<?php

namespace App\Core\Contracts;

/**
 * Contrato para el proveedor de señales externas (US06).
 */
interface SignalProviderInterface
{
    /**
     * Obtiene la señal de posición objetivo actual para un nivel de riesgo.
     *
     * El campo `profit` es el rendimiento no realizado de la posición abierta,
     * ya expresado en porcentaje sobre el margen invertido (p. ej. -1.0 = -1 %);
     * es 0.0 cuando la posición es CLOSE. Nótese que difiere del `profit` del
     * historial, que es una fracción (0.01 = 1 %).
     *
     * @param  string  $riskLevel  Nivel de riesgo ('conservador', 'balanceado', 'agresivo').
     * @return array{position: string, issued_at: string, signal_id: string|int, profit: float}
     *
     * @throws \Exception Si ocurre un error al conectar con el proveedor.
     */
    public function getCurrentSignal(string $riskLevel): array;

    /**
     * Obtiene el historial de señales para un nivel de riesgo.
     *
     * @param  string  $riskLevel  Nivel de riesgo ('conservador', 'balanceado', 'agresivo').
     * @return array<int, array{date: string, time: string, position: string, profit: float}>
     *
     * @throws \Exception Si ocurre un error al conectar con el proveedor.
     */
    public function getSignalHistory(string $riskLevel): array;
}
