<?php

namespace Tests\Feature;

use App\Core\Contracts\BinanceBrokerInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

/**
 * Vinculación del canal Hyperliquid y cambio de canal de ejecución.
 * El broker de Hyperliquid opera en modo mock (HYPERLIQUID_MOCK=true).
 */
class HyperliquidConnectionTest extends TestCase
{
    use RefreshDatabase;

    private const AUTHORIZED_EMAIL = 'vicenteiran@gmail.com';

    private function authorizedUser(array $attributes = []): User
    {
        return User::factory()->create([
            'email' => self::AUTHORIZED_EMAIL,
            ...$attributes,
        ]);
    }

    // ─── Vinculación ─────────────────────────────────────────────────────────

    public function test_unauthorized_email_cannot_link_hyperliquid(): void
    {
        $user = User::factory()->create(['email' => 'otro@example.com']);

        $response = $this->actingAs($user)->post(route('hyperliquid.store'), [
            'hyperliquid_wallet_address' => 'my_wallet',
            'hyperliquid_agent_key' => 'agent_key',
        ]);

        $response->assertSessionHasErrors('hyperliquid_wallet_address');
        $this->assertFalse($user->fresh()->isHyperliquidLinked());
    }

    public function test_authorized_user_links_hyperliquid_with_agent_credentials(): void
    {
        $user = $this->authorizedUser();

        $response = $this->actingAs($user)->post(route('hyperliquid.store'), [
            'hyperliquid_wallet_address' => 'my_wallet',
            'hyperliquid_agent_key' => 'agent_private_key',
        ]);

        $response->assertRedirect(route('dashboard'));
        $user->refresh();
        $this->assertTrue($user->isHyperliquidLinked());
        $this->assertSame('my_wallet', $user->hyperliquid_wallet_address);
    }

    public function test_master_private_key_is_rejected_with_security_alert(): void
    {
        $user = $this->authorizedUser();

        $response = $this->actingAs($user)->post(route('hyperliquid.store'), [
            'hyperliquid_wallet_address' => 'my_wallet',
            'hyperliquid_agent_key' => 'master_private_key',
        ]);

        $response->assertSessionHas('withdrawal_error');
        $this->assertFalse($user->fresh()->isHyperliquidLinked());
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = $this->authorizedUser();

        $response = $this->actingAs($user)->post(route('hyperliquid.store'), [
            'hyperliquid_wallet_address' => 'invalid_wallet',
            'hyperliquid_agent_key' => 'agent_key',
        ]);

        $response->assertSessionHasErrors('hyperliquid_wallet_address');
        $this->assertFalse($user->fresh()->isHyperliquidLinked());
    }

    // ─── Desvinculación ──────────────────────────────────────────────────────

    public function test_disconnecting_active_hyperliquid_channel_pauses_bot_and_falls_back_to_binance(): void
    {
        $user = $this->authorizedUser([
            'hyperliquid_wallet_address' => 'my_wallet',
            'hyperliquid_agent_key' => 'agent_key',
            'hyperliquid_verified' => true,
            'trading_channel' => User::CHANNEL_HYPERLIQUID,
            'bot_active' => true,
            'bot_mode' => 'real',
        ]);

        $this->actingAs($user)->post(route('hyperliquid.disconnect'))->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->isHyperliquidLinked());
        $this->assertSame(User::CHANNEL_BINANCE, $user->tradingChannel());
        $this->assertFalse($user->bot_active);
        $this->assertSame('simulation', $user->bot_mode);
    }

    public function test_disconnecting_inactive_hyperliquid_keeps_binance_channel_running(): void
    {
        $user = $this->authorizedUser([
            'binance_api_key' => 'my_secure_api_key',
            'binance_secret_key' => 'my_secure_secret_key',
            'binance_verified' => true,
            'hyperliquid_wallet_address' => 'my_wallet',
            'hyperliquid_agent_key' => 'agent_key',
            'hyperliquid_verified' => true,
            'trading_channel' => User::CHANNEL_BINANCE,
            'bot_active' => true,
        ]);

        $this->actingAs($user)->post(route('hyperliquid.disconnect'));

        $user->refresh();
        $this->assertFalse($user->isHyperliquidLinked());
        $this->assertTrue($user->bot_active, 'Desvincular un canal inactivo no debe pausar el bot.');
        $this->assertSame(User::CHANNEL_BINANCE, $user->tradingChannel());
    }

    // ─── Cambio de canal de ejecución ────────────────────────────────────────

    public function test_switching_to_an_unlinked_channel_is_blocked(): void
    {
        $user = $this->authorizedUser();

        $response = $this->actingAs($user)->post(route('bot.switch-channel'), [
            'trading_channel' => 'hyperliquid',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(User::CHANNEL_BINANCE, $user->fresh()->tradingChannel());
    }

    public function test_switching_channel_updates_the_user_channel(): void
    {
        $user = $this->authorizedUser([
            'hyperliquid_wallet_address' => 'my_wallet',
            'hyperliquid_agent_key' => 'agent_key',
            'hyperliquid_verified' => true,
        ]);

        $response = $this->actingAs($user)->post(route('bot.switch-channel'), [
            'trading_channel' => 'hyperliquid',
        ]);

        $response->assertSessionHas('success');
        $this->assertSame(User::CHANNEL_HYPERLIQUID, $user->fresh()->tradingChannel());
    }

    public function test_switching_channel_in_real_mode_closes_positions_on_the_previous_channel(): void
    {
        $user = $this->authorizedUser([
            'binance_api_key' => 'my_secure_api_key',
            'binance_secret_key' => 'my_secure_secret_key',
            'binance_verified' => true,
            'hyperliquid_wallet_address' => 'my_wallet',
            'hyperliquid_agent_key' => 'agent_key',
            'hyperliquid_verified' => true,
            'trading_channel' => User::CHANNEL_BINANCE,
            'bot_mode' => 'real',
            'bot_active' => false,
        ]);

        Cache::put("user:{$user->id}:real_position", 'LONG');

        // El cierre preventivo debe ejecutarse en el canal ANTERIOR (Binance).
        $binanceBroker = Mockery::mock(BinanceBrokerInterface::class);
        $binanceBroker->shouldReceive('closeOpenPositions')
            ->once()
            ->withArgs(fn ($key, $secret) => $key === 'my_secure_api_key' && $secret === 'my_secure_secret_key')
            ->andReturnTrue();
        $this->app->instance(BinanceBrokerInterface::class, $binanceBroker);

        $this->actingAs($user)->post(route('bot.switch-channel'), [
            'trading_channel' => 'hyperliquid',
        ]);

        $this->assertSame(User::CHANNEL_HYPERLIQUID, $user->fresh()->tradingChannel());
        $this->assertSame('CLOSE', Cache::get("user:{$user->id}:real_position"));
    }
}
