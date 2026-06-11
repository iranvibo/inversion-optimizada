<?php

namespace App\Core\Contracts;

use App\Core\Simulation\RiskProfile;

/**
 * Puerto de salida (Clean Architecture): abstrae la fuente de
 * rendimientos históricos para que el caso de uso de simulación
 * no dependa de la infraestructura (dataset fijo, API, base de datos...).
 */
interface HistoricalDataProviderInterface
{
    /**
     * Devuelve la serie histórica de rendimientos mensuales del bot
     * para un perfil de riesgo, ordenada cronológicamente.
     *
     * @return array<int, array{period: string, return: float}> p. ej. [['period' => '2023-01', 'return' => 0.021], ...]
     */
    public function monthlyReturns(RiskProfile $profile): array;
}
