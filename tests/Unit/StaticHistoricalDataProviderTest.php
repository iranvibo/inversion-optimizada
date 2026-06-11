<?php

namespace Tests\Unit;

use App\Core\Simulation\RiskProfile;
use App\Infrastructure\MarketData\StaticHistoricalDataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas unitarias del adaptador de datos históricos fijos.
 */
class StaticHistoricalDataProviderTest extends TestCase
{
    private StaticHistoricalDataProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new StaticHistoricalDataProvider();
    }

    /**
     * Cada perfil tiene una serie completa, cronológica y con meses negativos
     * (necesarios para mostrar drawdowns con honestidad).
     */
    public function test_every_profile_has_a_complete_and_honest_dataset(): void
    {
        foreach (RiskProfile::cases() as $profile) {
            $returns = $this->provider->monthlyReturns($profile);

            $this->assertCount(24, $returns, "El perfil {$profile->value} debe tener 24 meses.");
            $this->assertSame('2024-01', $returns[0]['period']);
            $this->assertSame('2025-12', $returns[23]['period']);

            $negatives = array_filter($returns, fn (array $point) => $point['return'] < 0);
            $this->assertNotEmpty($negatives, "El perfil {$profile->value} debe incluir meses negativos.");
        }
    }

    /**
     * Los datos son deterministas: dos llamadas devuelven exactamente lo mismo.
     */
    public function test_dataset_is_deterministic(): void
    {
        $this->assertSame(
            $this->provider->monthlyReturns(RiskProfile::Balanceado),
            $this->provider->monthlyReturns(RiskProfile::Balanceado),
        );
    }

    /**
     * La volatilidad crece con el riesgo: agresivo > balanceado > conservador.
     */
    public function test_volatility_increases_with_risk(): void
    {
        $worstMonth = function (RiskProfile $profile): float {
            $returns = array_column($this->provider->monthlyReturns($profile), 'return');

            return min($returns);
        };

        $this->assertLessThan($worstMonth(RiskProfile::Conservador), $worstMonth(RiskProfile::Balanceado));
        $this->assertLessThan($worstMonth(RiskProfile::Balanceado), $worstMonth(RiskProfile::Agresivo));
    }
}
