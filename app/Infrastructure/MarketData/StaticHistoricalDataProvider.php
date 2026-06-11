<?php

namespace App\Infrastructure\MarketData;

use App\Core\Contracts\HistoricalDataProviderInterface;
use App\Core\Simulation\RiskProfile;

/**
 * Adaptador de infraestructura: dataset histórico FIJO de 24 meses
 * por perfil de riesgo (criterio E de INVEST: "set de datos simulados
 * fijos para las proyecciones"). No requiere conexión con Binance.
 *
 * Los datasets son deterministas a propósito: el resultado de la
 * simulación es reproducible y testeable.
 */
class StaticHistoricalDataProvider implements HistoricalDataProviderInterface
{
    /**
     * Rendimientos mensuales históricos del bot por perfil.
     * Incluyen meses negativos reales para mostrar drawdowns con honestidad.
     *
     * @var array<string, list<float>>
     */
    private const MONTHLY_RETURNS = [
        // Crecimiento suave, caídas contenidas (~ -4% peor drawdown)
        'conservador' => [
            0.011, 0.009, -0.006, 0.012, 0.008, -0.011,
            0.010, 0.013, -0.008, 0.009, 0.011, 0.007,
            -0.014, -0.009, 0.012, 0.010, 0.008, -0.005,
            0.013, 0.009, 0.011, -0.007, 0.010, 0.012,
        ],
        // Crecimiento medio, caídas moderadas (~ -12% peor drawdown)
        'balanceado' => [
            0.024, 0.018, -0.021, 0.027, 0.015, -0.034,
            0.022, 0.031, -0.018, 0.020, 0.026, 0.014,
            -0.052, -0.038, 0.029, 0.024, 0.019, -0.012,
            0.033, 0.021, 0.027, -0.016, 0.025, 0.030,
        ],
        // Crecimiento alto, caídas pronunciadas (~ -25% peor drawdown)
        'agresivo' => [
            0.052, 0.038, -0.045, 0.061, 0.033, -0.072,
            0.048, 0.067, -0.039, 0.044, 0.058, 0.031,
            -0.110, -0.085, 0.064, 0.052, 0.041, -0.027,
            0.073, 0.046, 0.059, -0.035, 0.055, 0.066,
        ],
    ];

    /**
     * Mes inicial de la serie histórica fija.
     */
    private const SERIES_START = '2024-01';

    public function monthlyReturns(RiskProfile $profile): array
    {
        $returns = self::MONTHLY_RETURNS[$profile->value];

        $series = [];
        $period = \DateTimeImmutable::createFromFormat('!Y-m', self::SERIES_START);

        foreach ($returns as $monthlyReturn) {
            $series[] = [
                'period' => $period->format('Y-m'),
                'return' => $monthlyReturn,
            ];
            $period = $period->modify('+1 month');
        }

        return $series;
    }
}
