<?php

namespace App\Core\Trading;

use App\Core\Contracts\BinanceBrokerInterface;
use App\Core\Contracts\BrokerInterface;
use App\Core\Contracts\HyperliquidBrokerInterface;
use App\Models\User;

/**
 * Resuelve el canal de ejecución (broker) activo de cada usuario.
 *
 * El usuario elige por dónde se ejecutan sus operaciones en real
 * (users.trading_channel): 'binance' (exchange centralizado, por defecto) o
 * 'hyperliquid' (DEX de perpetuos on-chain, sin las restricciones de derivados
 * del EEE). El resto de la aplicación opera contra el contrato genérico
 * BrokerInterface y este resolver decide la implementación concreta.
 *
 * Se resuelve vía contenedor (interfaces por canal) para que los tests puedan
 * seguir sustituyendo cada canal de forma independiente.
 */
class BrokerResolver
{
    /**
     * Broker del canal activo del usuario.
     */
    public function forUser(User $user): BrokerInterface
    {
        return $this->forChannel($user->tradingChannel());
    }

    /**
     * Broker de un canal concreto ('binance' | 'hyperliquid').
     */
    public function forChannel(string $channel): BrokerInterface
    {
        return match ($channel) {
            User::CHANNEL_HYPERLIQUID => app(HyperliquidBrokerInterface::class),
            default => app(BinanceBrokerInterface::class),
        };
    }

    /**
     * Indica si el driver del canal está en modo mock (desarrollo/tests).
     * En mock nunca se persisten snapshots del histórico real (ver memoria #16).
     */
    public function isMock(string $channel): bool
    {
        return (bool) match ($channel) {
            User::CHANNEL_HYPERLIQUID => config('services.hyperliquid.mock'),
            default => config('services.binance.mock'),
        };
    }
}
