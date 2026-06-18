<?php

namespace Tests\Unit;

use App\Core\Simulation\RiskProfile;
use Tests\TestCase;

/**
 * Pruebas de la regla de negocio de fracción de capital por perfil de riesgo (US06).
 */
class RiskProfileTest extends TestCase
{
    public function test_capital_fraction_per_profile(): void
    {
        $this->assertSame(0.20, RiskProfile::Conservador->capitalFraction());
        $this->assertSame(0.50, RiskProfile::Balanceado->capitalFraction());
        $this->assertSame(0.90, RiskProfile::Agresivo->capitalFraction());
    }

    public function test_from_string_is_case_insensitive(): void
    {
        $this->assertSame(RiskProfile::Agresivo, RiskProfile::fromString('Agresivo'));
        $this->assertSame(RiskProfile::Agresivo, RiskProfile::fromString('AGRESIVO'));
        $this->assertSame(RiskProfile::Conservador, RiskProfile::fromString('conservador'));
    }

    public function test_from_string_falls_back_to_balanced_for_unknown_values(): void
    {
        $this->assertSame(RiskProfile::Balanceado, RiskProfile::fromString(null));
        $this->assertSame(RiskProfile::Balanceado, RiskProfile::fromString('inexistente'));
    }
}
