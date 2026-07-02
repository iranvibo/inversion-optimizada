<?php

namespace App\Infrastructure\Hyperliquid;

use App\Core\Contracts\HyperliquidBrokerInterface;
use App\Core\Exceptions\HyperliquidException;
use App\Core\Exceptions\HyperliquidInvalidCredentialsException;
use App\Core\Simulation\RiskProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Canal de ejecución sobre Hyperliquid (DEX de futuros perpetuos on-chain).
 *
 * Credenciales (ver HyperliquidBrokerInterface):
 *   - $apiKey    → dirección de la wallet principal (0x..., consultas /info).
 *   - $secretKey → clave privada de la API wallet (agente) que firma /exchange.
 *                  Un agente puede operar pero NO retirar ni transferir fondos.
 *
 * Mecánica:
 *   - Estado (posición, equity, saldo libre): POST /info clearinghouseState.
 *   - Órdenes "de mercado": límite IoC con precio agresivo (mid ± slippage),
 *     como hace el SDK oficial. El cierre usa reduceOnly.
 *   - El apalancamiento se fija con la acción updateLeverage (cross) antes de
 *     abrir; su fallo no es bloqueante (se opera con el apalancamiento vigente).
 *
 * Reglas de gestión de posición idénticas al canal de Binance (US06):
 * idempotencia, cierre solo si hay algo abierto, flip cierra-y-abre, y
 * dimensionamiento = capital_disponible × fracción_perfil × apalancamiento.
 */
class HyperliquidBroker implements HyperliquidBrokerInterface
{
    /**
     * Último nonce usado, para garantizar monotonicidad cuando se envían dos
     * acciones en el mismo milisegundo (p.ej. cierre + apertura en un flip).
     */
    protected int $lastNonce = 0;

    public function __construct(
        protected readonly HyperliquidSigner $signer,
    ) {}

    /**
     * Valida las credenciales y comprueba la promesa de seguridad del canal:
     * la clave aportada NO debe poder retirar fondos. En Hyperliquid eso se
     * traduce en exigir una API wallet (agente): si la clave privada pegada
     * corresponde a la propia wallet principal, esa clave SÍ podría retirar y
     * se reporta 'enableWithdrawals' => true para que el flujo la rechace.
     *
     * @throws HyperliquidInvalidCredentialsException
     * @throws HyperliquidException
     */
    public function checkApiRestrictions(string $apiKey, string $secretKey): array
    {
        if ($this->isMock()) {
            return $this->handleMockRestrictions($apiKey, $secretKey);
        }

        $wallet = $this->normalizedAddress($apiKey);
        $agentAddress = $this->agentAddress($secretKey);

        if ($agentAddress === $wallet) {
            // La clave privada controla la wallet principal: puede retirar.
            return ['enableWithdrawals' => true, 'agentAddress' => $agentAddress];
        }

        // La wallet debe existir y ser consultable en el exchange.
        $state = $this->fetchClearinghouseState($wallet);

        // Verificación best-effort de que el agente está aprobado por la wallet.
        // Si el endpoint no responde no se bloquea la vinculación: una clave de
        // agente no autorizada fallará igualmente al firmar la primera orden.
        try {
            $agents = $this->postInfo(['type' => 'extraAgents', 'user' => $wallet]);
            $approved = array_map(
                fn ($agent) => strtolower((string) ($agent['address'] ?? '')),
                is_array($agents) ? $agents : [],
            );
            if ($approved !== [] && ! in_array($agentAddress, $approved, true)) {
                throw new HyperliquidInvalidCredentialsException(
                    'La API wallet no está autorizada para esta cuenta de Hyperliquid. Aprueba el agente en el panel de API de Hyperliquid y vuelve a intentarlo.'
                );
            }
        } catch (HyperliquidInvalidCredentialsException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('Hyperliquid extraAgents check skipped: '.$e->getMessage());
        }

        return [
            'enableWithdrawals' => false,
            'agentAddress' => $agentAddress,
            'accountValue' => (float) ($state['marginSummary']['accountValue'] ?? 0),
        ];
    }

