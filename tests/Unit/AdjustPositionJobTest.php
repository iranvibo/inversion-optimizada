<?php

namespace Tests\Unit;

use App\Core\Contracts\BinanceBrokerInterface;
use App\Events\BalanceUpdated;
use App\Events\BotStatusUpdated;
use App\Jobs\AdjustPositionJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

/**
 * Pruebas unitarias del trabajo de ajuste de posición (US06).
 *
 * Cubre la ejecución del ajuste tanto en modo simulación como en modo real.
 * El bot no toma decisiones de trading: solo replica la señal externa, por lo
 * que no existe ningún gatekeeper de riesgo local que lo pause por su cuenta.
 */
class AdjustPositionJobTest extends TestCase
{
    use RefreshDatabase;

    /** @var \Mockery\MockInterface&BinanceBrokerInterface */
    protected $broker;

    protected function setUp(): void
    {
        parent::setUp();

        // Contexto real: el broker se intercepta con un doble (sin red), así que
        // mock=false permite ejercitar la persistencia del histórico real.
        config(['services.binance.mock' => false]);

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

    // ─── El bot no se autopausa por riesgo ───────────────────────────────

    public function test_does_not_pause_bot_when_balance_falls(): void
    {
        Event::fake([BotStatusUpdated::class, BalanceUpdated::class]);

        $user = User::factory()->create([
            'bot_active' => true,
            'bot_mode' => 'simulation',
            'risk_level' => 'balanceado',
            'estimated_capital' => 1000,
        ]);

        // Aunque el balance caiga fuertemente respecto al capital estimado y al
        // primer snapshot del día, el bot NO debe pausarse ni registrar ninguna
        // protección de riesgo: este proyecto no toma decisiones de trading.
        $user->balanceSnapshots()->create(['balance' => 1000, 'captured_at' => now()->startOfDay()->addHours(1)]);
        $user->balanceSnapshots()->create(['balance' => 400, 'captured_at' => now()->startOfDay()->addHours(4)]);

        $this->runJob($user->id, 'LONG');

        $user->refresh();
        $this->assertTrue($user->bot_active, 'El bot no debe pausarse por reglas de riesgo locales.');
        $this->assertDatabaseMissing('bot_activities', [
            'user_id' => $user->id,
            'type' => 'risk_protection',
        ]);

        // El ajuste normal sí se ejecuta (se abre la posición solicitada).
        $this->assertDatabaseHas('bot_activities', [
            'user_id' => $user->id,
            'type' => 'long',
            'action' => 'open_long',
        ]);
        $this->assertSame('LONG', Cache::get("user:{$user->id}:simulation_position"));
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

        // Abrir posición no genera snapshot de balance pero sí despacha el evento para actualizar la UI.
        $this->assertSame(0, $user->balanceSnapshots()->count());
        $this->assertSame('LONG', Cache::get("user:{$user->id}:simulation_position"));
        Event::assertDispatched(BalanceUpdated::class);
    }

    public function test_real_mode_pauses_bot_on_invalid_credentials(): void
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

        $this->broker->shouldReceive('adjustPosition')
            ->once()
            ->andThrow(new \App\Core\Exceptions\BinanceInvalidCredentialsException());

        $this->runJob($user->id, 'LONG');

        $user->refresh();
        $this->assertFalse($user->bot_active, 'El bot debe pausarse si las credenciales son inválidas.');
        $this->assertSame('CLOSE', Cache::get("user:{$user->id}:real_position"));
        $this->assertSame('CLOSE', Cache::get("user:{$user->id}:simulation_position"));
        Event::assertDispatched(BotStatusUpdated::class);
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
            ->with($user->binance_api_key, $user->binance_secret_key, 'LONG', 'balanceado')
            ->andReturn(true);

        $this->runJob($user->id, 'LONG');

        $this->assertDatabaseHas('bot_activities', [
            'user_id' => $user->id,
            'type' => 'long',
            'action' => 'open_long',
        ]);
        $this->assertDatabaseHas('balance_snapshots', [
            'user_id' => $user->id,
            'balance' => 1000.00,
        ]);
        $this->assertSame('LONG', Cache::get("user:{$user->id}:real_position"));
        Event::assertDispatched(BalanceUpdated::class);
    }

