<?php

namespace App\Core\Exceptions;

/**
 * Marcador para las excepciones de credenciales inválidas de cualquier canal
 * (BinanceInvalidCredentialsException, HyperliquidInvalidCredentialsException).
 *
 * Permite a los consumidores agnósticos al canal (jobs, comandos) capturar
 * "credenciales inválidas" con un único catch sin acoplarse al exchange,
 * preservando a la vez la jerarquía original de cada canal.
 */
interface InvalidBrokerCredentialsInterface
{
    //
}
