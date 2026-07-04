<?php

namespace Tests\Unit;

use App\Core\Contracts\BinanceBrokerInterface;
use App\Core\Trading\BrokerResolver;
use App\Core\Trading\PositionReconciler;
use App\Events\BalanceUpdated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

/**
 * Pruebas unitarias para el reconciliador de posiciones (US06).
 */
class PositionReconcilerTest extends TestCase
{
    use RefreshDatabase;

    /** @var \Mockery\MockInterface&BinanceBrokerInterface */
    protected $broker;
    protected BrokerResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.binance.mock' => false]);

        $this->broker = Mockery::mock(BinanceBrokerInterface::class);
        $this->app->instance(BinanceBrokerInterface::class, $this->broker);
        
        $this->resolver = app(BrokerResolver::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_reconcile_does_nothing_when_bot_mode_is_simulation(): void
    {
        $user = User::factory()->create([
            'bot_mode' => 'simulation',
            'binance_verified' => true,
        ]);

        $reconciler = new PositionReconciler($this->resolver);
        $result = $reconciler->reconcile($user);

        $this->assertFalse($result);
        $this->assertSame(0, $user->botActivities()->count());
    }

    public function test_reconcile_does_nothing_when_broker_not_linked(): void
    {
        $user = User::factory()->create([
            'bot_mode' => 'real',
            'binance_verified' => false,
        ]);

        $reconciler = new PositionReconciler($this->resolver);
        $result = $reconciler->reconcile($user);

        $this->assertFalse($result);
        $this->assertSame(0, $user->botActivities()->count());
    }

    public function test_reconcile_updates_cache_when_mismatch_detected_but_no_closure(): void
    {
        $user = User::factory()->create([
            'bot_active' => true,
            'bot_mode' => 'real',
            'binance_api_key' => 'key',
            'binance_secret_key' => 'secret',
            'binance_verified' => true,
        ]);

        Cache::put("user:{$user->id}:real_position", 'CLOSE');

        $this->broker->shouldReceive('getOpenPosition')
            ->once()
            ->with($user->binance_api_key, $user->binance_secret_key)
            ->andReturn('LONG');

        $reconciler = new PositionReconciler($this->resolver);
        $result = $reconciler->reconcile($user);

        // Debería retornar false porque no fue un cierre (fue una apertura)
        $this->assertFalse($result);
        $this->assertSame('LONG', Cache::get("user:{$user->id}:real_position"));
        $this->assertSame(0, $user->botActivities()->count());
    }

    public function test_reconcile_detects_closure_records_activity_and_creates_snapshot(): void
    {
        Event::fake([BalanceUpdated::class]);

        $user = User::factory()->create([
            'bot_active' => true,
            'bot_mode' => 'real',
            'binance_api_key' => 'key',
            'binance_secret_key' => 'secret',
            'binance_verified' => true,
            'risk_level' => 'balanceado',
        ]);

        Cache::put("user:{$user->id}:real_position", 'LONG');
        Cache::put("user:{$user->id}:open_capital", 1000.0);

        // El exchange reporta CLOSE
        $this->broker->shouldReceive('getOpenPosition')
            ->once()
            ->with($user->binance_api_key, $user->binance_secret_key)
            ->andReturn('CLOSE');

        $this->broker->shouldReceive('getTotalBalance')
            ->once()
            ->with($user->binance_api_key, $user->binance_secret_key)
            ->andReturn(1100.0); // Beneficio de +100$ (+10%)

        $reconciler = new PositionReconciler($this->resolver);
        $result = $reconciler->reconcile($user);

        $this->assertTrue($result);
        
        // Verifica la caché
        $this->assertSame('CLOSE', Cache::get("user:{$user->id}:real_position"));

        // Verifica que se haya registrado la actividad de cierre con PnL correcto
        $this->assertDatabaseHas('bot_activities', [
            'user_id' => $user->id,
            'bot_mode' => 'real',
            'type' => 'close',
            'action' => 'close_profit',
            'profit_value' => 100.0,
            'profit_percentage' => 10.0,
        ]);

        // Verifica que se haya creado el snapshot
        $this->assertDatabaseHas('balance_snapshots', [
            'user_id' => $user->id,
            'balance' => 1100.0,
            'trading_channel' => 'binance',
        ]);

        Event::assertDispatched(BalanceUpdated::class);
    }
}
