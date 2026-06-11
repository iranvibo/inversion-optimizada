<?php

namespace App\Core\Simulation;

use App\Core\Contracts\HistoricalDataProviderInterface;
use InvalidArgumentException;

/**
 * Caso de uso (US02): proyectar la evolución histórica de una inversión
 * según el perfil de riesgo y el capital estimado del usuario.
 *
 * No conoce HTTP ni base de datos: recibe un puerto de datos históricos
 * y devuelve un DTO puro, lo que permite testearlo de forma aislada.
 */
class HistoricalSimulationService
{
    public function __construct(
        private readonly HistoricalDataProviderInterface $dataProvider,
    ) {
    }

    /**
     * Ejecuta la simulación componiendo los rendimientos mensuales
     * y calculando el peor drawdown histórico (pico a valle).
     *
     * @throws InvalidArgumentException si el capital no es positivo o no hay datos.
     */
    public function simulate(RiskProfile $profile, float $initialCapital): SimulationResult
    {
        if ($initialCapital <= 0) {
            throw new InvalidArgumentException('El capital inicial debe ser mayor que cero.');
        }

        $returns = $this->dataProvider->monthlyReturns($profile);

        if ($returns === []) {
            throw new InvalidArgumentException('No hay datos históricos disponibles para el perfil seleccionado.');
        }

        $series = [['period' => 'Inicio', 'value' => round($initialCapital, 2)]];

        $value = $initialCapital;
        $peak = $initialCapital;
        $maxDrawdown = 0.0;

        foreach ($returns as $point) {
            $value *= (1 + $point['return']);

            // Drawdown: distancia porcentual desde el máximo histórico alcanzado.
            $peak = max($peak, $value);
            $drawdown = ($peak - $value) / $peak * 100;
            $maxDrawdown = max($maxDrawdown, $drawdown);

            $series[] = ['period' => $point['period'], 'value' => round($value, 2)];
        }

        $totalReturn = ($value - $initialCapital) / $initialCapital * 100;

        return new SimulationResult(
            profile: $profile,
            initialCapital: $initialCapital,
            series: $series,
            finalValue: $value,
            totalReturnPercent: $totalReturn,
            maxDrawdownPercent: $maxDrawdown,
        );
    }
}