    /**
     * Patrimonio neto (equity) de la cuenta de perpetuos, en USDC (la UI lo
     * muestra como $): accountValue = colateral + P/L latente de la posición,
     * por lo que abrir una posición no hace caer el balance (US03).
     */
    public function getTotalBalance(string $apiKey, string $secretKey): float
    {
        if ($this->isMock()) {
            return $this->handleMockBalance($apiKey, $secretKey);
        }

        $state = $this->fetchClearinghouseState($this->normalizedAddress($apiKey));

        return round((float) ($state['marginSummary']['accountValue'] ?? 0), 2);
    }

    /**
     * Colateral libre utilizable para abrir nuevas posiciones (withdrawable),
     * en USDC. Se consulta en cada apertura (US06).
     */
    public function getAvailableBalance(string $apiKey, string $secretKey): float
    {
        if ($this->isMock()) {
            $this->assertMockCredentials($apiKey, $secretKey);

            // Saldo determinista (sin oscilación) para dimensionamiento reproducible.
            return (float) (1000 + (crc32($apiKey) % 9000));
        }

        $state = $this->fetchClearinghouseState($this->normalizedAddress($apiKey));

        return round((float) ($state['withdrawable'] ?? 0), 2);
    }

    /**
     * Posición abierta en el activo operado: 'LONG' (szi > 0), 'SHORT' (szi < 0)
     * o 'CLOSE' (sin posición).
     */
    public function getOpenPosition(string $apiKey, string $secretKey): string
    {
        if ($this->isMock()) {
            $this->assertMockCredentials($apiKey, $secretKey);

            return Cache::get(self::mockPositionCacheKey($apiKey), 'CLOSE');
        }

        $szi = $this->fetchSignedPositionSize($this->normalizedAddress($apiKey));

        return match (true) {
            $szi > 0 => 'LONG',
            $szi < 0 => 'SHORT',
            default => 'CLOSE',
        };
    }

    /**
     * Cierre preventivo: cancela las órdenes abiertas del activo y aplana la
     * posición con una orden de mercado (IoC) reduceOnly opuesta (US04/US07).
     */
    public function closeOpenPositions(string $apiKey, string $secretKey): bool
    {
        if ($this->isMock()) {
            return $this->handleMockClose($apiKey, $secretKey);
        }

        $wallet = $this->normalizedAddress($apiKey);

        $this->cancelOpenOrders($wallet, $secretKey);

        $szi = $this->fetchSignedPositionSize($wallet);
        if ($szi !== 0.0) {
            // Se cierra la cantidad exacta reportada por el exchange (ya es un
            // múltiplo válido del paso del activo), en dirección opuesta.
            $this->sendMarketOrder($wallet, $secretKey, $szi < 0, abs($szi), true);
        }

        return true;
    }

    /**
     * Ajusta la posición hacia la señal objetivo aplicando las reglas de US06.
     *
     * @return bool true si se ejecutó un cambio de estado; false si fue idempotente.
     */
    public function adjustPosition(string $apiKey, string $secretKey, string $position, string $riskLevel = 'balanceado'): bool
    {
        $position = strtoupper($position);

        $current = $this->getOpenPosition($apiKey, $secretKey);

        if ($position === 'CLOSE') {
            if ($current === 'CLOSE') {
                return false;
            }

            $this->closeOpenPositions($apiKey, $secretKey);
            if ($this->isMock()) {
                Cache::put(self::mockPositionCacheKey($apiKey), 'CLOSE');
            }

            return true;
        }

        // Ya hay una posición abierta en la misma dirección: no se reabre.
        if ($current === $position) {
            return false;
        }

        // Posición contraria abierta: se cierra antes de abrir la nueva.
        if ($current !== 'CLOSE') {
            $this->closeOpenPositions($apiKey, $secretKey);
        }

        // Capital disponible más actualizado y dimensionamiento de la orden.
        $context = $this->buildOrderContext($apiKey, $secretKey, $position, $riskLevel);

        if ($this->isMock()) {
            Cache::put(self::mockLastOrderCacheKey($apiKey), $context);
            Cache::put(self::mockPositionCacheKey($apiKey), $position);

            return true;
        }

        $this->openRealPosition($apiKey, $secretKey, $position, $context);

        return true;
    }

