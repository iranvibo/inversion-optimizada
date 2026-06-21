<?php

namespace App\Jobs;

use App\Core\Contracts\BinanceBrokerInterface;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Trabajo en segundo plano para procesar el ajuste de posición de un usuario (US06).
 */
class AdjustPositionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $newPosition
    ) {}

    /**
     * Execute the job.
     */
    public function handle(BinanceBrokerInterface $broker): void
    {
        $user = User::find($this->userId);
        if (!$user || !$user->bot_active) {
            return;
        }

        $latestSnapshot = $user->balanceSnapshots()->latest('captured_at')->first();
        $currentBalance = $latestSnapshot ? (float) $latestSnapshot->balance : (float) ($user->estimated_capital ?? 100.0);

        // EJECUCIÓN DEL AJUSTE SEGÚN EL MODO DEL BOT.
        //
        // Este proyecto NO toma decisiones de trading: se limita a replicar la
        // señal externa vigente. Por eso no existe ningún gatekeeper de riesgo
        // local (stop-loss diario ni suelo de "capital protegido") que pause el
        // bot por su cuenta. La única pausa automática admitida es por motivos
        // de seguridad (credenciales inválidas o permisos de retiro indebidos).

        if ($user->bot_mode === 'real') {
            if (!$user->isBinanceLinked()) {
                Log::warning("AdjustPositionJob aborted: Binance not connected for real mode. User ID: {$user->id}");
                return;
            }

            // Posición real vigente antes del ajuste. Sirve para detectar un "flip"
            // (LONG↔SHORT directo), donde adjustPosition cierra la posición contraria
            // y abre la nueva en una sola llamada. Sin esto, el cierre intermedio (y su
            // P/L) se perdería en el feed porque solo registraríamos la apertura.
            $previousRealPosition = strtoupper(
                (string) \Illuminate\Support\Facades\Cache::get("user:{$user->id}:real_position", 'CLOSE')
            );

            try {
                // Ajustar posición en Binance partiendo del estado real del exchange.
                // Devuelve false si la operación es idempotente (la posición ya
                // está en el estado objetivo o no hay nada que cerrar).
                $changed = $broker->adjustPosition(
                    $user->binance_api_key,
                    $user->binance_secret_key,
                    $this->newPosition,
                    $user->risk_level ?? 'balanceado'
                );

                // Registrar la posición real objetivo en caché (refleja el estado deseado).
                \Illuminate\Support\Facades\Cache::put("user:{$user->id}:real_position", $this->newPosition);

                // Sin cambios en el exchange: no se registra actividad ni se sincroniza balance.
                if (! $changed) {
                    return;
                }

                if ($this->newPosition === 'CLOSE') {
                    // Patrimonio neto real (equity) tras liquidar las órdenes en el exchange.
                    $newBalance = $this->fetchSettledBalance($broker, $user, $currentBalance);

                    // Capital con el que se abrió la posición que se acaba de cerrar.
                    // Si no se registró (p.ej. bot reiniciado, posición abierta fuera
                    // del bot), se usa el último balance conocido como referencia.
                    $openCapital = (float) \Illuminate\Support\Facades\Cache::pull(
                        "user:{$user->id}:open_capital",
                        $currentBalance
                    );

                    $this->recordCloseActivity($user, $openCapital, $newBalance);

                    // Sincronizar el nuevo balance
                    $now = now()->toImmutable();
                    // En real solo se persiste el histórico con datos reales de Binance,
                    // nunca valores del broker mock (evita contaminar la curva real).
                    if (! config('services.binance.mock')) {
                        $user->balanceSnapshots()->create([
                            'balance' => $newBalance,
                            'captured_at' => $now,
                        ]);
                    }

                    // Emitir evento WebSocket para actualizar en tiempo real
                    event(new \App\Events\BalanceUpdated($user, $newBalance, $now));
                } else {
                    // Capital de apertura de la nueva posición. Si venimos de CLOSE,
                    // coincide con currentBalance (evitando latencia). Si es un flip,
                    // se recalcula con el equity tras cerrar la posición anterior.
                    $openCapital = $currentBalance;

                    // Flip LONG↔SHORT: adjustPosition cerró la posición contraria antes
                    // de abrir la nueva. Registramos ese cierre con su P/L real para que
                    // aparezca en el historial igual que un cierre explícito.
                    if ($previousRealPosition === 'LONG' || $previousRealPosition === 'SHORT') {
                        // Equity tras el flip: abrir la nueva posición no altera el patrimonio
                        // neto de inmediato, así que el balance refleja el resultado del cierre.
                        $balanceAfterClose = $this->fetchSettledBalance($broker, $user, $currentBalance);
                        $closedCapital = (float) \Illuminate\Support\Facades\Cache::pull(
                            "user:{$user->id}:open_capital",
                            $currentBalance
                        );

                        $this->recordCloseActivity($user, $closedCapital, $balanceAfterClose);

                        // El capital de apertura de la nueva posición es el equity tras el cierre.
                        $openCapital = $balanceAfterClose;
                    }

                    \Illuminate\Support\Facades\Cache::put("user:{$user->id}:open_capital", $openCapital);

                    $user->botActivities()->create([
                        'bot_mode' => 'real',
                        'type' => $this->newPosition === 'LONG' ? 'long' : 'short',
                        'action' => $this->newPosition === 'LONG' ? 'open_long' : 'open_short',
                        'risk_alert' => false,
                        'description' => $this->newPosition === 'LONG'
                            ? 'Se inició una inversión al alza (LONG) esperando una subida del precio.'
                            : 'Se inició una inversión a la baja (SHORT) esperando una caída del precio.',
                    ]);

                    // Sincronizar el balance de apertura (coincide con el equity actual al no cambiar la equidad al abrir)
                    $now = now()->toImmutable();
                    // Solo se persiste el histórico real cuando Binance no está en mock.
                    if (! config('services.binance.mock')) {
                        $user->balanceSnapshots()->create([
                            'balance' => $openCapital,
                            'captured_at' => $now,
                        ]);
                    }

                    // Emitir evento WebSocket para actualizar en tiempo real
                    event(new \App\Events\BalanceUpdated($user, $openCapital, $now));
                }

            } catch (\App\Core\Exceptions\BinanceInvalidCredentialsException $e) {
                $user->update([
                    'bot_active' => false,
                ]);
                \Illuminate\Support\Facades\Cache::put("user:{$user->id}:real_position", 'CLOSE');
                \Illuminate\Support\Facades\Cache::put("user:{$user->id}:simulation_position", 'CLOSE');
                Log::warning("AdjustPositionJob: Bot pausado para el usuario ID: {$user->id} debido a credenciales inválidas.");
                event(new \App\Events\BotStatusUpdated($user, false));
            } catch (\Exception $e) {
                Log::error("Fallo al ajustar posición real en Binance para el usuario ID: {$user->id}. Detalle: " . $e->getMessage());
            }

        } else {
            // MODO SIMULACIÓN: operaciones simuladas sin tocar Binance
            if ($this->newPosition === 'CLOSE') {
                $profitPercent = 1.5;
                $profitVal = round($currentBalance * ($profitPercent / 100), 2);

                $user->botActivities()->create([
                    'bot_mode' => 'simulation',
                    'type' => 'close',
                    'action' => 'close_profit',
                    'profit_percentage' => $profitPercent,
                    'profit_value' => $profitVal,
                    'risk_alert' => false,
                    'description' => "Inversión finalizada: posición cerrada con un +{$profitPercent}% de beneficio (+{$profitVal}$).",
                ]);

                // Registrar un nuevo balance snapshot simulado
                $newBalance = round($currentBalance + $profitVal, 2);
                $now = now()->toImmutable();
                $user->balanceSnapshots()->create([
                    'balance' => $newBalance,
                    'captured_at' => $now,
                ]);

                // Emitir evento WebSocket para actualizar balance en tiempo real
                event(new \App\Events\BalanceUpdated($user, $newBalance, $now));
            } else {
                $user->botActivities()->create([
                    'bot_mode' => 'simulation',
                    'type' => $this->newPosition === 'LONG' ? 'long' : 'short',
                    'action' => $this->newPosition === 'LONG' ? 'open_long' : 'open_short',
                    'risk_alert' => false,
                    'description' => $this->newPosition === 'LONG'
                        ? 'Se inició una inversión al alza (LONG) esperando una subida del precio.'
                        : 'Se inició una inversión a la baja (SHORT) esperando una caída del precio.',
                ]);

                // Emitir evento WebSocket para actualizar en tiempo real
                $now = now()->toImmutable();
                event(new \App\Events\BalanceUpdated($user, $currentBalance, $now));
            }

            // Registrar la posición simulada ajustada en caché
            \Illuminate\Support\Facades\Cache::put("user:{$user->id}:simulation_position", $this->newPosition);
        }
    }

    /**
     * Obtiene el patrimonio neto real (equity) tras un cambio en el exchange,
     * dando tiempo a que se liquiden las órdenes. Si el balance muestra una caída
     * sospechosa de más del 5% respecto a la referencia, reintenta hasta 3 veces,
     * ya que puede tratarse de una discrepancia temporal de Binance.
     */
    private function fetchSettledBalance(BinanceBrokerInterface $broker, User $user, float $referenceBalance): float
    {
        if (! app()->runningUnitTests()) {
            sleep(2);
        }

        $newBalance = $broker->getTotalBalance($user->binance_api_key, $user->binance_secret_key);

        if (! app()->runningUnitTests()) {
            $attempts = 0;
            while ($attempts < 3 && $referenceBalance > 0 && ($referenceBalance - $newBalance) / $referenceBalance > 0.05) {
                sleep(1);
                $newBalance = $broker->getTotalBalance($user->binance_api_key, $user->binance_secret_key);
                $attempts++;
            }
        }

        return $newBalance;
    }

    /**
     * Registra la actividad de cierre calculando el beneficio/pérdida REAL a partir
     * del capital con el que se abrió la posición y el capital tras cerrarla.
     *
     *   profit = capital_cierre − capital_apertura
     *   porcentaje = profit / capital_apertura × 100
     *
     * Según el signo se clasifica como cierre con beneficio (close_profit) o con
     * pérdida (close_loss). El importe y el porcentaje quedan guardados (con signo)
     * y se muestran al usuario en el feed de actividad.
     */
    private function recordCloseActivity(User $user, float $openCapital, float $closeCapital): void
    {
        $profitValue = round($closeCapital - $openCapital, 2);
        $profitPercentage = $openCapital > 0
            ? round(($profitValue / $openCapital) * 100, 2)
            : 0.0;

        $user->botActivities()->create([
            'bot_mode' => 'real',
            'type' => 'close',
            'action' => $profitValue >= 0 ? 'close_profit' : 'close_loss',
            'profit_percentage' => $profitPercentage,
            'profit_value' => $profitValue,
            'risk_alert' => false,
        ]);
    }
}
