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
}
