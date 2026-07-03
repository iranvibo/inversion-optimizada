<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\MoonPayService;
use Tests\TestCase;

/**
 * Construcción y firma de las URLs del widget de MoonPay. La firma es la
 * garantía de que la wallet de destino no puede manipularse en el cliente:
 * MoonPay la verifica en su servidor con la misma secret key.
 */
class MoonPayServiceTest extends TestCase
{
    private const SECRET_KEY = 'sk_test_secret';

    private MoonPayService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.moonpay.api_key' => 'pk_test_key',
            'services.moonpay.secret_key' => self::SECRET_KEY,
        ]);

        $this->service = new MoonPayService;
    }

    private function user(): User
    {
        return User::factory()->make([
            'id' => 7,
            'hyperliquid_wallet_address' => '0xabc123def456abc123def456abc123def456abcd',
        ]);
    }

    /**
     * Separa la query sin firmar y la firma de una URL generada.
     *
     * @return array{string, string, string}
     */
    private function splitSignedUrl(string $url): array
    {
        [$base, $query] = explode('?', $url, 2);
        $position = strrpos($query, '&signature=');
        $this->assertNotFalse($position, 'La URL debe incluir el parámetro signature.');

        $unsignedQuery = substr($query, 0, $position);
        $signature = rawurldecode(substr($query, $position + strlen('&signature=')));

        return [$base, $unsignedQuery, $signature];
    }

    public function test_buy_url_targets_user_wallet_with_valid_signature(): void
    {
        $url = $this->service->buyUrlFor($this->user());

        [$base, $unsignedQuery, $signature] = $this->splitSignedUrl($url);
        parse_str($unsignedQuery, $params);

        $this->assertSame('https://buy.moonpay.com', $base);
        $this->assertSame('pk_test_key', $params['apiKey']);
        $this->assertSame('usdc_arbitrum', $params['currencyCode']);
        $this->assertSame('0xabc123def456abc123def456abc123def456abcd', $params['walletAddress']);
        $this->assertSame('eur', $params['baseCurrencyCode']);
        $this->assertSame('es', $params['language']);
        $this->assertSame('7', $params['externalCustomerId']);

        // Firma HMAC-SHA256 sobre la query string (incluida la '?'), en base64.
        $expected = base64_encode(hash_hmac('sha256', '?'.$unsignedQuery, self::SECRET_KEY, true));
        $this->assertSame($expected, $signature);
    }

    public function test_sell_url_uses_wallet_as_refund_address_with_valid_signature(): void
    {
        $url = $this->service->sellUrlFor($this->user());

        [$base, $unsignedQuery, $signature] = $this->splitSignedUrl($url);
        parse_str($unsignedQuery, $params);

        $this->assertSame('https://sell.moonpay.com', $base);
        $this->assertSame('usdc_arbitrum', $params['baseCurrencyCode']);
        $this->assertSame('eur', $params['quoteCurrencyCode']);
        $this->assertSame('0xabc123def456abc123def456abc123def456abcd', $params['refundWalletAddress']);

        $expected = base64_encode(hash_hmac('sha256', '?'.$unsignedQuery, self::SECRET_KEY, true));
        $this->assertSame($expected, $signature);
    }

    public function test_is_not_configured_without_keys(): void
    {
        $this->assertTrue($this->service->isConfigured());

        config(['services.moonpay.secret_key' => null]);
        $this->assertFalse($this->service->isConfigured());

        config(['services.moonpay.secret_key' => self::SECRET_KEY, 'services.moonpay.api_key' => null]);
        $this->assertFalse($this->service->isConfigured());
    }
}
