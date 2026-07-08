<?php

namespace App\Core\Exceptions;

class BinanceWithdrawalPermissionException extends BinanceException
{
    protected $message = 'Alerta de Seguridad: La API Key tiene los permisos de retiro habilitados.';
}
