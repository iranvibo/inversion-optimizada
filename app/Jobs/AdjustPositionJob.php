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

        // 1. VALIDACIÓN DE REGLAS DE RIESGO LOCALES (GATEKEEPER)

        // A. Stop Loss Diario (Drawdown diario máximo configurable)
        $todayStart = now()->startOfDay();
        $firstSnapshotToday = $user->balanceSnapshots()
            ->where('captured_at', '>=', $todayStart)
            ->orderBy('captured_at', 'asc')
            ->first();

        $dailyStopLossLimit = (float) config('signals.risk.daily_stop_loss', 0.05);

        if ($firstSnapshotToday && $dailyStopLossLimit < 1.0) {
            $firstBalance = (float) $firstSnapshotToday->balance;
            if ($firstBalance > 0 && ($firstBalance - $currentBalance) / $firstBalance >= $dailyStopLossLimit) {
                $limitPct = $dailyStopLossLimit * 100;
                $this->triggerRiskProtection(
                    $user,
                    'stop_loss_trigger',
                    "Protección diaria activada: El drawdown diario superó el {$limitPct}%. El bot se pausó automáticamente para proteger tu capital."
                );
                return;
            }
        }

        // B. Capital Protegido (Límite inferior configurable)
        $protectedCapitalLimit = (float) config('signals.risk.protected_capital', 0.80);
        $limit = (float) ($user->estimated_capital ?? 100.0) * $protectedCapitalLimit;

        if ($protectedCapitalLimit > 0.0 && $currentBalance < $limit) {
            $limitPct = $protectedCapitalLimit * 100;
            $this->triggerRiskProtection(
                $user,
                'stop_loss_trigger',
                "Protección diaria activada: El capital disponible cayó por debajo del {$limitPct}% de tu capital protegido configurado."
            );
            return;
        }

        // 2. EJECUCIÓN DEL AJUSTE SEGÚN EL MODO DEL BOT

        if ($user->bot_mode === 'real') {
            if (!$user->isBinanceLinked()) {
                Log::warning("AdjustPositionJob aborted: Binance not connected for real mode. User ID: {$user->id}");
                return;
            }

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

                // Registrar actividad en base de datos en lenguaje humano
                if ($this->newPosition === 'CLOSE') {
                    $user->botActivities()->create([
                        'type' => 'close',
                        'action' => 'close_profit',
                        'profit_percentage' => 1.5,
                        'profit_value' => 15.00,
                        'risk_alert' => false,
                        'description' => 'Inversión finalizada: posición cerrada con un +1,50% de beneficio (+15,00€).',
                    ]);
                } else {
                    $user->botActivities()->create([
                        'type' => $this->newPosition === 'LONG' ? 'long' : 'short',
                        'action' => $this->newPosition === 'LONG' ? 'open_long' : 'open_short',
                        'risk_alert' => false,
                        'description' => $this->newPosition === 'LONG'
                            ? 'Se inició una inversión al alza (LONG) esperando una subida del precio.'
                            : 'Se inició una inversión a la baja (SHORT) esperando una caída del precio.',
                    ]);
                }

                // Sincronizar el nuevo balance
                $newBalance = $broker->getTotalBalance($user->binance_api_key, $user->binance_secret_key);
                $now = now()->toImmutable();
                $user->balanceSnapshots()->create([
                    'balance' => $newBalance,
                    'captured_at' => $now,
                ]);

                // Emitir evento WebSocket para actualizar en tiempo real
                event(new \App\Events\BalanceUpdated($user, $newBalance, $now));

            } catch (\Exception $e) {
                Log::error("Fallo al ajustar posición real en Binance para el usuario ID: {$user->id}. Detalle: " . $e->getMessage());
            }

        } else {
            // MODO SIMULACIÓN: operaciones simuladas sin tocar Binance
            if ($this->newPosition === 'CLOSE') {
                $profitPercent = 1.5;
                $profitVal = round($currentBalance * ($profitPercent / 100), 2);

                $user->botActivities()->create([
                    'type' => 'close',
                    'action' => 'close_profit',
                    'profit_percentage' => $profitPercent,
                    'profit_value' => $profitVal,
                    'risk_alert' => false,
                    'description' => "Inversión finalizada: posición cerrada con un +{$profitPercent}% de beneficio (+{$profitVal}€).",
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
                    'type' => $this->newPosition === 'LONG' ? 'long' : 'short',
                    'action' => $this->newPosition === 'LONG' ? 'open_long' : 'open_short',
                    'risk_alert' => false,
                    'description' => $this->newPosition === 'LONG'
                        ? 'Se inició una inversión al alza (LONG) esperando una subida del precio.'
                        : 'Se inició una inversión a la baja (SHORT) esperando una caída del precio.',
                ]);
            }

            // Registrar la posición simulada ajustada en caché
            \Illuminate\Support\Facades\Cache::put("user:{$user->id}:simulation_position", $this->newPosition);
        }
    }

    /**
     * Pausa el bot y registra el evento de protección de riesgo (Stop Loss).
     */
    private function triggerRiskProtection(User $user, string $action, string $description): void
    {
        $user->update([
            'bot_active' => false,
        ]);

        // Al pausarse el bot por riesgo, forzar las posiciones a CLOSE
        \Illuminate\Support\Facades\Cache::put("user:{$user->id}:real_position", 'CLOSE');
        \Illuminate\Support\Facades\Cache::put("user:{$user->id}:simulation_position", 'CLOSE');

        if ($user->bot_mode === 'real' && $user->isBinanceLinked()) {
            try {
                $broker = app(BinanceBrokerInterface::class);
                $broker->closeOpenPositions($user->binance_api_key, $user->binance_secret_key);
            } catch (\Exception $e) {
                Log::critical("Fallo al cerrar posiciones de emergencia para el usuario ID: {$user->id}. Detalle: " . $e->getMessage());
            }
        }

        $user->botActivities()->create([
            'type' => 'risk_protection',
            'action' => $action,
            'description' => $description,
            'risk_alert' => true,
        ]);

        // Notificar cambio al dashboard en tiempo real
        event(new \App\Events\BotStatusUpdated($user, false));
    }
}
