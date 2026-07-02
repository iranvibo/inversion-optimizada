<?php

namespace App\Core\Exceptions;

class BinanceInvalidCredentialsException extends BinanceException implements InvalidBrokerCredentialsInterface
{
    protected $message = 'Las credenciales de Binance ingresadas no son válidas o han expirado.';
}
