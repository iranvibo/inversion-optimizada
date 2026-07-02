<?php

namespace App\Core\Contracts;

use App\Core\Exceptions\BinanceException;
use App\Core\Exceptions\BinanceInvalidCredentialsException;

interface BinanceBrokerInterface extends BrokerInterface
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
     * Obtiene el saldo disponible en la cartera de Futuros USDⓈ-M denominado en el
     * activo de margen configurado (USDT/USDC). Es el capital realmente utilizable
     * para abrir posiciones apalancadas y se consulta en cada apertura (US06).
     *
     * @throws BinanceInvalidCredentialsException
     * @throws BinanceException
     */
    public function getAvailableBalance(string $apiKey, string $secretKey): float;

    /**
     * Cancela todas las órdenes abiertas y gestiona el cierre preventivo
     * de las posiciones en Binance para mitigar el riesgo al pausar el bot.
     *
     * @throws BinanceInvalidCredentialsException
     * @throws BinanceException
     */
    public function closeOpenPositions(string $apiKey, string $secretKey): bool;

    /**
     * Devuelve la posición efectivamente abierta en Binance en este instante:
     * 'LONG', 'SHORT' o 'CLOSE' (sin posición abierta).
     *
     * Se consulta el estado real del exchange para decidir si abrir, cerrar,
     * mantener o invertir una posición (US06).
     *
     * @throws BinanceInvalidCredentialsException
     * @throws BinanceException
     */
    public function getOpenPosition(string $apiKey, string $secretKey): string;

    /**
     * Ajusta la posición en Binance según la señal objetivo (LONG, SHORT, CLOSE),
     * partiendo siempre del estado real consultado en el exchange (US06):
     *
     *   - Si ya hay una posición abierta en la misma dirección, no se reabre.
     *   - Si la señal es de cierre (CLOSE), se cierra la posición abierta (si la hay).
     *   - Si hay una posición contraria abierta, se cierra y se abre la nueva.
     *   - Al abrir, se consulta el capital disponible más actualizado y se
     *     compromete la fracción asociada al perfil de riesgo, con el
     *     apalancamiento configurado (10x por defecto, "de ser posible").
     *
     * @param  string  $riskLevel  Perfil de riesgo del usuario (define la fracción de capital).
     * @return bool true si se ejecutó un cambio de estado en el exchange; false si fue idempotente.
     *
     * @throws BinanceInvalidCredentialsException
     * @throws BinanceException
     */
    public function adjustPosition(string $apiKey, string $secretKey, string $position, string $riskLevel = 'balanceado'): bool;
}