    // ─── Claves de caché del mock (paridad con BinanceBroker) ────────────────

    /**
     * Posición abierta simulada por credencial (modo mock).
     */
    public static function mockPositionCacheKey(string $apiKey): string
    {
        return 'hyperliquid:mock_position:'.md5($apiKey);
    }

    /**
     * Contexto de la última orden de apertura simulada (perfil, fracción,
     * apalancamiento, capital, nocional), para validar el dimensionamiento.
     */
    public static function mockLastOrderCacheKey(string $apiKey): string
    {
        return 'hyperliquid:mock_last_order:'.md5($apiKey);
    }

    // ─── Path mock ───────────────────────────────────────────────────────────

    protected function isMock(): bool
    {
        return (bool) config('services.hyperliquid.mock');
    }

    /**
     * @throws HyperliquidInvalidCredentialsException
     */
    protected function assertMockCredentials(string $apiKey, string $secretKey): void
    {
        if (str_contains($apiKey, 'invalid') || str_contains($secretKey, 'invalid')) {
            throw new HyperliquidInvalidCredentialsException;
        }
    }

    /**
     * Mock de restricciones: 'invalid' → credenciales inválidas; 'master' o
     * 'withdraw' → la clave puede retirar (equivale a pegar la clave maestra).
     */
    protected function handleMockRestrictions(string $apiKey, string $secretKey): array
    {
        $this->assertMockCredentials($apiKey, $secretKey);

        $canWithdraw = str_contains($apiKey, 'withdraw') || str_contains($secretKey, 'withdraw')
            || str_contains($apiKey, 'master') || str_contains($secretKey, 'master');

        return ['enableWithdrawals' => $canWithdraw, 'agentAddress' => '0xmockagent'];
    }

    /**
     * Balance mock determinista con oscilación suave (~±2%), como en Binance.
     */
    protected function handleMockBalance(string $apiKey, string $secretKey): float
    {
        $this->assertMockCredentials($apiKey, $secretKey);

        $base = 1000 + (crc32($apiKey) % 9000);
        $angle = (now()->timestamp % 86400) / 86400 * 2 * M_PI;

        return round($base * (1 + 0.02 * sin($angle)), 2);
    }

    /**
     * @throws HyperliquidInvalidCredentialsException
     * @throws HyperliquidException
     */
    protected function handleMockClose(string $apiKey, string $secretKey): bool
    {
        $this->assertMockCredentials($apiKey, $secretKey);

        if (str_contains($apiKey, 'fail_close') || str_contains($secretKey, 'fail_close')) {
            throw new HyperliquidException('Simulated failure closing positions.');
        }

        Cache::put(self::mockPositionCacheKey($apiKey), 'CLOSE');

        return true;
    }

    // ─── Dimensionamiento (US06) ─────────────────────────────────────────────

    /**
     * Contexto de la orden de apertura: nocional = capital × fracción × leverage
     * y cantidad = nocional / precio, truncada al paso del activo (szDecimals).
     *
     * @return array{position: string, risk_level: string, fraction: float, leverage: int, balance: float, notional: float, price: float, quantity: float}
     */
    protected function buildOrderContext(string $apiKey, string $secretKey, string $position, string $riskLevel): array
    {
        $balance = $this->getAvailableBalance($apiKey, $secretKey);
        $fraction = RiskProfile::fromString($riskLevel)->capitalFraction();
        $leverage = max(1, (int) config('services.hyperliquid.leverage', 1));
        $price = $this->getMarketPrice();

        $notional = round($balance * $fraction * $leverage, 2);
        $quantity = $price > 0 ? $this->floorToSizeStep($notional / $price) : 0.0;

        return [
            'position' => $position,
            'risk_level' => strtolower($riskLevel),
            'fraction' => $fraction,
            'leverage' => $leverage,
            'balance' => $balance,
            'notional' => $notional,
            'price' => $price,
            'quantity' => $quantity,
        ];
    }

