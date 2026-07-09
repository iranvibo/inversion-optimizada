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

            // El profit de la posición en curso cambia en cada ciclo aunque la
            // señal no lo haga, así que se cachea siempre (lo lee la cabecera
            // del dashboard junto al Balance Total).
            Cache::put("signal:current_profit:{$riskLevel}", (float) ($signal['profit'] ?? 0.0));

            // 3. Comprobar si la señal difiere de la última conocida (Idempotencia)
            $cacheKey = "signal:last_known_position:{$riskLevel}";
            $lastKnownPosition = Cache::get($cacheKey);
            $signalChanged = $newPosition !== $lastKnownPosition;

            if ($signalChanged) {
                // Actualizar la última posición conocida
                Cache::put($cacheKey, $newPosition);
            }

            // 4. Encolar trabajo de ajuste para cada usuario activo con ese nivel
            // de riesgo. Además del cambio de señal global, reconciliamos la deriva
            // por usuario: en modo real la posición ejecutada (`real_position`)
            // puede quedar desincronizada del objetivo si un job anterior falló al
            // contactar Binance o no llegó a procesarse. Como la idempotencia es
            // global, sin esta reconciliación el usuario quedaría "atascado" en la
            // posición vieja hasta que la señal volviese a cambiar.
            $users = User::where('bot_active', true)
                ->where('risk_level', $riskLevel)
                ->get(['id', 'bot_mode']);

            $dispatched = 0;
            foreach ($users as $user) {
                $needsAdjustment = $signalChanged;

                if (! $needsAdjustment && $user->bot_mode === 'real') {
                    $effectivePosition = Cache::get("user:{$user->id}:real_position");
                    $needsAdjustment = $effectivePosition !== null && $effectivePosition !== $newPosition;
                }

                if ($needsAdjustment) {
                    AdjustPositionJob::dispatch($user->id, $newPosition);
                    $dispatched++;
                }
            }

            if ($signalChanged) {
                Log::info("Cambio de señal detectado para '{$riskLevel}': '{$lastKnownPosition}' -> '{$newPosition}'. Encolados {$dispatched} trabajos de ajuste.");
            } elseif ($dispatched > 0) {
                Log::warning("Reconciliación de posición para '{$riskLevel}': encolados {$dispatched} ajustes hacia '{$newPosition}' por desincronización (posible job fallido previo).");
            }
        }
    }
}
