<?php

namespace Tests\Unit;

use App\Core\Contracts\BinanceBrokerInterface;
use App\Core\Contracts\HyperliquidBrokerInterface;
use App\Core\Exceptions\HyperliquidInvalidCredentialsException;
use App\Core\Trading\BrokerResolver;
use App\Jobs\AdjustPositionJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * El motor de ejecución (AdjustPositionJob) despacha al broker del canal de
 * ejecución elegido por el usuario, con las credenciales de ese canal.
 */
class AdjustPositionJobChannelTest extends TestCase
{
    use RefreshDatabase;

    /** @var MockInterface&HyperliquidBrokerInterface */
    protected $hyperliquidBroker;

    /** @var MockInterface&BinanceBrokerInterface */
    protected $binanceBroker;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.hyperliquid.mock' => false]);

        $this->hyperliquidBroker = Mockery::mock(HyperliquidBrokerInterface::class);
        $this->app->instance(HyperliquidBrokerInterface::class, $this->hyperliquidBroker);

        $this->binanceBroker = Mockery::mock(BinanceBrokerInterface::class);
        $this->app->instance(BinanceBrokerInterface::class, $this->binanceBroker);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function hyperliquidUser(): User
    {
        return User::factory()->create([
            'bot_active' => true,
            'bot_mode' => 'real',
            'risk_level' => 'balanceado',
            'estimated_capital' => 1000,
            'trading_channel' => User::CHANNEL_HYPERLIQUID,
            'hyperliquid_wallet_address' => '0xwallet',
            'hyperliquid_agent_key' => '0xagentkey',
            'hyperliquid_verified' => true,
        ]);
    }

    private function runJob(int $userId, string $position): void
    {
        (new AdjustPositionJob($userId, $position))->handle(app(BrokerResolver::class));
    }

    public function test_hyperliquid_channel_dispatches_to_hyperliquid_broker_with_wallet_credentials(): void
    {
        $user = $this->hyperliquidUser();

        $this->hyperliquidBroker->shouldReceive('adjustPosition')
            ->once()
            ->withArgs(fn ($wallet, $agentKey, $position, $riskLevel) => $wallet === '0xwallet'
                && $agentKey === '0xagentkey'
                && $position === 'LONG'
                && $riskLevel === 'balanceado')
            ->andReturnTrue();

        // El broker de Binance no debe recibir ninguna llamada.
        $this->binanceBroker->shouldNotReceive('adjustPosition');

        $this->runJob($user->id, 'LONG');

        $this->assertSame('LONG', Cache::get("user:{$user->id}:real_position"));
        $this->assertDatabaseHas('bot_activities', [
            'user_id' => $user->id,
            'bot_mode' => 'real',
            'action' => 'open_long',
        ]);
    }

    public function test_hyperliquid_channel_requires_hyperliquid_link_even_with_binance_linked(): void
    {
        $user = User::factory()->create([
            'bot_active' => true,
            'bot_mode' => 'real',
            'trading_channel' => User::CHANNEL_HYPERLIQUID,
            // Binance vinculado, pero el canal activo (Hyperliquid) no lo está.
            'binance_api_key' => 'my_secure_api_key',
            'binance_secret_key' => 'my_secure_secret_key',
            'binance_verified' => true,
        ]);

        $this->hyperliquidBroker->shouldNotReceive('adjustPosition');
        $this->binanceBroker->shouldNotReceive('adjustPosition');

        $this->runJob($user->id, 'LONG');

        $this->assertDatabaseCount('bot_activities', 0);
    }

    public function test_invalid_hyperliquid_credentials_pause_the_bot(): void
    {
        $user = $this->hyperliquidUser();

        $this->hyperliquidBroker->shouldReceive('adjustPosition')
            ->once()
            ->andThrow(new HyperliquidInvalidCredentialsException);

        $this->runJob($user->id, 'LONG');

        $this->assertFalse($user->fresh()->bot_active, 'Credenciales inválidas del canal deben pausar el bot.');
        $this->assertSame('CLOSE', Cache::get("user:{$user->id}:real_position"));
    }
}