    /**
     * Abre la posición real: fija el apalancamiento (no bloqueante) y envía la
     * orden de mercado (límite IoC agresiva).
     *
     * @param  array{leverage: int, quantity: float}  $context
     */
    protected function openRealPosition(string $apiKey, string $secretKey, string $position, array $context): void
    {
        $this->setLeverage($secretKey, (int) $context['leverage']);

        $this->sendMarketOrder(
            $this->normalizedAddress($apiKey),
            $secretKey,
            $position === 'LONG',
            (float) $context['quantity'],
            false,
        );
    }

    // ─── Acciones firmadas (/exchange) ───────────────────────────────────────

    /**
     * Fija el apalancamiento cross del activo. El fallo no es bloqueante: se
     * registra y se continúa con el apalancamiento vigente (igual que Binance).
     */
    protected function setLeverage(string $secretKey, int $leverage): void
    {
        try {
            // El orden de las claves es el del SDK oficial (afecta a la firma).
            $this->postExchange([
                'type' => 'updateLeverage',
                'asset' => $this->assetIndex(),
                'isCross' => true,
                'leverage' => $leverage,
            ], $secretKey);
        } catch (\Exception $e) {
            Log::warning("No se pudo fijar el apalancamiento {$leverage}x en Hyperliquid: ".$e->getMessage());
        }
    }

    /**
     * Envía una "orden de mercado": límite IoC con precio agresivo (mid ± slippage),
     * el mecanismo canónico de Hyperliquid. Con $reduceOnly=true aplana la
     * posición existente sin poder abrir una nueva.
     *
     * @throws HyperliquidInvalidCredentialsException
     * @throws HyperliquidException
     */
    protected function sendMarketOrder(string $wallet, string $secretKey, bool $isBuy, float $quantity, bool $reduceOnly): void
    {
        $quantity = $reduceOnly ? $quantity : $this->floorToSizeStep($quantity);
        if ($quantity <= 0) {
            throw new HyperliquidException('La cantidad de la orden es 0: capital insuficiente para el tamaño mínimo del activo.');
        }

        $price = $this->aggressivePrice($isBuy, $this->getMarketPrice());

        // Claves y orden EXACTOS del wire del SDK (a, b, p, s, r, t): la firma
        // msgpack depende del orden de inserción.
        $action = [
            'type' => 'order',
            'orders' => [[
                'a' => $this->assetIndex(),
                'b' => $isBuy,
                'p' => HyperliquidSigner::floatToWire($price),
                's' => HyperliquidSigner::floatToWire($quantity),
                'r' => $reduceOnly,
                't' => ['limit' => ['tif' => 'Ioc']],
            ]],
            'grouping' => 'na',
        ];

        $response = $this->postExchange($action, $secretKey);

        foreach ($response['response']['data']['statuses'] ?? [] as $status) {
            if (isset($status['error'])) {
                throw new HyperliquidException('Orden rechazada por Hyperliquid: '.$status['error']);
            }
        }
    }

    /**
     * Cancela las órdenes abiertas del activo operado. No bloqueante: un fallo
     * al cancelar no impide continuar con el aplanado de la posición.
     */
    protected function cancelOpenOrders(string $wallet, string $secretKey): void
    {
        try {
            $openOrders = $this->postInfo(['type' => 'openOrders', 'user' => $wallet]);
            $coin = $this->coin();

            $cancels = [];
            foreach (is_array($openOrders) ? $openOrders : [] as $order) {
                if (($order['coin'] ?? null) === $coin && isset($order['oid'])) {
                    $cancels[] = ['a' => $this->assetIndex(), 'o' => (int) $order['oid']];
                }
            }

            if ($cancels !== []) {
                $this->postExchange(['type' => 'cancel', 'cancels' => $cancels], $secretKey);
            }
        } catch (\Exception $e) {
            Log::warning('Hyperliquid cancel orders warning: '.$e->getMessage());
        }
    }

