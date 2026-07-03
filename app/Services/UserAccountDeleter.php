<?php

namespace App\Services;

use App\Core\Contracts\BinanceBrokerInterface;
use App\Core\Contracts\HyperliquidBrokerInterface;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Eliminación definitiva de una cuenta de usuario (GDPR / administración).
 *
 * Antes de borrar, si el bot está activo intenta cerrar las posiciones
 * abiertas en cada canal vinculado para no dejar operaciones huérfanas en el
 * exchange. Compartido entre la auto-eliminación (AuthController) y la
 * administración de usuarios (AdminUserController).
 */
class UserAccountDeleter
{
    public function __construct(
        private readonly BinanceBrokerInterface $binanceBroker,
        private readonly HyperliquidBrokerInterface $hyperliquidBroker,
    ) {}

    public function delete(User $user): void
    {
        if ($user->bot_active) {
            // Cada canal se cierra de forma independiente: el fallo de uno no
            // impide intentar cerrar el otro ni bloquea la eliminación (GDPR).
            if ($user->isBinanceLinked()) {
                try {
                    $this->binanceBroker
                        ->closeOpenPositions($user->binance_api_key, $user->binance_secret_key);
                    Log::info("Cierre de posiciones preventivo (Binance) por eliminación de cuenta para el usuario ID: {$user->id}");
                } catch (\Exception $e) {
                    Log::critical("Fallo al cerrar posiciones en Binance al eliminar la cuenta del usuario ID: {$user->id}. Detalle: ".$e->getMessage());
                }
            }

            if ($user->isHyperliquidLinked()) {
                try {
                    $this->hyperliquidBroker
                        ->closeOpenPositions($user->hyperliquid_wallet_address, $user->hyperliquid_agent_key);
                    Log::info("Cierre de posiciones preventivo (Hyperliquid) por eliminación de cuenta para el usuario ID: {$user->id}");
                } catch (\Exception $e) {
                    Log::critical("Fallo al cerrar posiciones en Hyperliquid al eliminar la cuenta del usuario ID: {$user->id}. Detalle: ".$e->getMessage());
                }
            }
        }

        // Borrado definitivo (cascada configurada en BD).
        $user->delete();
    }
}
