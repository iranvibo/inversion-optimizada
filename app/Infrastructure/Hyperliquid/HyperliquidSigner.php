<?php

namespace App\Infrastructure\Hyperliquid;

use Elliptic\EC;
use kornrunner\Keccak;
use MessagePack\Packer;

/**
 * Firma de acciones L1 del exchange de Hyperliquid, replicando byte a byte el
 * esquema del SDK oficial (hyperliquid-python-sdk, utils/signing.py):
 *
 *   1. connectionId = keccak256( msgpack(action) + nonce(8 bytes BE) + 0x00 )
 *      (el byte 0x00 indica "sin vault"; con vault sería 0x01 + address).
 *   2. "Phantom agent" = { source: 'a' (mainnet) | 'b' (testnet), connectionId }.
 *   3. Firma EIP-712 del phantom agent con dominio
 *      { name: 'Exchange', version: '1', chainId: 1337, verifyingContract: 0x0 }.
 *
 * El orden de las claves del action importa: msgpack serializa los mapas en el
 * orden de inserción y cualquier variación produce una firma inválida. La
 * corrección de esta clase se verifica en tests contra los vectores oficiales
 * del SDK de Python (tests/signing_test.py).
 */
class HyperliquidSigner
{
    private const EIP712_DOMAIN_TYPE = 'EIP712Domain(string name,string version,uint256 chainId,address verifyingContract)';

    private const AGENT_TYPE = 'Agent(string source,bytes32 connectionId)';

    /**
     * Firma una acción L1 y devuelve la firma en el formato que espera el
     * endpoint /exchange: ['r' => '0x..', 's' => '0x..', 'v' => 27|28].
     *
     * @param  array<string, mixed>  $action  Acción con las claves YA en el orden de wire.
     * @param  string|null  $vaultAddress  Dirección del vault/subcuenta (no usado en este MVP).
     * @return array{r: string, s: string, v: int}
     */
    public function signL1Action(array $action, int $nonce, string $privateKey, bool $isMainnet, ?string $vaultAddress = null): array
    {
        $connectionId = $this->actionHash($action, $nonce, $vaultAddress);

        $digest = $this->phantomAgentDigest($connectionId, $isMainnet);

        return $this->signDigest($digest, $privateKey);
    }

    /**
     * Hash de la acción (connectionId): keccak256 del msgpack de la acción,
     * el nonce en 8 bytes big-endian y el discriminador de vault.
     *
     * @param  array<string, mixed>  $action
     * @return string 32 bytes binarios.
     */
    public function actionHash(array $action, int $nonce, ?string $vaultAddress = null): string
    {
        $data = (new Packer)->pack($action);
        $data .= pack('J', $nonce); // uint64 big-endian

        if ($vaultAddress === null) {
            $data .= "\x00";
        } else {
            $data .= "\x01".hex2bin($this->stripHexPrefix(strtolower($vaultAddress)));
        }

        return $this->keccak($data);
    }

    /**
     * Digest EIP-712 (\x19\x01 + domainSeparator + hashStruct) del phantom agent
     * { source: 'a'|'b', connectionId } con el dominio fijo del exchange.
     *
     * @param  string  $connectionId  32 bytes binarios.
     * @return string 32 bytes binarios.
     */
    protected function phantomAgentDigest(string $connectionId, bool $isMainnet): string
    {
        $domainSeparator = $this->keccak(
            $this->keccak(self::EIP712_DOMAIN_TYPE)
            .$this->keccak('Exchange')
            .$this->keccak('1')
            .$this->uint256(1337)
            .str_repeat("\x00", 32) // verifyingContract = 0x0, alineado a 32 bytes
        );

        $structHash = $this->keccak(
            $this->keccak(self::AGENT_TYPE)
            .$this->keccak($isMainnet ? 'a' : 'b')
            .$connectionId
        );

        return $this->keccak("\x19\x01".$domainSeparator.$structHash);
    }

    /**
     * Firma ECDSA (secp256k1, k determinista RFC 6979, s canónico) de un digest.
     *
     * @param  string  $digest  32 bytes binarios.
     * @return array{r: string, s: string, v: int}
     */
    protected function signDigest(string $digest, string $privateKey): array
    {
        $ec = new EC('secp256k1');
        $key = $ec->keyFromPrivate($this->stripHexPrefix($privateKey), 'hex');
        $signature = $key->sign(bin2hex($digest), ['canonical' => true]);

        return [
            // Hex mínimo (sin ceros a la izquierda), como to_hex() del SDK oficial.
            'r' => '0x'.(ltrim($signature->r->toString(16), '0') ?: '0'),
            's' => '0x'.(ltrim($signature->s->toString(16), '0') ?: '0'),
            'v' => 27 + (int) $signature->recoveryParam,
        ];
    }

    /**
     * Dirección Ethereum (0x + 40 hex en minúsculas) derivada de una clave
     * privada. Permite detectar si el usuario pegó la clave de su wallet
     * principal en lugar de una API wallet (agente) y rechazarla.
     */
    public function addressFromPrivateKey(string $privateKey): string
    {
        $ec = new EC('secp256k1');
        $key = $ec->keyFromPrivate($this->stripHexPrefix($privateKey), 'hex');

        // Clave pública sin comprimir: 0x04 + X (32 bytes) + Y (32 bytes).
        $publicKey = hex2bin($key->getPublic(false, 'hex'));

        return '0x'.substr(bin2hex($this->keccak(substr($publicKey, 1))), -40);
    }

    /**
     * Formatea un número al formato "wire" de Hyperliquid (float_to_wire del SDK):
     * máximo 8 decimales, sin ceros finales ni notación científica.
     */
    public static function floatToWire(float $value): string
    {
        $rounded = number_format($value, 8, '.', '');

        if (abs((float) $rounded - $value) >= 1e-12) {
            throw new \InvalidArgumentException("float_to_wire causes rounding: {$value}");
        }

        $normalized = rtrim(rtrim($rounded, '0'), '.');

        return $normalized === '' || $normalized === '-0' ? '0' : $normalized;
    }

    /**
     * keccak-256 sobre bytes, devolviendo bytes.
     */
    protected function keccak(string $data): string
    {
        return hex2bin(Keccak::hash($data, 256));
    }

    /**
     * Entero sin signo alineado a 32 bytes (big-endian), para EIP-712.
     */
    protected function uint256(int $value): string
    {
        return str_pad(pack('J', $value), 32, "\x00", STR_PAD_LEFT);
    }

    protected function stripHexPrefix(string $hex): string
    {
        return str_starts_with($hex, '0x') ? substr($hex, 2) : $hex;
    }
}
