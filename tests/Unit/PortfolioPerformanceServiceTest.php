<?php

namespace Tests\Unit;

use App\Core\Portfolio\PortfolioPerformanceService;
use App\Core\Portfolio\TimeRange;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas unitarias del caso de uso de evolución del balance (US03).
 * El servicio es lógica pura: se prueba aislado, sin base de datos ni HTTP.
 */
class PortfolioPerformanceServiceTest extends TestCase
{
    private PortfolioPerformanceService $service;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PortfolioPerformanceService;
        $this->now = new DateTimeImmutable('2026-06-11 12:00:00');
    }

    /**
     * @return array{captured_at: DateTimeImmutable, balance: float}
     */
    private function snapshot(string $modifier, float $balance): array
    {
        return ['captured_at' => $this->now->modify($modifier), 'balance' => $balance];
    }

    // ─── Caminos felices ─────────────────────────────────────────────────

    public function test_reports_positive_change_in_human_language(): void
    {
        $report = $this->service->report([
            $this->snapshot('-6 hours', 1000.0),
            $this->snapshot('-3 hours', 1050.0),
            $this->snapshot('-1 hour', 1100.0),
        ], TimeRange::Day, $this->now);

        $this->assertCount(3, $report->series);
        $this->assertSame(1100.0, $report->currentBalance);
        $this->assertEqualsWithDelta(10.0, $report->changePercent, 0.001);
        $this->assertSame('Tu balance ha subido un 10,0 % en el último día.', $report->changeMessage);
    }

    public function test_reports_negative_change_in_human_language(): void
    {
        $report = $this->service->report([
            $this->snapshot('-5 days', 1000.0),
            $this->snapshot('-1 day', 900.0),
        ], TimeRange::Week, $this->now);

        $this->assertEqualsWithDelta(-10.0, $report->changePercent, 0.001);
        $this->assertSame('Tu balance ha bajado un 10,0 % en la última semana.', $report->changeMessage);
    }

    public function test_reports_stability_when_change_is_negligible(): void
    {
        $report = $this->service->report([
            $this->snapshot('-2 hours', 1000.0),
            $this->snapshot('-1 hour', 1000.5), // +0,05%: por debajo del umbral
        ], TimeRange::Day, $this->now);

        $this->assertSame('Tu balance se ha mantenido estable en el último día.', $report->changeMessage);
    }

    public function test_serializes_to_the_expected_contract(): void
    {
        $report = $this->service->report([
            $this->snapshot('-2 hours', 1000.0),
            $this->snapshot('-1 hour', 1100.0),
        ], TimeRange::Day, $this->now);

        $data = $report->toArray();

        $this->assertSame(
            ['range', 'series', 'current_balance', 'change_percent', 'change_message'],
            array_keys($data),
        );
        $this->assertSame('day', $data['range']);
        $this->assertSame(['t', 'value'], array_keys($data['series'][0]));
        $this->assertSame(10.0, $data['change_percent']);
    }

    // ─── Filtro temporal (Escenario 2) ───────────────────────────────────

    public function test_filters_snapshots_outside_the_selected_range(): void
    {
        $snapshots = [
            $this->snapshot('-2 months', 800.0), // fuera de cualquier rango
            $this->snapshot('-3 days', 1000.0),
            $this->snapshot('-1 hour', 1100.0),
        ];

        $day = $this->service->report($snapshots, TimeRange::Day, $this->now);
        $week = $this->service->report($snapshots, TimeRange::Week, $this->now);
        $month = $this->service->report($snapshots, TimeRange::Month, $this->now);

        $this->assertCount(1, $day->series);
        $this->assertCount(2, $week->series);
        $this->assertCount(2, $month->series);

        // El cambio se calcula solo dentro de la ventana visible
        $this->assertEqualsWithDelta(10.0, $week->changePercent, 0.001);
    }

    public function test_sorts_snapshots_chronologically_before_reporting(): void
    {
        $report = $this->service->report([
            $this->snapshot('-1 hour', 1200.0),
            $this->snapshot('-6 hours', 1000.0),
            $this->snapshot('-3 hours', 1100.0),
        ], TimeRange::Day, $this->now);

        $values = array_column($report->series, 'value');

        $this->assertSame([1000.0, 1100.0, 1200.0], $values);
        $this->assertEqualsWithDelta(20.0, $report->changePercent, 0.001);
    }

    // ─── Casos límite y de error ─────────────────────────────────────────

    public function test_handles_empty_history_gracefully(): void
    {
        $report = $this->service->report([], TimeRange::Month, $this->now);

        $this->assertSame([], $report->series);
        $this->assertNull($report->currentBalance);
        $this->assertSame(0.0, $report->changePercent);
        $this->assertSame(
            'Aún no hay historial suficiente para calcular la evolución de tu balance.',
            $report->changeMessage,
        );
    }

    public function test_single_snapshot_reports_no_change(): void
    {
        $report = $this->service->report([
            $this->snapshot('-1 hour', 1500.0),
        ], TimeRange::Day, $this->now);

        $this->assertSame(1500.0, $report->currentBalance);
        $this->assertSame(0.0, $report->changePercent);
        $this->assertStringContainsString('Aún no hay historial suficiente', $report->changeMessage);
    }

    public function test_zero_initial_balance_does_not_divide_by_zero(): void
    {
        $report = $this->service->report([
            $this->snapshot('-2 hours', 0.0),
            $this->snapshot('-1 hour', 100.0),
        ], TimeRange::Day, $this->now);

        $this->assertSame(0.0, $report->changePercent);
    }
}
