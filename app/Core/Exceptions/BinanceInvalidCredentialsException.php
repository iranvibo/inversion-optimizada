<?php

namespace App\Core\Exceptions;

class BinanceInvalidCredentialsException extends BinanceException
{
    protected $message = 'Las credenciales de Binance ingresadas no son válidas o han expirado.';
}
