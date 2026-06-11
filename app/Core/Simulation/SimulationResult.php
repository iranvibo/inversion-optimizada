<?php

namespace App\Core\Simulation;

/**
 * DTO inmutable con el resultado de la simulación histórica.
 */
final class SimulationResult
{
    /**
     * @param array<int, array{period: string, value: float}> $series Curva de capital periodo a periodo.
     */
    public function __construct(
        public readonly RiskProfile $profile,
        public readonly float $initialCapital,
        public readonly array $series,
        public readonly float $finalValue,
        public readonly float $totalReturnPercent,
        public readonly float $maxDrawdownPercent,
    ) {
    }

    /**
     * Mensaje de transparencia en lenguaje natural (US02 - Escenario 2).
     */
    public function drawdownMessage(): string
    {
        return sprintf(
            'La cuenta ha tenido caídas temporales de hasta un %s%%.',
            number_format($this->maxDrawdownPercent, 1, ',', '.')
        );
    }

    /**
     * Representación serializable para la capa HTTP (JSON).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'profile' => $this->profile->value,
            'initial_capital' => round($this->initialCapital, 2),
            'series' => $this->series,
            'final_value' => round($this->finalValue, 2),
            'total_return_percent' => round($this->totalReturnPercent, 2),
            'max_drawdown_percent' => round($this->maxDrawdownPercent, 2),
            'drawdown_message' => $this->drawdownMessage(),
        ];
    }
}
