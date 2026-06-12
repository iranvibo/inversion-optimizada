<?php

namespace App\Core\Contracts;

use App\Core\Exceptions\BinanceException;
use App\Core\Exceptions\BinanceInvalidCredentialsException;

interface BinanceBrokerInterface
{
    /**
     * Obtiene las restricciones y permisos de la API Key de Binance.
     *
     * Retorna una estructura con banderas de permisos (por ejemplo, 'enableWithdrawals' => bool).
     *
     *
     * @throws BinanceInvalidCredentialsException
     * @throws BinanceException
     */
    public function checkApiRestrictions(string $apiKey, string $secretKey): array;

    /**
     * Obtiene el balance total consolidado de la cuenta de Binance,
     * expresado en la divisa fiat de referencia (EUR).
     *
     *
     * @throws BinanceInvalidCredentialsException
     * @throws BinanceException
     */
    public function getTotalBalance(string $apiKey, string $secretKey): float;

    /**
     * Cancela todas las órdenes abiertas y gestiona el cierre preventivo
     * de las posiciones en Binance para mitigar el riesgo al pausar el bot.
     *
     * @throws BinanceInvalidCredentialsException
     * @throws BinanceException
     */
    public function closeOpenPositions(string $apiKey, string $secretKey): bool;

    /**
     * Ajusta la posición en Binance según la señal (LONG, SHORT, CLOSE).
     * En el MVP, se simula o ejecuta cancelando órdenes previas y colocando la nueva.
     *
     * @throws BinanceInvalidCredentialsException
     * @throws BinanceException
     */
    public function adjustPosition(string $apiKey, string $secretKey, string $position): bool;
}
