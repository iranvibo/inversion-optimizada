<?php

namespace App\Core\Portfolio;

use DateTimeImmutable;

/**
 * Caso de uso (US03): construir el informe de evolución del balance
 * para una ventana temporal (Día / Semana / Mes), incluyendo el
 * porcentaje de cambio expresado en lenguaje humano.
 *
 * Lógica pura: recibe los snapshots como datos planos y no conoce
 * HTTP ni base de datos, lo que permite testearla de forma aislada.
 */
class PortfolioPerformanceService
{
    /**
     * Por debajo de este umbral (en %) el cambio se comunica como "estable"
     * para no estresar al usuario con micro-fluctuaciones.
     */
    private const STABLE_THRESHOLD_PERCENT = 0.1;

    /**
     * @param  list<array{captured_at: DateTimeImmutable, balance: float}>  $snapshots
     */
    public function report(array $snapshots, TimeRange $range, ?DateTimeImmutable $now = null): PerformanceReport
    {
        $now ??= new DateTimeImmutable;
        $start = $range->startAt($now);

        // Defensa en profundidad: aunque la capa de persistencia ya filtre por
        // fecha, el caso de uso garantiza la ventana temporal y el orden.
        $window = array_values(array_filter(
            $snapshots,
            fn (array $point) => $point['captured_at'] >= $start,
        ));

        usort($window, fn (array $a, array $b) => $a['captured_at'] <=> $b['captured_at']);

        $series = array_map(
            fn (array $point) => [
                't' => $point['captured_at']->format(DATE_ATOM),
                'value' => round($point['balance'], 2),
            ],
            $window,
        );

        $currentBalance = $series === [] ? null : end($series)['value'];
        $changePercent = $this->changePercent($window);

        return new PerformanceReport(
            range: $range,
            series: $series,
            currentBalance: $currentBalance,
            changePercent: $changePercent,
            changeMessage: $this->humanMessage($window, $changePercent, $range),
        );
    }

    /**
     * Cambio porcentual entre el primer y el último punto de la ventana.
     *
     * @param  list<array{captured_at: DateTimeImmutable, balance: float}>  $window
     */
    private function changePercent(array $window): float
    {
        if (count($window) < 2) {
            return 0.0;
        }

        $first = $window[0]['balance'];
        $last = end($window)['balance'];

        // Evita división por cero si la cuenta arrancó vacía.
        if ($first == 0.0) {
            return 0.0;
        }

        return ($last - $first) / $first * 100;
    }

    /**
     * Porcentaje de cambio del balance "en lenguaje humano" (Escenario 2).
     *
     * @param  list<array{captured_at: DateTimeImmutable, balance: float}>  $window
     */
    private function humanMessage(array $window, float $changePercent, TimeRange $range): string
    {
        if (count($window) < 2) {
            return 'Aún no hay historial suficiente para calcular la evolución de tu balance.';
        }

        $formatted = number_format(abs($changePercent), 1, ',', '.');

        if (abs($changePercent) < self::STABLE_THRESHOLD_PERCENT) {
            return "Tu balance se ha mantenido estable en {$range->label()}.";
        }

        return $changePercent > 0
            ? "Tu balance ha subido un {$formatted} % en {$range->label()}."
            : "Tu balance ha bajado un {$formatted} % en {$range->label()}.";
    }
}
