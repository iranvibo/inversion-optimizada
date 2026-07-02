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

    /**
     * Configura un contexto Binance "real" (mock=false) con el broker
     * interceptado por un doble, para poder probar la persistencia del
     * histórico real sin realizar llamadas de red.
     */
    private function fakeRealBroker(float $balance): void
    {
        config(['services.binance.mock' => false]);

        $broker = \Mockery::mock(\App\Core\Contracts\BinanceBrokerInterface::class);
        $broker->shouldReceive('getTotalBalance')->andReturn($balance);
        $this->app->instance(\App\Core\Contracts\BinanceBrokerInterface::class, $broker);
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

        // El histórico real solo se persiste con datos reales de Binance (no mock).
        $this->fakeRealBroker(1234.56);

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

    public function test_sync_command_does_not_persist_when_binance_is_mock(): void
    {
        Event::fake([BalanceUpdated::class]);

        // mock=true (de setUp): no se debe contaminar el histórico real con
        // valores del broker mock.
        $this->artisan('binance:sync-balances')->assertExitCode(0);

        $this->assertDatabaseCount('balance_snapshots', 0);
        Event::assertNotDispatched(BalanceUpdated::class);
    }

    public function test_sync_command_skips_users_without_linked_binance(): void
    {
        Event::fake([BalanceUpdated::class]);

        $this->fakeRealBroker(1234.56);

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

    // ─── Escenario 3: patrimonio neto en vivo (P/L de la posición) ───────

    public function test_live_endpoint_returns_current_equity_in_real_mode(): void
    {
        $this->user->update(['bot_mode' => 'real']);

        $response = $this->actingAs($this->user)->getJson(route('dashboard.balance.live'));

        $response->assertOk();
        $response->assertJson(['live' => true]);
        $this->assertGreaterThan(0, $response->json('balance'));
    }

    public function test_live_endpoint_is_inert_in_simulation_mode(): void
    {
        $this->user->update(['bot_mode' => 'simulation']);

        $response = $this->actingAs($this->user)->getJson(route('dashboard.balance.live'));

        $response->assertOk();
        $response->assertExactJson(['live' => false, 'balance' => null]);
    }

    public function test_live_endpoint_requires_authentication(): void
    {
        $this->getJson(route('dashboard.balance.live'))->assertUnauthorized();
    }

    public function test_history_endpoint_separates_channels_and_returns_only_active_channel_snapshots(): void
    {
        // Snapshot for Binance
        $this->user->balanceSnapshots()->create([
            'balance' => 1000.00,
            'captured_at' => now()->subMinutes(10),
            'trading_channel' => 'binance',
        ]);

        // Snapshot for Hyperliquid
        $this->user->balanceSnapshots()->create([
            'balance' => 2000.00,
            'captured_at' => now()->subMinutes(5),
            'trading_channel' => 'hyperliquid',
        ]);

        // When active channel is Binance, only Binance snapshots should be returned
        $this->user->update(['trading_channel' => 'binance']);
        $response = $this->actingAs($this->user)->getJson(route('dashboard.balance', ['range' => 'day']));
        $response->assertOk();
        $this->assertCount(1, $response->json('series'));
        $this->assertEquals(1000.00, $response->json('current_balance'));

        // When active channel is Hyperliquid, only Hyperliquid snapshots should be returned
        $this->user->update(['trading_channel' => 'hyperliquid']);
        $response = $this->actingAs($this->user)->getJson(route('dashboard.balance', ['range' => 'day']));
        $response->assertOk();
        $this->assertCount(1, $response->json('series'));
        $this->assertEquals(2000.00, $response->json('current_balance'));
    }
}
