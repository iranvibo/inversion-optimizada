<?php

namespace App\Core\Trading;

use App\Events\BalanceUpdated;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Reconcilia de forma pasiva la posición real del exchange con el estado almacenado en caché (US06).
 */
class PositionReconciler
{
    public function __construct(
        protected readonly BrokerResolver $brokerResolver
    ) {}

    /**
     * Reconcilia la posición real del exchange con la caché local de la aplicación.
     * Si detecta que la posición se cerró en el exchange (por ejemplo, por un Take Profit
     * o Stop Loss ejecutado directamente en el broker/exchange), registra la actividad
     * de cierre con el beneficio/pérdida real y genera un snapshot de balance.
     *
     * @return bool True si se registró una actividad de cierre en este ciclo; false en caso contrario.
     */
    public function reconcile(User $user, ?float $balance = null): bool
    {
        if ($user->bot_mode !== 'real' || ! $user->isBrokerLinked()) {
            return false;
        }

        if ($this->brokerResolver->isMock($user->tradingChannel())) {
            return false;
        }

        try {
            $broker = $this->brokerResolver->forUser($user);

            // Usamos un caché de corta duración para evitar sobrecargar el exchange con peticiones repetidas en ráfaga
            $actualPosition = Cache::remember(
                "user:{$user->id}:exchange_position",
                now()->addSeconds(5),
                fn () => strtoupper($broker->getOpenPosition($user->brokerApiKey(), $user->brokerSecretKey()))
            );

            $cachedPosition = strtoupper((string) Cache::get("user:{$user->id}:real_position", 'CLOSE'));

            if ($actualPosition !== $cachedPosition) {
                Log::info("Reconciliation mismatch detected for User {$user->id} on {$user->tradingChannel()}: Cached: {$cachedPosition}, Actual: {$actualPosition}");

                $closed = false;

                if (($cachedPosition === 'LONG' || $cachedPosition === 'SHORT') && $actualPosition === 'CLOSE') {
                    if ($balance === null) {
                        $balance = $broker->getTotalBalance($user->brokerApiKey(), $user->brokerSecretKey());
                    }

                    $openCapital = (float) Cache::pull("user:{$user->id}:open_capital");
                    if ($openCapital <= 0) {
                        $latestSnapshot = $user->balanceSnapshots()
                            ->where('trading_channel', $user->tradingChannel())
                            ->latest('captured_at')
                            ->first();
                        $openCapital = $latestSnapshot ? (float) $latestSnapshot->balance : $balance;
                    }

                    $profitValue = round($balance - $openCapital, 2);
                    $profitPercentage = $openCapital > 0
                        ? round(($profitValue / $openCapital) * 100, 2)
                        : 0.0;

                    // Crear actividad de cierre por el exchange (Take Profit / Stop Loss)
                    $user->botActivities()->create([
                        'bot_mode' => 'real',
                        'type' => 'close',
                        'action' => $profitValue >= 0 ? 'close_profit' : 'close_loss',
                        'profit_percentage' => $profitPercentage,
                        'profit_value' => $profitValue,
                        'risk_alert' => false,
                        'description' => 'Inversión finalizada: posición cerrada en el exchange.',
                    ]);

                    // Persistir el snapshot de balance del canal activo
                    $capturedAt = now();
                    $user->balanceSnapshots()->create([
                        'balance' => $balance,
                        'captured_at' => $capturedAt,
                        'trading_channel' => $user->tradingChannel(),
                    ]);

                    // Emitir evento WebSocket para actualizar en tiempo real
                    event(new BalanceUpdated($user, $balance, $capturedAt->toImmutable()));

                    $closed = true;
                }

                // Sincronizar la caché con la posición real del exchange
                Cache::put("user:{$user->id}:real_position", $actualPosition);

                return $closed;
            }
        } catch (\Throwable $e) {
            Log::error("Failed to reconcile position for User {$user->id}: " . $e->getMessage());
        }

        return false;
    }
}
