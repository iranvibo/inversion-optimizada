<?php

namespace Tests\Unit;

use App\Infrastructure\Hyperliquid\HyperliquidSigner;
use PHPUnit\Framework\TestCase;

/**
 * Verifica que la firma de acciones L1 replica BYTE A BYTE la del SDK oficial
 * de Hyperliquid. Todos los vectores (clave, acciones, nonces y firmas
 * esperadas) provienen de hyperliquid-python-sdk/tests/signing_test.py.
 *
 * Si cualquiera de estos tests falla, el canal real de Hyperliquid NO debe
 * usarse: una firma incorrecta hace que el exchange rechace las órdenes.
 */
class HyperliquidSignerTest extends TestCase
{
    private const TEST_PRIVATE_KEY = '0x0123456789012345678901234567890123456789012345678901234567890123';

    private HyperliquidSigner $signer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signer = new HyperliquidSigner;
    }

    public function test_phantom_agent_connection_id_matches_production(): void
    {
        $orderAction = [
            'type' => 'order',
            'orders' => [[
                'a' => 4,
                'b' => true,
                'p' => HyperliquidSigner::floatToWire(1670.1),
                's' => HyperliquidSigner::floatToWire(0.0147),
                'r' => false,
                't' => ['limit' => ['tif' => 'Ioc']],
            ]],
            'grouping' => 'na',
        ];

        $hash = '0x'.bin2hex($this->signer->actionHash($orderAction, 1677777606040));

        $this->assertSame('0x0fcbeda5ae3c4950a548021552a4fea2226858c4453571bf3f24ba017eac2908', $hash);
    }

    public function test_l1_action_signing_matches_official_sdk_vectors(): void
    {
        $action = ['type' => 'dummy', 'num' => 100000000000];

        $mainnet = $this->signer->signL1Action($action, 0, self::TEST_PRIVATE_KEY, true);
        $this->assertSame('0x53749d5b30552aeb2fca34b530185976545bb22d0b3ce6f62e31be961a59298', $mainnet['r']);
        $this->assertSame('0x755c40ba9bf05223521753995abb2f73ab3229be8ec921f350cb447e384d8ed8', $mainnet['s']);
        $this->assertSame(27, $mainnet['v']);

        $testnet = $this->signer->signL1Action($action, 0, self::TEST_PRIVATE_KEY, false);
        $this->assertSame('0x542af61ef1f429707e3c76c5293c80d01f74ef853e34b76efffcb57e574f9510', $testnet['r']);
        $this->assertSame('0x17b8b32f086e8cdede991f1e2c529f5dd5297cbe8128500e00cbaf766204a613', $testnet['s']);
        $this->assertSame(28, $testnet['v']);
    }

    public function test_order_action_signing_matches_official_sdk_vectors(): void
    {
        $orderAction = [
            'type' => 'order',
            'orders' => [[
                'a' => 1,
                'b' => true,
                'p' => HyperliquidSigner::floatToWire(100),
                's' => HyperliquidSigner::floatToWire(100),
                'r' => false,
                't' => ['limit' => ['tif' => 'Gtc']],
            ]],
            'grouping' => 'na',
        ];

        $mainnet = $this->signer->signL1Action($orderAction, 0, self::TEST_PRIVATE_KEY, true);
        $this->assertSame('0xd65369825a9df5d80099e513cce430311d7d26ddf477f5b3a33d2806b100d78e', $mainnet['r']);
        $this->assertSame('0x2b54116ff64054968aa237c20ca9ff68000f977c93289157748a3162b6ea940e', $mainnet['s']);
        $this->assertSame(28, $mainnet['v']);

        $testnet = $this->signer->signL1Action($orderAction, 0, self::TEST_PRIVATE_KEY, false);
        $this->assertSame('0x82b2ba28e76b3d761093aaded1b1cdad4960b3af30212b343fb2e6cdfa4e3d54', $testnet['r']);
        $this->assertSame('0x6b53878fc99d26047f4d7e8c90eb98955a109f44209163f52d8dc4278cbbd9f5', $testnet['s']);
        $this->assertSame(27, $testnet['v']);
    }

    public function test_signing_with_vault_address_matches_official_sdk_vector(): void
    {
        $action = ['type' => 'dummy', 'num' => 100000000000];

        $signature = $this->signer->signL1Action(
            $action, 0, self::TEST_PRIVATE_KEY, true, '0x1719884eb866cb12b2287399b15f7db5e7d775ea'
        );

        $this->assertSame('0x3c548db75e479f8012acf3000ca3a6b05606bc2ec0c29c50c515066a326239', $signature['r']);
        $this->assertSame('0x4d402be7396ce74fbba3795769cda45aec00dc3125a984f2a9f23177b190da2c', $signature['s']);
        $this->assertSame(28, $signature['v']);
    }

    public function test_float_to_wire_normalizes_like_the_official_sdk(): void
    {
        $this->assertSame('1670.1', HyperliquidSigner::floatToWire(1670.1));
        $this->assertSame('0.0147', HyperliquidSigner::floatToWire(0.0147));
        $this->assertSame('100', HyperliquidSigner::floatToWire(100));
        $this->assertSame('0', HyperliquidSigner::floatToWire(0));
        $this->assertSame('50000', HyperliquidSigner::floatToWire(50000.0));
    }

    public function test_derives_ethereum_address_from_private_key(): void
    {
        // Primera cuenta determinista de Hardhat (vector público conocido).
        $address = $this->signer->addressFromPrivateKey(
            '0xac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80'
        );

        $this->assertSame('0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266', $address);
    }
}
