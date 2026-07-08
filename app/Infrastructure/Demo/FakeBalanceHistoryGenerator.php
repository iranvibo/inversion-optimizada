<?php

namespace App\Infrastructure\Demo;

use DateTimeImmutable;

/**
 * Adaptador de infraestructura de DEMO (US03): genera un historial sintético
 * de balances mediante un paseo aleatorio determinista (sembrado por usuario),
 * para poder probar los filtros Día/Semana/Mes sin esperar semanas de
 * sincronizaciones reales.
 */
class FakeBalanceHistoryGenerator
{
    /**
     * Genera la serie histórica: un punto diario durante 30 días y puntos
     * horarios en las últimas 48 horas (para que el filtro "Día" tenga densidad).
     *
     * @return list<array{captured_at: DateTimeImmutable, balance: float}>
     */
    public function generate(float $baseBalance, DateTimeImmutable $now, int $seed): array
    {
        // Determinista a propósito: misma semilla => misma serie (testeable).
        mt_srand($seed);

        $series = [];
        $value = $baseBalance;

        // Fase 1: 30 días de puntos diarios (hasta hace 48 horas)
        $cursor = $now->modify('-30 days');
        $intradayStart = $now->modify('-48 hours');

        while ($cursor < $intradayStart) {
            $value = $this->nextValue($value, maxDeltaPercent: 1.8);
            $series[] = ['captured_at' => $cursor, 'balance' => $value];
            $cursor = $cursor->modify('+1 day');
        }

        // Fase 2: 48 horas de puntos horarios (oscilaciones más pequeñas)
        $cursor = $intradayStart;
        while ($cursor <= $now) {
            $value = $this->nextValue($value, maxDeltaPercent: 0.4);
            $series[] = ['captured_at' => $cursor, 'balance' => $value];
            $cursor = $cursor->modify('+1 hour');
        }

        return $series;
    }

    /**
     * Siguiente paso del paseo aleatorio con ligero sesgo alcista.
     */
    private function nextValue(float $current, float $maxDeltaPercent): float
    {
        $deltaPercent = mt_rand((int) (-$maxDeltaPercent * 90), (int) ($maxDeltaPercent * 100)) / 10000;

        return round($current * (1 + $deltaPercent), 2);
    }
}