    /**
     * Firma y envía una acción L1 al endpoint /exchange, interpretando el
     * formato de error del exchange.
     *
     * @throws HyperliquidInvalidCredentialsException
     * @throws HyperliquidException
     */
    protected function postExchange(array $action, string $secretKey): array
    {
        $nonce = $this->nextNonce();
        $signature = $this->signer->signL1Action(
            $action,
            $nonce,
            $secretKey,
            (bool) config('services.hyperliquid.is_mainnet', true),
        );

        try {
            $response = Http::post($this->apiUrl().'/exchange', [
                'action' => $action,
                'nonce' => $nonce,
                'signature' => $signature,
            ]);
        } catch (\Exception $e) {
            throw new HyperliquidException('Error al conectar con la API de Hyperliquid: '.$e->getMessage());
        }

        if ($response->failed()) {
            throw new HyperliquidException('No se pudo enviar la acción a Hyperliquid: HTTP '.$response->status());
        }

        $json = $response->json() ?? [];

        if (($json['status'] ?? null) !== 'ok') {
            $error = is_string($json['response'] ?? null) ? $json['response'] : json_encode($json);

            // Firma inválida o agente no autorizado/caducado → credenciales.
            $lower = strtolower((string) $error);
            if (str_contains($lower, 'does not exist') || str_contains($lower, 'unauthorized') || str_contains($lower, 'agent')) {
                throw new HyperliquidInvalidCredentialsException('Hyperliquid rechazó las credenciales: '.$error);
            }

            throw new HyperliquidException('Hyperliquid rechazó la acción: '.$error);
        }

        return $json;
    }

    // ─── Consultas de estado (/info) ─────────────────────────────────────────

    /**
     * Estado de la cuenta de perpetuos (posiciones, márgenes, withdrawable).
     *
     * @throws HyperliquidInvalidCredentialsException
     * @throws HyperliquidException
     */
    protected function fetchClearinghouseState(string $wallet): array
    {
        return $this->postInfo(['type' => 'clearinghouseState', 'user' => $wallet]);
    }

    /**
     * Tamaño firmado (szi) de la posición del activo operado: >0 LONG, <0 SHORT.
     */
    protected function fetchSignedPositionSize(string $wallet): float
    {
        $state = $this->fetchClearinghouseState($wallet);
        $coin = $this->coin();

        foreach ($state['assetPositions'] ?? [] as $entry) {
            if (($entry['position']['coin'] ?? null) === $coin) {
                return (float) ($entry['position']['szi'] ?? 0);
            }
        }

        return 0.0;
    }

    /**
     * POST al endpoint público /info.
     *
     * @throws HyperliquidInvalidCredentialsException
     * @throws HyperliquidException
     */
    protected function postInfo(array $body): array
    {
        try {
            $response = Http::post($this->apiUrl().'/info', $body);
        } catch (\Exception $e) {
            throw new HyperliquidException('Error al conectar con la API de Hyperliquid: '.$e->getMessage());
        }

        // 4xx en /info indica una petición malformada (p.ej. dirección inválida).
        if ($response->status() >= 400 && $response->status() < 500) {
            throw new HyperliquidInvalidCredentialsException;
        }

        if ($response->failed()) {
            throw new HyperliquidException('No se pudo consultar la API de Hyperliquid: HTTP '.$response->status());
        }

        return $response->json() ?? [];
    }

    /**
     * Precio de referencia del activo (mid). En mock, determinista (50000).
     *
     * @throws HyperliquidException
     */
    protected function getMarketPrice(): float
    {
        if ($this->isMock()) {
            return 50000.0;
        }

        $mids = $this->postInfo(['type' => 'allMids']);
        $price = (float) ($mids[$this->coin()] ?? 0);

        if ($price <= 0) {
            throw new HyperliquidException('No se pudo obtener el precio de '.$this->coin().' en Hyperliquid.');
        }

        return $price;
    }

    // ─── Metadatos del activo y formato de órdenes ───────────────────────────

