<?php

namespace App\Console\Commands;

use App\Core\Contracts\SignalProviderInterface;
use App\Jobs\AdjustPositionJob;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Comando Artisan para sondear la API externa de señales (US06).
 */
class PollSignals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'signals:poll {--once}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consulta la API externa de señales y ajusta las posiciones de los bots activos';

    /**
     * Execute the console command.
     */
    public function handle(SignalProviderInterface $signalProvider): int
    {
        if ($this->option('once') || app()->runningUnitTests()) {
            $this->poll($signalProvider);
            return 0;
        }

        $startTime = time();
        // Bucle para emular sondeo sub-minuto (cada 5 segundos durante un minuto)
        while (time() - $startTime < 55) {
            $loopStart = microtime(true);

            $this->poll($signalProvider);

            $elapsed = microtime(true) - $loopStart;
            $sleepTime = 5 - $elapsed;
            if ($sleepTime > 0) {
                usleep((int) ($sleepTime * 1000000));
            }
        }

        return 0;
    }

    /**
     * Ejecuta un ciclo individual de sondeo.
     */
    private function poll(SignalProviderInterface $signalProvider): void
    {
        // 1. Obtener niveles de riesgo activos de los usuarios con bot encendido
        $activeRiskLevels = User::where('bot_active', true)
            ->distinct()
            ->pluck('risk_level')
            ->filter()
            ->all();

        foreach ($activeRiskLevels as $riskLevel) {
            $riskLevel = strtolower($riskLevel);

            try {
                // 2. Consultar la señal actual del proveedor (HTTP o Mock)
                $signal = $signalProvider->getCurrentSignal($riskLevel);
                $newPosition = strtoupper($signal['position'] ?? 'CLOSE');
            } catch (\Exception $e) {
                Log::error("Error en el sondeo de señales para el nivel '{$riskLevel}': " . $e->getMessage());

                // Escenario 5: Registrar la incidencia y marcar inestabilidad en cache
                Cache::put("signal_provider_unstable:{$riskLevel}", true, now()->addMinutes(5));
                continue;
            }

            // Limpiar marca de inestabilidad si la consulta fue exitosa
            Cache::forget("signal_provider_unstable:{$riskLevel}");

            // 3. Comprobar si la señal difiere de la última conocida (Idempotencia)
            $cacheKey = "signal:last_known_position:{$riskLevel}";
            $lastKnownPosition = Cache::get($cacheKey);

            if ($newPosition !== $lastKnownPosition) {
                // Actualizar la última posición conocida
                Cache::put($cacheKey, $newPosition);

                // 4. Encolar trabajo de ajuste para cada usuario activo con ese nivel de riesgo
                $users = User::where('bot_active', true)
                    ->where('risk_level', $riskLevel)
                    ->get(['id']);

                foreach ($users as $user) {
                    AdjustPositionJob::dispatch($user->id, $newPosition);
                }

                Log::info("Cambio de señal detectado para '{$riskLevel}': '{$lastKnownPosition}' -> '{$newPosition}'. Encolados trabajos de ajuste para {$users->count()} usuarios.");
            }
        }
    }
}
