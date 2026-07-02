<?php

namespace App\Core\Exceptions;

use Exception;

/**
 * Excepción base de cualquier canal de ejecución (broker/exchange).
 * Las excepciones específicas de cada canal (Binance, Hyperliquid) extienden
 * de esta clase para que el código agnóstico al canal pueda capturarlas juntas.
 */
class BrokerException extends Exception
{
    //
}
