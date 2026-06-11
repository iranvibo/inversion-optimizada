<?php

namespace Tests\Unit;

use App\Core\Contracts\HistoricalDataProviderInterface;
use App\Core\Simulation\HistoricalSimulationService;
use App\Core\Simulation\RiskProfile;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas unitarias del caso de uso de simulación (US02).
 * El proveedor de datos históricos se mockea para aislar la lógica de cálculo.
 */
class HistoricalSimulationServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Crea el servicio con un proveedor mockeado que devuelve los retornos dados.
     *
     * @param array<int, array{period: string, return: float}> $returns
     */
    private function makeService(array $returns): HistoricalSimulationService
    {
        $provider = Mockery::mock(HistoricalDataProviderInterface::class);
        $provider->shouldReceive('monthlyReturns')->andReturn($returns);

        return new HistoricalSimulationService($provider);
    }

    /**
     * Happy path: la curva de capital compone los retornos mes a mes.
     */
    public function test_it_compounds_monthly_returns_into_equity_curve(): void
    {
        $service = $this->makeService([
            ['period' => '2024-01', 'return' => 0.10],  // 1000 -> 1100
            ['period' => '2024-02', 'return' => -0.10], // 1100 -> 990
            ['period' => '2024-03', 'return' => 0.20],  // 990 -> 1188
        ]);

        $result = $service->simulate(RiskProfile::Balanceado, 1000.0);

        // Serie: punto inicial + un punto por mes
        $this->assertCount(4, $result->series);
        $this->assertSame(['period' => 'Inicio', 'value' => 1000.0], $result->series[0]);
        $this->assertEqualsWithDelta(1100.0, $result->series[1]['value'], 0.01);
        $this->assertEqualsWithDelta(990.0, $result->series[2]['value'], 0.01);
        $this->assertEqualsWithDelta(1188.0, $result->series[3]['value'], 0.01);

        $this->assertEqualsWithDelta(1188.0, $result->finalValue, 0.01);
        $this->assertEqualsWithDelta(18.8, $result->totalReturnPercent, 0.01);
    }

    /**
     * Escenario 2: el peor drawdown se mide desde el pico máximo hasta el valle.
     */
    public function test_it_calculates_max_drawdown_from_peak_to_trough(): void
    {
        $service = $this->makeService([
            ['period' => '2024-01', 'return' => 0.25],  // 1000 -> 1250 (pico)
            ['period' => '2024-02', 'return' => -0.20], // 1250 -> 1000 (caída 20%)
            ['period' => '2024-03', 'return' => -0.10], // 1000 -> 900  (caída acumulada 28%)
            ['period' => '2024-04', 'return' => 0.50],  // 900 -> 1350 (recuperación, no afecta al drawdown)
        ]);

        $result = $service->simulate(RiskProfile::Agresivo, 1000.0);

        // Peor caída: de 1250 a 900 = 28%
        $this->assertEqualsWithDelta(28.0, $result->maxDrawdownPercent, 0.01);
    }

    /**
     * Escenario 1: el resultado escala linealmente con el capital del slider.
     */
    public function test_result_scales_linearly_with_capital(): void
    {
        $returns = [
            ['period' => '2024-01', 'return' => 0.05],
            ['period' => '2024-02', 'return' => 0.05],
        ];

        $small = $this->makeService($returns)->simulate(RiskProfile::Conservador, 1000.0);
        $large = $this->makeService($returns)->simulate(RiskProfile::Conservador, 5000.0);

        $this->assertEqualsWithDelta($small->finalValue * 5, $large->finalValue, 0.01);
        // El rendimiento porcentual y el drawdown no dependen del capital
        $this->assertEqualsWithDelta($small->totalReturnPercent, $large->totalReturnPercent, 0.001);
        $this->assertEqualsWithDelta($small->maxDrawdownPercent, $large->maxDrawdownPercent, 0.001);
    }

    /**
     * Edge case: sin meses negativos el drawdown es cero.
     */
    public function test_drawdown_is_zero_when_returns_never_fall(): void
    {
        $service = $this->makeService([
            ['period' => '2024-01', 'return' => 0.02],
            ['period' => '2024-02', 'return' => 0.03],
        ]);

        $result = $service->simulate(RiskProfile::Conservador, 1000.0);

        $this->assertSame(0.0, $result->maxDrawdownPercent);
    }

    /**
     * Escenario 2: el mensaje de transparencia se redacta en lenguaje natural.
     */
    public function test_drawdown_message_is_in_natural_language(): void
    {
        $service = $this->makeService([
            ['period' => '2024-01', 'return' => -0.12],
        ]);

        $result = $service->simulate(RiskProfile::Balanceado, 1000.0);

        $this->assertSame('La cuenta ha tenido caídas temporales de hasta un 12,0%.', $result->drawdownMessage());
    }

    /**
     * Error: capital cero o negativo es rechazado.
     */
    public function test_it_rejects_non_positive_capital(): void
    {
        $service = $this->makeService([['period' => '2024-01', 'return' => 0.01]]);

        $this->expectException(InvalidArgumentException::class);
        $service->simulate(RiskProfile::Balanceado, 0.0);
    }

    /**
     * Error: dataset histórico vacío es rechazado.
     */
    public function test_it_rejects_empty_historical_dataset(): void
    {
        $service = $this->makeService([]);

        $this->expectException(InvalidArgumentException::class);
        $service->simulate(RiskProfile::Balanceado, 1000.0);
    }

    /**
     * El DTO se serializa con todas las claves que consume el frontend.
     */
    public function test_result_serializes_all_keys_for_the_frontend(): void
    {
        $service = $this->makeService([['period' => '2024-01', 'return' => 0.05]]);

        $payload = $service->simulate(RiskProfile::Agresivo, 1000.0)->toArray();

        $this->assertSame('agresivo', $payload['profile']);
        $this->assertSame(1000.0, $payload['initial_capital']);
        $this->assertArrayHasKey('series', $payload);
        $this->assertArrayHasKey('final_value', $payload);
        $this->assertArrayHasKey('total_return_percent', $payload);
        $this->assertArrayHasKey('max_drawdown_percent', $payload);
        $this->assertArrayHasKey('drawdown_message', $payload);
    }
}
