<?php

namespace App\Core\Contracts;

use App\Core\Exceptions\BrokerException;
use App\Core\Exceptions\InvalidBrokerCredentialsInterface;

/**
 * Contrato genérico de un canal de ejecución de trading (broker/exchange).
 *
 * Cada canal interpreta el par de credenciales según su naturaleza:
 *   - Binance:     $apiKey = API Key,              $secretKey = Secret Key (HMAC).
 *   - Hyperliquid: $apiKey = dirección de la wallet principal (0x...),
 *                  $secretKey = clave privada de la API wallet (agente) delegada,
 *                  que puede operar pero NO puede retirar ni transferir fondos.
 *
 * Las señales a ejecutar son siempre LONG, SHORT o CLOSE (US06), y los métodos
 * comparten la semántica documentada en BinanceBrokerInterface.
 */
interface BrokerInterface
{
    /**
     * Obtiene las restricciones/permisos de las credenciales del canal.
     * Retorna al menos la bandera 'enableWithdrawals' => bool: la promesa de
     * seguridad de la plataforma exige que las credenciales NO puedan retirar.
     *
     * @throws InvalidBrokerCredentialsInterface
     * @throws BrokerException
     */
    public function checkApiRestrictions(string $apiKey, string $secretKey): array;

    /**
     * Patrimonio neto total (equity) de la cuenta, incluyendo el P/L latente de
     * la posición abierta, en la divisa de referencia del canal (US03).
     *
     * @throws InvalidBrokerCredentialsInterface
     * @throws BrokerException
     */
    public function getTotalBalance(string $apiKey, string $secretKey): float;

    /**
     * Saldo disponible como colateral para abrir nuevas posiciones (US06).
     *
     * @throws InvalidBrokerCredentialsInterface
     * @throws BrokerException
     */
    public function getAvailableBalance(string $apiKey, string $secretKey): float;

    /**
     * Cancela las órdenes abiertas y aplana (cierra) la posición abierta, como
     * cierre preventivo al pausar el bot o cambiar de modo/canal (US04/US07).
     *
     * @throws InvalidBrokerCredentialsInterface
     * @throws BrokerException
     */
    public function closeOpenPositions(string $apiKey, string $secretKey): bool;

    /**
     * Posición efectivamente abierta en el canal en este instante:
     * 'LONG', 'SHORT' o 'CLOSE' (sin posición).
     *
     * @throws InvalidBrokerCredentialsInterface
     * @throws BrokerException
     */
    public function getOpenPosition(string $apiKey, string $secretKey): string;

    /**
     * Ajusta la posición hacia la señal objetivo (LONG, SHORT, CLOSE) partiendo
     * siempre del estado real consultado en el canal (US06):
     *   - Misma dirección ya abierta: no se reabre (no-op).
     *   - CLOSE: solo cierra si hay algo abierto.
     *   - Dirección contraria: cierra y abre la nueva.
     *   - Al abrir se consulta el capital disponible actualizado y se compromete
     *     la fracción del perfil de riesgo con el apalancamiento configurado.
     *
     * @param  string  $riskLevel  Perfil de riesgo (define la fracción de capital).
     * @return bool true si se ejecutó un cambio de estado; false si fue idempotente.
     *
     * @throws InvalidBrokerCredentialsInterface
     * @throws BrokerException
     */
    public function adjustPosition(string $apiKey, string $secretKey, string $position, string $riskLevel = 'balanceado'): bool;
}
