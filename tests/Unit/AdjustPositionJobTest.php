<?php

namespace Tests\Unit;

use App\Core\Contracts\BinanceBrokerInterface;
use App\Events\BalanceUpdated;
use App\Events\BotStatusUpdated;
use App\Jobs\AdjustPositionJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

/**
 * Pruebas unitarias del trabajo de ajuste de posición (US06).
 *
 * Cubre el gatekeeper de riesgo local (stop-loss diario y capital protegido),
 * y la ejecución del ajuste tanto en modo simulación como en modo real.
 */
class AdjustPositionJobTest extends TestCase
{
    use RefreshDatabase;

    /** @var \Mockery\MockInterface&BinanceBrokerInterface */
    protected $broker;

    protected function setUp(): void
    {
        parent::setUp();

        // Reglas de riesgo deterministas para las pruebas.
        config([
            'signals.risk.daily_stop_loss' => 0.05,
            'signals.risk.protected_capital' => 0.80,
        ]);

        $this->broker = Mockery::mock(BinanceBrokerInterface::class);
        $this->app->instance(BinanceBrokerInterface::class, $this->broker);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function runJob(int $userId, string $position): void
    {
        (new AdjustPositionJob($userId, $position))->handle($this->broker);
    }

    // ─── Guardas tempranas ───────────────────────────────────────────────

    public function test_does_nothing_when_user_is_missing(): void
    {
        // No debe lanzar excepción aunque el usuario no exista.
        $this->runJob(99999, 'LONG');

        $this->assertDatabaseCount('bot_activities', 0);
    }

    public function test_does_nothing_when_bot_is_inactive(): void
    {
        $user = User::factory()->create([
            'bot_active' => false,
            'bot_mode' => 'simulation',
            'risk_level' => 'balanceado',
            'estimated_capital' => 1000,
        ]);

        $this->runJob($user->id, 'LONG');

        $this->assertSame(0, $user->botActivities()->count());
    }

    // ─── Gatekeeper A: Stop-loss diario ──────────────────────────────────

    public function test_daily_stop_loss_pauses_bot_and_forces_close(): void
    {
        Event::fake([BotStatusUpdated::class, BalanceUpdated::class]);

        $user = User::factory()->create([
            'bot_active' => true,
            'bot_mode' => 'simulation',
            'risk_level' => 'balanceado',
            'estimated_capital' => 1000,
        ]);

        // Primer snapshot del día y caída posterior del 6% (> 5% permitido).
        $user->balanceSnapshots()->create(['balance' => 1000, 'captured_at' => now()->startOfDay()->addHours(1)]);
        $user->balanceSnapshots()->create(['balance' => 940, 'captured_at' => now()->startOfDay()->addHours(4)]);

        $this->runJob($user->id, 'LONG');

        $user->refresh();
        $this->assertFalse($user->bot_active, 'El bot debe pausarse al superar el stop-loss diario.');

        $this->assertDatabaseHas('bot_activities', [
            'user_id' => $user->id,
            'type' => 'risk_protection',
            'action' => 'stop_loss_trigger',
            'risk_alert' => true,
        ]);

        $this->assertSame('CLOSE', Cache::get("user:{$user->id}:simulation_position"));
        $this->assertSame('CLOSE', Cache::get("user:{$user->id}:real_position"));

        Event::assertDispatched(BotStatusUpdated::class);
        // No debe ejecutarse el ajuste normal tras la protección.
        Event::assertNotDispatched(BalanceUpdated::class);
    }

    public function test_daily_stop_loss_not_triggered_within_limit(): void
    {
        $user = User::factory()->create([
            'bot_active' => true,
            'bot_mode' => 'simulation',
            'risk_level' => 'balanceado',
            'estimated_capital' => 1000,
        ]);

        // Caída del 3% (dentro del 5% permitido) → no debe pausar.
        $user->balanceSnapshots()->create(['balance' => 1000, 'captured_at' => now()->startOfDay()->addHours(1)]);
        $user->balanceSnapshots()->create(['balance' => 970, 'captured_at' => now()->startOfDay()->addHours(4)]);

        $this->runJob($user->id, 'LONG');

        $user->refresh();
        $this->assertTrue($user->bot_active);
        $this->assertDatabaseMissing('bot_activities', [
            'user_id' => $user->id,
            'type' => 'risk_protection',
        ]);
    }

    // ─── Gatekeeper B: Capital protegido ─────────────────────────────────

    public function test_protected_capital_pauses_bot(): void
    {
        Event::fake([BotStatusUpdated::class]);

        $user = User::factory()->create([
            'bot_active' => true,
            'bot_mode' => 'simulation',
            'risk_level' => 'balanceado',
            'estimated_capital' => 1000,
        ]);

        // Snapshot de ayer por debajo del 80% del capital protegido (700 < 800).
        // Al ser de ayer, no dispara el gatekeeper de stop-loss diario.
        $user->balanceSnapshots()->create([
            'balance' => 700,
            'captured_at' => Carbon::yesterday()->setHour(12),
        ]);

        $this->runJob($user->id, 'LONG');

        $user->refresh();
        $this->assertFalse($user->bot_active);
        $this->assertDatabaseHas('bot_activities', [
            'user_id' => $user->id,
            'type' => 'risk_protection',
            'action' => 'stop_loss_trigger',
            'risk_alert' => true,
        ]);

        Event::assertDispatched(BotStatusUpdated::class);
    }

    public function test_real_mode_risk_protection_closes_binance_positions(): void
    {
        Event::fake([BotStatusUpdated::class]);

        $user = User::factory()->create([
            'bot_active' => true,
            'bot_mode' => 'real',
            'binance_api_key' => 'key',
            'binance_secret_key' => 'secret',
            'binance_verified' => true,
            'risk_level' => 'balanceado',
            'estimated_capital' => 1000,
        ]);

        $user->balanceSnapshots()->create([
            'balance' => 700,
            'captured_at' => Carbon::yesterday()->setHour(12),
        ]);

        // La protección de emergencia debe cerrar posiciones en Binance.
        $this->broker->shouldReceive('closeOpenPositions')
            ->once()
            ->with($user->binance_api_key, $user->binance_secret_key)
            ->andReturn(true);

        $this->runJob($user->id, 'LONG');

        $user->refresh();
        $this->assertFalse($user->bot_active);
    }

    // ─── Ejecución en modo simulación ────────────────────────────────────

    public function test_simulation_close_books_profit_and_updates_balance(): void
    {
        Event::fake([BalanceUpdated::class]);

        $user = User::factory()->create([
            'bot_active' => true,
            'bot_mode' => 'simulation',
            'risk_level' => 'balanceado',
            'estimated_capital' => 1000,
        ]);

        $this->runJob($user->id, 'CLOSE');

        // Beneficio simulado del 1.5% sobre el capital base (1000 → +15 → 1015).
        $this->assertDatabaseHas('bot_activities', [
            'user_id' => $user->id,
            'type' => 'close',
            'action' => 'close_profit',
            'profit_percentage' => 1.5,
            'profit_value' => 15.00,
            'risk_alert' => false,
        ]);

        $this->assertDatabaseHas('balance_snapshots', [
            'user_id' => $user->id,
            'balance' => 1015.00,
        ]);

        $this->assertSame('CLOSE', Cache::get("user:{$user->id}:simulation_position"));
        Event::assertDispatched(BalanceUpdated::class);
    }

    public function test_simulation_long_records_activity_without_snapshot(): void
    {
        Event::fake([BalanceUpdated::class]);

        $user = User::factory()->create([
            'bot_active' => true,
            'bot_mode' => 'simulation',
            'risk_level' => 'balanceado',
            'estimated_capital' => 1000,
        ]);

        $this->runJob($user->id, 'LONG');

        $this->assertDatabaseHas('bot_activities', [
            'user_id' => $user->id,
            'type' => 'long',
            'action' => 'open_long',
            'risk_alert' => false,
        ]);

        // Abrir posición no genera snapshot de balance ni evento.
        $this->assertSame(0, $user->balanceSnapshots()->count());
        $this->assertSame('LONG', Cache::get("user:{$user->id}:simulation_position"));
        Event::assertNotDispatched(BalanceUpdated::class);
    }

    // ─── Ejecución en modo real ──────────────────────────────────────────

    public function test_real_mode_adjusts_position_and_syncs_balance(): void
    {
        Event::fake([BalanceUpdated::class]);

        $user = User::factory()->create([
            'bot_active' => true,
            'bot_mode' => 'real',
            'binance_api_key' => 'key',
            'binance_secret_key' => 'secret',
            'binance_verified' => true,
            'risk_level' => 'balanceado',
            'estimated_capital' => 1000,
        ]);

        $this->broker->shouldReceive('adjustPosition')
            ->once()
            ->with($user->binance_api_key, $user->binance_secret_key, 'LONG')
            ->andReturn(true);
        $this->broker->shouldReceive('getTotalBalance')
            ->once()
            ->with($user->binance_api_key, $user->binance_secret_key)
            ->andReturn(1050.00);

        $this->runJob($user->id, 'LONG');

        $this->assertDatabaseHas('bot_activities', [
            'user_id' => $user->id,
            'type' => 'long',
            'action' => 'open_long',
        ]);
        $this->assertDatabaseHas('balance_snapshots', [
            'user_id' => $user->id,
            'balance' => 1050.00,
        ]);
        $this->assertSame('LONG', Cache::get("user:{$user->id}:real_position"));
        Event::assertDispatched(BalanceUpdated::class);
    }

    public function test_real_mode_aborts_when_binance_not_linked(): void
    {
        $user = User::factory()->create([
            'bot_active' => true,
            'bot_mode' => 'real',
            'binance_verified' => false,
            'risk_level' => 'balanceado',
            'estimated_capital' => 1000,
        ]);

        // El broker no debe recibir ninguna llamada.
        $this->broker->shouldNotReceive('adjustPosition');

        $this->runJob($user->id, 'LONG');

        $this->assertSame(0, $user->botActivities()->count());
    }

    public function test_real_mode_swallows_broker_errors_without_side_effects(): void
    {
        Event::fake([BalanceUpdated::class]);

        $user = User::factory()->create([
            'bot_active' => true,
            'bot_mode' => 'real',
            'binance_api_key' => 'key',
            'binance_secret_key' => 'secret',
            'binance_verified' => true,
            'risk_level' => 'balanceado',
            'estimated_capital' => 1000,
        ]);

        $this->broker->shouldReceive('adjustPosition')
            ->once()
            ->andThrow(new \RuntimeException('Binance caído'));

        // No debe propagar la excepción ni dejar estado a medias.
        $this->runJob($user->id, 'LONG');

        $this->assertSame(0, $user->botActivities()->count());
        $this->assertSame(0, $user->balanceSnapshots()->count());
        Event::assertNotDispatched(BalanceUpdated::class);
    }
}
