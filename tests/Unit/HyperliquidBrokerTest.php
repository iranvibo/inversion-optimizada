<?php

namespace Tests\Unit;

use App\Core\Contracts\HyperliquidBrokerInterface;
use App\Core\Exceptions\HyperliquidException;
use App\Core\Exceptions\HyperliquidInvalidCredentialsException;
use App\Infrastructure\Hyperliquid\HyperliquidBroker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Cubre el canal de ejecución de Hyperliquid: reglas de gestión de posición y
 * dimensionamiento en mock, y el path real (sin red) con Http::fake verificando
 * los endpoints y el formato de las órdenes firmadas.
 *
 * OJO: un solo Http::fake por test (los stubs se acumulan entre fakes).
 */
class HyperliquidBrokerTest extends TestCase
{
    private const WALLET = '0x1234567890abcdef1234567890abcdef12345678';

    // Clave de pruebas pública (primera cuenta de Hardhat); su dirección es
    // 0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266, distinta de self::WALLET.
    private const AGENT_KEY = '0xac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80';

    protected HyperliquidBrokerInterface $broker;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.hyperliquid.mock' => true]);

        $this->broker = $this->app->make(HyperliquidBrokerInterface::class);
    }

    /**
     * Configura el path real contra un host falso interceptado por Http::fake.
     */
    private function useRealDriver(): void
    {
        // Ninguna petición debe escaparse a la red real durante los tests.
        Http::preventStrayRequests();

        config([
            'services.hyperliquid.mock' => false,
            'services.hyperliquid.api_url' => 'https://hyperliquid.test',
            'services.hyperliquid.is_mainnet' => true,
            'services.hyperliquid.coin' => 'BTC',
            'services.hyperliquid.leverage' => 1,
            'services.hyperliquid.slippage' => 0.05,
        ]);
    }

    /**
     * Respuesta estándar de /info por tipo de consulta, con una posición y
     * saldos parametrizables.
     */
    private function fakeInfoResponses(float $szi = 0.0, float $accountValue = 1000.0, float $withdrawable = 1000.0): \Closure
    {
        return function ($request) use ($szi, $accountValue, $withdrawable) {
            if (! str_ends_with($request->url(), '/info')) {
                return null;
            }

            return match ($request['type']) {
                'clearinghouseState' => Http::response([
                    'marginSummary' => ['accountValue' => (string) $accountValue],
                    'withdrawable' => (string) $withdrawable,
                    'assetPositions' => $szi === 0.0 ? [] : [[
                        'type' => 'oneWay',
                        'position' => ['coin' => 'BTC', 'szi' => (string) $szi],
                    ]],
                ]),
                'allMids' => Http::response(['BTC' => '50000.0']),
                'meta' => Http::response(['universe' => [['name' => 'BTC', 'szDecimals' => 5, 'maxLeverage' => 40]]]),
                'openOrders' => Http::response([]),
                'extraAgents' => Http::response([['address' => '0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266', 'name' => 'vibo']]),
                default => Http::response([]),
            };
        };
    }

    // ─── Path mock: credenciales y reglas de seguridad ───────────────────────

    public function test_mock_throws_exception_on_invalid_credentials(): void
    {
        $this->expectException(HyperliquidInvalidCredentialsException::class);

        $this->broker->checkApiRestrictions('invalid_wallet', 'some_key');
    }

    public function test_mock_detects_master_key_as_withdrawal_capable(): void
    {
        $restrictions = $this->broker->checkApiRestrictions('my_wallet', 'master_private_key');

        $this->assertTrue($restrictions['enableWithdrawals']);
    }

    public function test_mock_passes_with_agent_credentials(): void
    {
        $restrictions = $this->broker->checkApiRestrictions('my_wallet', 'agent_private_key');

        $this->assertFalse($restrictions['enableWithdrawals']);
    }

    public function test_mock_balance_is_positive_and_deterministic(): void
    {
        $balance = $this->broker->getTotalBalance('my_wallet', 'agent_key');

        $this->assertGreaterThan(0, $balance);
    }

    // ─── Path mock: reglas de gestión de posición (US06) ─────────────────────

    public function test_mock_adjust_opens_long_and_is_idempotent(): void
    {
        $this->assertTrue($this->broker->adjustPosition('my_wallet', 'agent_key', 'LONG'));
        $this->assertSame('LONG', $this->broker->getOpenPosition('my_wallet', 'agent_key'));

        // Misma dirección: no se reabre.
        $this->assertFalse($this->broker->adjustPosition('my_wallet', 'agent_key', 'LONG'));
    }

    public function test_mock_close_only_acts_when_a_position_is_open(): void
    {
        $this->assertFalse($this->broker->adjustPosition('my_wallet', 'agent_key', 'CLOSE'));

        $this->broker->adjustPosition('my_wallet', 'agent_key', 'SHORT');
        $this->assertTrue($this->broker->adjustPosition('my_wallet', 'agent_key', 'CLOSE'));
        $this->assertSame('CLOSE', $this->broker->getOpenPosition('my_wallet', 'agent_key'));
    }

    public function test_mock_flip_closes_and_opens_the_opposite_position(): void
    {
        $this->broker->adjustPosition('my_wallet', 'agent_key', 'LONG');

        $this->assertTrue($this->broker->adjustPosition('my_wallet', 'agent_key', 'SHORT'));
        $this->assertSame('SHORT', $this->broker->getOpenPosition('my_wallet', 'agent_key'));
    }

    public function test_mock_sizes_the_order_by_risk_profile_fraction_and_leverage(): void
    {
        config(['services.hyperliquid.leverage' => 2]);

        $this->broker->adjustPosition('my_wallet', 'agent_key', 'LONG', 'conservador');

        $context = Cache::get(HyperliquidBroker::mockLastOrderCacheKey('my_wallet'));

        $expectedBalance = (float) (1000 + (crc32('my_wallet') % 9000));
        $this->assertSame('conservador', $context['risk_level']);
        $this->assertSame(0.20, $context['fraction']);
        $this->assertSame(2, $context['leverage']);
        $this->assertSame($expectedBalance, $context['balance']);
        $this->assertSame(round($expectedBalance * 0.20 * 2, 2), $context['notional']);
        $this->assertSame(50000.0, $context['price']);
    }

    // ─── Path real: consultas de estado ──────────────────────────────────────

    public function test_real_position_is_inferred_from_clearinghouse_szi(): void
    {
        $this->useRealDriver();
        Http::fake($this->fakeInfoResponses(szi: -0.02));

        $position = $this->broker->getOpenPosition(self::WALLET, self::AGENT_KEY);

        $this->assertSame('SHORT', $position);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/info')
            && $request['type'] === 'clearinghouseState'
            && $request['user'] === self::WALLET);
    }

    public function test_real_total_balance_reads_account_value_equity(): void
    {
        $this->useRealDriver();
        Http::fake($this->fakeInfoResponses(accountValue: 1234.567));

        $this->assertSame(1234.57, $this->broker->getTotalBalance(self::WALLET, self::AGENT_KEY));
    }

    public function test_real_available_balance_reads_withdrawable(): void
    {
        $this->useRealDriver();
        Http::fake($this->fakeInfoResponses(withdrawable: 321.994));

        $this->assertSame(321.99, $this->broker->getAvailableBalance(self::WALLET, self::AGENT_KEY));
    }

    public function test_real_rejects_malformed_wallet_address(): void
    {
        $this->useRealDriver();

        $this->expectException(HyperliquidInvalidCredentialsException::class);

        $this->broker->getTotalBalance('not-an-address', self::AGENT_KEY);
    }

    // ─── Path real: restricciones y detección de clave maestra ───────────────

    public function test_real_flags_master_private_key_as_withdrawal_capable(): void
    {
        $this->useRealDriver();

        // La clave privada corresponde exactamente a la wallet indicada.
        $restrictions = $this->broker->checkApiRestrictions(
            '0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266',
            self::AGENT_KEY,
        );

        $this->assertTrue($restrictions['enableWithdrawals']);
    }

    public function test_real_accepts_approved_agent_credentials(): void
    {
        $this->useRealDriver();
        Http::fake($this->fakeInfoResponses());

        $restrictions = $this->broker->checkApiRestrictions(self::WALLET, self::AGENT_KEY);

        $this->assertFalse($restrictions['enableWithdrawals']);
        $this->assertSame('0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266', $restrictions['agentAddress']);
    }

    public function test_real_rejects_agent_not_approved_by_the_wallet(): void
    {
        $this->useRealDriver();
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/info') && $request['type'] === 'extraAgents') {
                return Http::response([['address' => '0x0000000000000000000000000000000000000001', 'name' => 'otro']]);
            }

            return ($this->fakeInfoResponses())($request);
        });

        $this->expectException(HyperliquidInvalidCredentialsException::class);

        $this->broker->checkApiRestrictions(self::WALLET, self::AGENT_KEY);
    }

    // ─── Path real: ejecución de órdenes firmadas ────────────────────────────

    public function test_real_open_long_sends_signed_ioc_order_with_expected_wire_format(): void
    {
        $this->useRealDriver();
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/exchange')) {
                return Http::response(['status' => 'ok', 'response' => ['type' => 'order', 'data' => ['statuses' => [['filled' => ['totalSz' => '0.01', 'avgPx' => '50000', 'oid' => 1]]]]]]);
            }

            return ($this->fakeInfoResponses(szi: 0.0, withdrawable: 1000.0))($request);
        });

        $changed = $this->broker->adjustPosition(self::WALLET, self::AGENT_KEY, 'LONG', 'balanceado');

        $this->assertTrue($changed);

        // Orden: compra de 0.01 BTC (1000 × 50% × 1x / 50000) a precio agresivo
        // 52500 (mid + 5%), como límite IoC no reduceOnly, firmada.
        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/exchange') || ($request['action']['type'] ?? null) !== 'order') {
                return false;
            }
            $order = $request['action']['orders'][0];

            return $order['a'] === 0
                && $order['b'] === true
                && $order['p'] === '52500'
                && $order['s'] === '0.01'
                && $order['r'] === false
                && $order['t'] === ['limit' => ['tif' => 'Ioc']]
                && $request['action']['grouping'] === 'na'
                && isset($request['nonce'], $request['signature']['r'], $request['signature']['s'], $request['signature']['v']);
        });

        // Antes de abrir se fija el apalancamiento configurado (cross).
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/exchange')
            && ($request['action']['type'] ?? null) === 'updateLeverage'
            && $request['action']['isCross'] === true
            && $request['action']['leverage'] === 1);
    }

    public function test_real_close_sends_reduce_only_order_opposite_to_position(): void
    {
        $this->useRealDriver();
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/exchange')) {
                return Http::response(['status' => 'ok', 'response' => ['type' => 'order', 'data' => ['statuses' => [['filled' => ['totalSz' => '0.02', 'avgPx' => '50000', 'oid' => 2]]]]]]);
            }

            // SHORT abierto de 0.02 BTC.
            return ($this->fakeInfoResponses(szi: -0.02))($request);
        });

        $changed = $this->broker->adjustPosition(self::WALLET, self::AGENT_KEY, 'CLOSE');

        $this->assertTrue($changed);

        // Cierre del corto: compra reduceOnly de 0.02 BTC.
        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/exchange') || ($request['action']['type'] ?? null) !== 'order') {
                return false;
            }
            $order = $request['action']['orders'][0];

            return $order['b'] === true && $order['r'] === true && $order['s'] === '0.02';
        });
    }

    public function test_real_adjust_is_idempotent_when_position_matches_signal(): void
    {
        $this->useRealDriver();
        Http::fake($this->fakeInfoResponses(szi: 0.01));

        $changed = $this->broker->adjustPosition(self::WALLET, self::AGENT_KEY, 'LONG');

        $this->assertFalse($changed);
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/exchange'));
    }

    public function test_real_order_rejection_raises_exception(): void
    {
        $this->useRealDriver();
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/exchange')) {
                return Http::response(['status' => 'ok', 'response' => ['type' => 'order', 'data' => ['statuses' => [['error' => 'Order must have minimum value of $10']]]]]);
            }

            return ($this->fakeInfoResponses())($request);
        });

        $this->expectException(HyperliquidException::class);

        $this->broker->adjustPosition(self::WALLET, self::AGENT_KEY, 'LONG');
    }
}