    /**
     * Índice del activo en el universe de perpetuos y sus szDecimals. Se
     * consulta /info meta con caché de 1 hora; si falla, se usan los valores
     * de configuración (BTC: índice 0, 5 decimales).
     *
     * @return array{index: int, szDecimals: int}
     */
    protected function assetMeta(): array
    {
        $coin = $this->coin();
        $fallback = [
            'index' => (int) config('services.hyperliquid.asset_index', 0),
            'szDecimals' => (int) config('services.hyperliquid.sz_decimals', 5),
        ];

        if ($this->isMock()) {
            return $fallback;
        }

        try {
            return Cache::remember("hyperliquid:asset_meta:{$coin}", now()->addHour(), function () use ($coin, $fallback) {
                $meta = $this->postInfo(['type' => 'meta']);
                foreach ($meta['universe'] ?? [] as $index => $asset) {
                    if (($asset['name'] ?? null) === $coin) {
                        return ['index' => (int) $index, 'szDecimals' => (int) ($asset['szDecimals'] ?? $fallback['szDecimals'])];
                    }
                }

                return $fallback;
            });
        } catch (\Exception $e) {
            Log::warning('Hyperliquid meta lookup failed, using config fallback: '.$e->getMessage());

            return $fallback;
        }
    }

    protected function assetIndex(): int
    {
        return $this->assetMeta()['index'];
    }

    /**
     * Trunca la cantidad HACIA ABAJO al paso del activo (szDecimals) para no
     * exceder el saldo ni enviar tamaños inválidos.
     */
    protected function floorToSizeStep(float $quantity): float
    {
        $factor = 10 ** $this->assetMeta()['szDecimals'];

        return floor($quantity * $factor) / $factor;
    }

    /**
     * Precio agresivo para una orden IoC "de mercado" (mid ± slippage), ajustado
     * a las reglas de precio de Hyperliquid: máximo 5 cifras significativas y
     * como mucho (6 − szDecimals) decimales; los enteros siempre son válidos.
     */
    protected function aggressivePrice(bool $isBuy, float $mid): float
    {
        $slippage = (float) config('services.hyperliquid.slippage', 0.05);
        $price = $isBuy ? $mid * (1 + $slippage) : $mid * (1 - $slippage);

        $significantDecimals = 4 - (int) floor(log10(abs($price))); // 5 cifras significativas
        $decimals = max(0, min($significantDecimals, 6 - $this->assetMeta()['szDecimals']));

        return round($price, $decimals);
    }

    // ─── Utilidades ──────────────────────────────────────────────────────────

    /**
     * Normaliza y valida una dirección Ethereum (0x + 40 hex, en minúsculas).
     *
     * @throws HyperliquidInvalidCredentialsException
     */
    protected function normalizedAddress(string $address): string
    {
        $address = strtolower(trim($address));

        if (! preg_match('/^0x[0-9a-f]{40}$/', $address)) {
            throw new HyperliquidInvalidCredentialsException('La dirección de la wallet no tiene un formato válido (0x + 40 caracteres hexadecimales).');
        }

        return $address;
    }

    /**
     * Deriva la dirección del agente desde su clave privada, validando el formato.
     *
     * @throws HyperliquidInvalidCredentialsException
     */
    protected function agentAddress(string $privateKey): string
    {
        $normalized = strtolower(trim($privateKey));
        $hex = str_starts_with($normalized, '0x') ? substr($normalized, 2) : $normalized;

        if (! preg_match('/^[0-9a-f]{64}$/', $hex)) {
            throw new HyperliquidInvalidCredentialsException('La clave privada de la API wallet no tiene un formato válido (64 caracteres hexadecimales).');
        }

        return $this->signer->addressFromPrivateKey($hex);
    }

    /**
     * Nonce en milisegundos, estrictamente creciente dentro del proceso.
     */
    protected function nextNonce(): int
    {
        $this->lastNonce = max((int) round(microtime(true) * 1000), $this->lastNonce + 1);

        return $this->lastNonce;
    }

    protected function apiUrl(): string
    {
        return rtrim((string) config('services.hyperliquid.api_url', 'https://api.hyperliquid.xyz'), '/');
    }

    protected function coin(): string
    {
        return (string) config('services.hyperliquid.coin', 'BTC');
    }
}
