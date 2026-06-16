<?php

namespace Tests\Feature;

use App\Events\BalanceUpdated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Pruebas de integración del balance y gráfico de evolución (US03):
 * flujo completo desde la petición HTTP / comando hasta la base de datos
 * y el evento de broadcast.
 */
class DashboardBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.binance.mock' => true]);

        // Usuario activo con Binance vinculado (precondición de la US03)
        $this->user = User::factory()->create([
            'binance_api_key' => 'my_secure_api_key',
            'binance_secret_key' => 'my_secret',
            'binance_verified' => true,
            'estimated_capital' => 1000,
            'onboarding_completed_at' => now(),
        ]);
    }

    /**
     * Atajo para sembrar puntos de la serie temporal del usuario.
     */
    private function snapshot(string $capturedAt, float $balance): void
    {
        $this->user->balanceSnapshots()->create([
            'balance' => $balance,
            'captured_at' => now()->modify($capturedAt),
        ]);
    }

    // ─── Acceso ──────────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard.balance'))->assertRedirect(route('login'));
        $this->post(route('binance.simulate-balance-sync'))->assertRedirect(route('login'));
    }

    // ─── Escenario 1: Dashboard con balance grande y legible ─────────────

    public function test_dashboard_renders_consolidated_balance(): void
    {
        $this->snapshot('-1 hour', 12345.67);

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Balance Total');
        $response->assertSee('12.345,67'); // formato es-ES, letra grande en la vista
    }

    public function test_dashboard_shows_placeholder_when_there_is_no_history(): void
    {
        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Sin datos todavía');
    }

    // ─── Escenario 2: Filtro temporal y cambio en lenguaje humano ────────

    public function test_history_endpoint_returns_series_and_human_readable_change(): void
    {
        $this->snapshot('-2 hours', 1000.00);
        $this->snapshot('-1 hour', 1100.00);

        $response = $this->actingAs($this->user)
            ->getJson(route('dashboard.balance', ['range' => 'day']));

        $response->assertOk();
        $response->assertJsonStructure([
            'range',
            'series' => [['t', 'value']],
            'current_balance',
            'change_percent',
            'change_message',
        ]);

        $data = $response->json();
        $this->assertSame('day', $data['range']);
        $this->assertCount(2, $data['series']);
        $this->assertEquals(1100.0, $data['current_balance']);
        $this->assertEqualsWithDelta(10.0, $data['change_percent'], 0.01);
        $this->assertStringContainsString('ha subido un 10,0 % en el último día', $data['change_message']);
    }

    public function test_history_endpoint_filters_series_by_selected_range(): void
    {
        $this->snapshot('-2 months', 800.00); // fuera de todos los rangos
        $this->snapshot('-3 days', 1000.00);
        $this->snapshot('-1 hour', 1100.00);

        $fetch = fn (string $range) => $this->actingAs($this->user)
            ->getJson(route('dashboard.balance', ['range' => $range]))
            ->assertOk()
            ->json('series');

        $this->assertCount(1, $fetch('day'));
        $this->assertCount(2, $fetch('week'));
        $this->assertCount(2, $fetch('month'));
    }

    public function test_history_endpoint_defaults_to_month_range(): void
    {
        $response = $this->actingAs($this->user)->getJson(route('dashboard.balance'));

        $response->assertOk();
        $this->assertSame('month', $response->json('range'));
    }

    public function test_history_endpoint_rejects_invalid_range(): void
    {
        $this->actingAs($this->user)
            ->getJson(route('dashboard.balance', ['range' => 'year']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['range']);
    }

    public function test_history_endpoint_does_not_leak_other_users_balances(): void
    {
        $other = User::factory()->create();
        $other->balanceSnapshots()->create(['balance' => 99999.99, 'captured_at' => now()]);

        $response = $this->actingAs($this->user)->getJson(route('dashboard.balance'));

        $response->assertOk();
        $this->assertSame([], $response->json('series'));
    }

    // ─── Escenario 3: Sincronización reactiva del backend ────────────────

    public function test_sync_command_persists_snapshot_and_broadcasts_update(): void
    {
        Event::fake([BalanceUpdated::class]);

        $this->artisan('binance:sync-balances')->assertExitCode(0);

        $this->assertDatabaseCount('balance_snapshots', 1);
        $this->assertSame(1, $this->user->balanceSnapshots()->count());

        Event::assertDispatched(BalanceUpdated::class, function (BalanceUpdated $event) {
            return $event->user->is($this->user)
                && $event->balance > 0
                && $event->broadcastAs() === 'balance.updated'
                && array_keys($event->broadcastWith()) === ['balance', 'captured_at', 'current_position'];
        });
    }

    public function test_sync_command_skips_users_without_linked_binance(): void
    {
        Event::fake([BalanceUpdated::class]);

        $unlinked = User::factory()->create(['binance_verified' => false]);

        $this->artisan('binance:sync-balances')->assertExitCode(0);

        $this->assertSame(0, $unlinked->balanceSnapshots()->count());
        Event::assertDispatchedTimes(BalanceUpdated::class, 1); // solo el usuario vinculado
    }

    public function test_simulated_sync_backfills_demo_history_and_notifies_dashboard(): void
    {
        Event::fake([BalanceUpdated::class]);

        $response = $this->actingAs($this->user)
            ->post(route('binance.simulate-balance-sync'));

        $response->assertSessionHas('success');

        // Backfill de ~30 días + 48 horas + el snapshot recién sincronizado
        $this->assertGreaterThan(30, $this->user->balanceSnapshots()->count());
        Event::assertDispatched(BalanceUpdated::class);
    }

    public function test_simulated_sync_requires_linked_binance(): void
    {
        Event::fake([BalanceUpdated::class]);

        $unlinked = User::factory()->create(['binance_verified' => false]);

        $response = $this->actingAs($unlinked)
            ->post(route('binance.simulate-balance-sync'));

        $response->assertSessionHas('error');
        $this->assertSame(0, $unlinked->balanceSnapshots()->count());
        Event::assertNotDispatched(BalanceUpdated::class);
    }
}
