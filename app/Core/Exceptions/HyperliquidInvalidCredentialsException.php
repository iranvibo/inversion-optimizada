<?php

namespace App\Core\Exceptions;

class HyperliquidInvalidCredentialsException extends HyperliquidException implements InvalidBrokerCredentialsInterface
{
    protected $message = 'Las credenciales de Hyperliquid ingresadas no son válidas (revisa la dirección de tu wallet y la clave de tu API wallet).';
}
