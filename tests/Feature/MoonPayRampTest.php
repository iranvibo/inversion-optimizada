<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rampa fiat ↔ USDC (widget de MoonPay): guardas de acceso y renderizado.
 */
class MoonPayRampTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.moonpay.api_key' => 'pk_test_key',
            'services.moonpay.secret_key' => 'sk_test_secret',
        ]);
    }

    private function linkedUser(): User
    {
        return User::factory()->create([
            'hyperliquid_wallet_address' => '0xabc123def456abc123def456abc123def456abcd',
            'hyperliquid_agent_key' => 'agent_private_key',
            'hyperliquid_verified' => true,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('moonpay.buy'))->assertRedirect(route('login'));
        $this->get(route('moonpay.sell'))->assertRedirect(route('login'));
    }

    public function test_user_without_hyperliquid_wallet_is_sent_to_link_screen(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('moonpay.buy'))
            ->assertRedirect(route('hyperliquid.link'))
            ->assertSessionHas('error');
    }

    public function test_buy_page_embeds_signed_widget_for_linked_user(): void
    {
        $response = $this->actingAs($this->linkedUser())->get(route('moonpay.buy'));

        $response->assertOk()
            ->assertSee('Añadir fondos')
            ->assertSee('https://buy.moonpay.com', false)
            ->assertSee('walletAddress=0xabc123def456abc123def456abc123def456abcd', false)
            ->assertSee('signature=', false);
    }

    public function test_sell_page_embeds_signed_widget_for_linked_user(): void
    {
        $response = $this->actingAs($this->linkedUser())->get(route('moonpay.sell'));

        $response->assertOk()
            ->assertSee('Retirar fondos')
            ->assertSee('https://sell.moonpay.com', false)
            ->assertSee('refundWalletAddress=0xabc123def456abc123def456abc123def456abcd', false);
    }

    public function test_page_degrades_gracefully_when_moonpay_is_not_configured(): void
    {
        config(['services.moonpay.api_key' => null, 'services.moonpay.secret_key' => null]);

        $response = $this->actingAs($this->linkedUser())->get(route('moonpay.buy'));

        $response->assertOk()
            ->assertSee('Servicio no disponible temporalmente')
            ->assertDontSee('<iframe', false);
    }

    public function test_funds_tab_offers_ramp_when_hyperliquid_wallet_is_linked(): void
    {
        // Con wallet vinculada la pestaña Fondos ofrece comprar y vender.
        $this->actingAs($this->linkedUser())
            ->get(route('dashboard'))
            ->assertSee('Fondos')
            ->assertSee(route('moonpay.buy'), false)
            ->assertSee(route('moonpay.sell'), false);
    }

    public function test_funds_tab_guides_to_wallet_linking_when_not_linked(): void
    {
        // Sin wallet (p. ej. usuario solo de Binance) la pestaña guía a vincular.
        $binanceUser = User::factory()->create([
            'binance_api_key' => 'valid_key',
            'binance_secret_key' => 'valid_secret',
            'binance_verified' => true,
        ]);

        $this->actingAs($binanceUser)
            ->get(route('dashboard'))
            ->assertDontSee(route('moonpay.buy'), false)
            ->assertSee('Vincula tu wallet para gestionar fondos')
            ->assertSee(route('hyperliquid.link'), false);
    }
}
