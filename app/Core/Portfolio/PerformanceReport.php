<?php

namespace App\Core\Portfolio;

/**
 * DTO puro con la evolución del balance para una ventana temporal (US03).
 *
 * No conoce Eloquent ni HTTP: es el resultado del caso de uso y puede
 * serializarse tal cual hacia la capa de presentación.
 */
final class PerformanceReport
{
    /**
     * @param  list<array{t: string, value: float}>  $series  Serie temporal ya filtrada y ordenada.
     */
    public function __construct(
        public readonly TimeRange $range,
        public readonly array $series,
        public readonly ?float $currentBalance,
        public readonly float $changePercent,
        public readonly string $changeMessage,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'range' => $this->range->value,
            'series' => $this->series,
            'current_balance' => $this->currentBalance,
            'change_percent' => round($this->changePercent, 2),
            'change_message' => $this->changeMessage,
        ];
    }
}