    public function test_real_mode_passes_user_risk_level_to_broker(): void
    {
        Event::fake([BalanceUpdated::class]);

        $user = User::factory()->create([
            'bot_active' => true,
            'bot_mode' => 'real',
            'binance_api_key' => 'key',
            'binance_secret_key' => 'secret',
            'binance_verified' => true,
            'risk_level' => 'agresivo',
            'estimated_capital' => 1000,
        ]);

        $this->broker->shouldReceive('adjustPosition')
            ->once()
            ->with($user->binance_api_key, $user->binance_secret_key, 'SHORT', 'agresivo')
            ->andReturn(true);

        $this->runJob($user->id, 'SHORT');

        $this->assertDatabaseHas('bot_activities', [
            'user_id' => $user->id,
            'type' => 'short',
            'action' => 'open_short',
        ]);
    }

    public function test_real_mode_idempotent_no_op_skips_activity_and_balance_sync(): void
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

        // La posición objetivo ya está abierta en Binance: el broker devuelve false.
        $this->broker->shouldReceive('adjustPosition')
            ->once()
            ->with($user->binance_api_key, $user->binance_secret_key, 'LONG', 'balanceado')
            ->andReturn(false);
        // No debe sincronizar balance si no hubo cambio.
        $this->broker->shouldNotReceive('getTotalBalance');

        $this->runJob($user->id, 'LONG');

        $this->assertSame(0, $user->botActivities()->count());
        $this->assertSame(0, $user->balanceSnapshots()->count());
        // La caché de posición sí refleja el estado objetivo.
        $this->assertSame('LONG', Cache::get("user:{$user->id}:real_position"));
        Event::assertNotDispatched(BalanceUpdated::class);
    }

    public function test_real_mode_close_books_real_profit_from_open_and_close_capital(): void
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

        // La posición se abrió con un capital (equity) de 1000.
        Cache::put("user:{$user->id}:open_capital", 1000.00);

        $this->broker->shouldReceive('adjustPosition')
            ->once()
            ->with($user->binance_api_key, $user->binance_secret_key, 'CLOSE', 'balanceado')
            ->andReturn(true);
        // Al cerrar, el equity real es 1080 → beneficio real de +80 (+8%).
        $this->broker->shouldReceive('getTotalBalance')
            ->once()
            ->andReturn(1080.00);

        $this->runJob($user->id, 'CLOSE');

        $this->assertDatabaseHas('bot_activities', [
            'user_id' => $user->id,
            'type' => 'close',
            'action' => 'close_profit',
            'profit_percentage' => 8.00,
            'profit_value' => 80.00,
        ]);
        $this->assertDatabaseHas('balance_snapshots', [
            'user_id' => $user->id,
            'balance' => 1080.00,
        ]);
        // El capital de apertura se consume tras cerrar.
        $this->assertNull(Cache::get("user:{$user->id}:open_capital"));
        Event::assertDispatched(BalanceUpdated::class);
    }

    public function test_real_mode_close_books_real_loss_when_capital_drops(): void
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

        // Capital de apertura por encima del de cierre → pérdida real.
        Cache::put("user:{$user->id}:open_capital", 1000.00);

        $this->broker->shouldReceive('adjustPosition')
            ->once()
            ->with($user->binance_api_key, $user->binance_secret_key, 'CLOSE', 'balanceado')
            ->andReturn(true);
        // Equity real al cerrar 950 → pérdida de -50 (-5%).
        $this->broker->shouldReceive('getTotalBalance')->once()->andReturn(950.00);

        $this->runJob($user->id, 'CLOSE');

        $this->assertDatabaseHas('bot_activities', [
            'user_id' => $user->id,
            'type' => 'close',
            'action' => 'close_loss',
            'profit_percentage' => -5.00,
            'profit_value' => -50.00,
        ]);
    }

    public function test_real_mode_open_records_opening_capital_for_later_close(): void
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
            ->with($user->binance_api_key, $user->binance_secret_key, 'LONG', 'balanceado')
            ->andReturn(true);

        $this->runJob($user->id, 'LONG');

        // Al abrir, el equity real queda guardado como capital de apertura.
        $this->assertSame(1000.00, Cache::get("user:{$user->id}:open_capital"));
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
