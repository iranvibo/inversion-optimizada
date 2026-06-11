<?php

namespace App\Core\Contracts;

interface BinanceBrokerInterface
{
    /**
     * Obtiene las restricciones y permisos de la API Key de Binance.
     *
     * Retorna una estructura con banderas de permisos (por ejemplo, 'enableWithdrawals' => bool).
     *
     * @param string $apiKey
     * @param string $secretKey
     * @return array
     *
     * @throws \App\Core\Exceptions\BinanceInvalidCredentialsException
     * @throws \App\Core\Exceptions\BinanceException
     */
    public function checkApiRestrictions(string $apiKey, string $secretKey): array;
}
