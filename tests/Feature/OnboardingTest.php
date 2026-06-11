<?php

namespace Tests\Feature;

use App\Http\Requests\OnboardingSimulationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de integración del onboarding interactivo (US02):
 * flujo completo desde la petición HTTP hasta la base de datos.
 */
class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Usuario recién registrado: aún sin onboarding completado
        $this->user = User::factory()->create([
            'onboarding_completed_at' => null,
        ]);
    }

    // ─── Acceso y navegación ─────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('onboarding.show'))->assertRedirect(route('login'));
        $this->post(route('onboarding.simulate'))->assertRedirect(route('login'));
        $this->post(route('onboarding.complete'))->assertRedirect(route('login'));
    }

    public function test_new_user_sees_the_onboarding_screen(): void
    {
        $response = $this->actingAs($this->user)->get(route('onboarding.show'));

        $response->assertOk();
        $response->assertViewIs('onboarding.simulation');
        // Los tres perfiles de riesgo están disponibles (Escenario 1)
        $response->assertSee('Conservador');
        $response->assertSee('Balanceado');
        $response->assertSee('Agresivo');
    }

    public function test_user_with_completed_onboarding_is_redirected_to_dashboard(): void
    {
        $this->user->update(['onboarding_completed_at' => now()]);

        $this->actingAs($this->user)
            ->get(route('onboarding.show'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_login_redirects_new_users_to_onboarding(): void
    {
        $response = $this->post(route('login'), [
            'email' => $this->user->email,
            'password' => 'password', // contraseña por defecto de la factory
        ]);

        $response->assertRedirect(route('onboarding.show'));
    }

    // ─── Escenario 1: Proyección dinámica según parámetros ──────────────

    public function test_simulation_returns_dynamic_projection_for_given_parameters(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('onboarding.simulate'), [
            'risk_profile' => 'balanceado',
            'capital' => 1000,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'profile',
            'initial_capital',
            'series' => [['period', 'value']],
            'final_value',
            'total_return_percent',
            'max_drawdown_percent',
            'drawdown_message',
        ]);

        $data = $response->json();
        $this->assertSame('balanceado', $data['profile']);
        // JSON serializa floats enteros como int, por eso se compara valor y no tipo
        $this->assertEquals(1000.0, $data['initial_capital']);
        // La serie arranca en el capital indicado y tiene 24 meses + punto inicial
        $this->assertEquals(1000.0, $data['series'][0]['value']);
        $this->assertCount(25, $data['series']);
    }

    public function test_projection_changes_when_parameters_change(): void
    {
        $simulate = fn (string $profile, int $capital) => $this->actingAs($this->user)
            ->postJson(route('onboarding.simulate'), ['risk_profile' => $profile, 'capital' => $capital])
            ->json();

        $conservative = $simulate('conservador', 1000);
        $aggressive = $simulate('agresivo', 1000);
        $biggerCapital = $simulate('conservador', 5000);

        // Cambiar de perfil recalcula la proyección y el drawdown (criterio T de INVEST)
        $this->assertNotEquals($conservative['final_value'], $aggressive['final_value']);
        $this->assertGreaterThan($conservative['max_drawdown_percent'], $aggressive['max_drawdown_percent']);

        // Mover el slider de capital escala la proyección
        $this->assertEqualsWithDelta($conservative['final_value'] * 5, $biggerCapital['final_value'], 0.5);
    }

    // ─── Escenario 2: Transparencia en drawdowns ─────────────────────────

    public function test_simulation_exposes_drawdown_in_natural_language(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('onboarding.simulate'), [
            'risk_profile' => 'balanceado',
            'capital' => 1000,
        ]);

        $response->assertOk();
        $data = $response->json();

        $this->assertGreaterThan(0, $data['max_drawdown_percent']);
        $this->assertStringContainsString('La cuenta ha tenido caídas temporales de hasta un', $data['drawdown_message']);
    }

    // ─── Validaciones (casos de error) ───────────────────────────────────

    public function test_simulation_rejects_invalid_risk_profile(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('onboarding.simulate'), ['risk_profile' => 'temerario', 'capital' => 1000])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['risk_profile']);
    }

    public function test_simulation_rejects_capital_out_of_slider_bounds(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('onboarding.simulate'), [
                'risk_profile' => 'balanceado',
                'capital' => OnboardingSimulationRequest::MIN_CAPITAL - 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['capital']);

        $this->actingAs($this->user)
            ->postJson(route('onboarding.simulate'), [
                'risk_profile' => 'balanceado',
                'capital' => OnboardingSimulationRequest::MAX_CAPITAL + 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['capital']);
    }

    public function test_simulation_rejects_non_numeric_capital(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('onboarding.simulate'), [
                'risk_profile' => 'balanceado',
                'capital' => 'DROP TABLE users;', // entrada maliciosa: rechazada por validación
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['capital']);
    }

    // ─── Escenario 3: Persistencia de parámetros iniciales ──────────────

    public function test_completing_onboarding_persists_profile_and_capital_as_simulation_defaults(): void
    {
        $response = $this->actingAs($this->user)->post(route('onboarding.complete'), [
            'risk_profile' => 'agresivo',
            'capital' => 2500,
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        $this->user->refresh();
        $this->assertSame('agresivo', $this->user->risk_level);
        $this->assertEquals(2500.00, $this->user->estimated_capital);
        $this->assertSame('simulation', $this->user->bot_mode); // el bot queda en modo simulación
        $this->assertNotNull($this->user->onboarding_completed_at);
        $this->assertTrue($this->user->hasCompletedOnboarding());
    }

    public function test_completing_onboarding_rejects_invalid_parameters_and_persists_nothing(): void
    {
        $response = $this->actingAs($this->user)->post(route('onboarding.complete'), [
            'risk_profile' => 'invalido',
            'capital' => 50,
        ]);

        $response->assertSessionHasErrors(['risk_profile', 'capital']);

        $this->user->refresh();
        $this->assertNull($this->user->estimated_capital);
        $this->assertNull($this->user->onboarding_completed_at);
    }
}
