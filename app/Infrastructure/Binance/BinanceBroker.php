<?php

namespace App\Infrastructure\Binance;

use App\Core\Contracts\BinanceBrokerInterface;
use App\Core\Exceptions\BinanceException;
use App\Core\Exceptions\BinanceInvalidCredentialsException;
use App\Core\Simulation\RiskProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BinanceBroker implements BinanceBrokerInterface
{
    /**
     * Obtiene las restricciones y permisos de la API Key de Binance.
     *
     *
     * @throws BinanceInvalidCredentialsException
     * @throws BinanceException
     */
    public function checkApiRestrictions(string $apiKey, string $secretKey): array
    {
        if (config('services.binance.mock')) {
            return $this->handleMock($apiKey, $secretKey);
        }

        return $this->handleReal($apiKey, $secretKey);
    }

    /**
     * Obtiene el balance total consolidado de la cuenta en EUR (US03).
     *
     *
     * @throws BinanceInvalidCredentialsException
     * @throws BinanceException
     */
    public function getTotalBalance(string $apiKey, string $secretKey): float
    {
        if (config('services.binance.mock')) {
            return $this->handleMockBalance($apiKey, $secretKey);
        }

        return $this->handleRealBalance($apiKey, $secretKey);
    }

    /**
     * Saldo disponible en el wallet de Futuros USDⓈ-M, en el activo de margen
     * configurado (USDT/USDC). Es el capital utilizable para abrir posiciones (US06).
     *
     * @throws BinanceInvalidCredentialsException
     * @throws BinanceException
     */
    public function getAvailableBalance(string $apiKey, string $secretKey): float
    {
        if (config('services.binance.mock')) {
            if (str_contains($apiKey, 'invalid') || str_contains($secretKey, 'invalid')) {
                throw new BinanceInvalidCredentialsException;
            }

            // Saldo determinista (sin oscilación) para que el dimensionamiento sea reproducible.
            return (float) (1000 + (crc32($apiKey) % 9000));
        }

        return $this->handleRealAvailableBalance($apiKey, $secretKey);
    }

    /**
     * Consulta el balance del wallet de futuros (GET /fapi/v2/balance) y devuelve
     * el saldo disponible (`availableBalance`) del activo de margen configurado.
     *
     * @throws BinanceInvalidCredentialsException
     * @throws BinanceException
     */
    protected function handleRealAvailableBalance(string $apiKey, string $secretKey): float
    {
        $queryString = http_build_query([
            'timestamp' => now()->timestamp * 1000,
        ]);
        $signature = hash_hmac('sha256', $queryString, $secretKey);
        $futuresUrl = config('services.binance.futures_url', 'https://fapi.binance.com');
        $marginAsset = config('services.binance.margin_asset', 'USDT');

        try {
            $response = Http::withHeaders([
                'X-MBX-APIKEY' => $apiKey,
            ])->get("{$futuresUrl}/fapi/v2/balance?{$queryString}&signature={$signature}");

            if ($response->status() === 400 || $response->status() === 401) {
                $data = $response->json();
                if (isset($data['code']) && $data['code'] === -2015) {
                    throw new BinanceInvalidCredentialsException;
                }
                throw new BinanceException($data['msg'] ?? 'Error de autenticación o permisos al consultar el balance de futuros.');
            }

            if ($response->failed()) {
                throw new BinanceException('No se pudo obtener el balance de futuros de Binance: HTTP '.$response->status());
            }

            foreach ($response->json() ?? [] as $wallet) {
                if (($wallet['asset'] ?? null) === $marginAsset) {
                    return round((float) ($wallet['availableBalance'] ?? 0), 2);
                }
            }

            return 0.0;
        } catch (\Exception $e) {
            if ($e instanceof BinanceInvalidCredentialsException || $e instanceof BinanceException) {
                throw $e;
            }
            Log::error('Binance futures balance check failed: '.$e->getMessage());
            throw new BinanceException('Error al conectar con la API de Binance para obtener el balance de futuros: '.$e->getMessage());
        }
    }

    /**
     * Simula el balance consolidado en modo mock: un valor base determinista
     * derivado de la API Key con una oscilación suave (~±2%) según la hora
     * del día, para transmitir la sensación de "cuenta viva" en demos.
     *
     * @throws BinanceInvalidCredentialsException
     */
    protected function handleMockBalance(string $apiKey, string $secretKey): float
    {
        if (str_contains($apiKey, 'invalid') || str_contains($secretKey, 'invalid')) {
            throw new BinanceInvalidCredentialsException;
        }

        $base = 1000 + (crc32($apiKey) % 9000);
        $angle = (now()->timestamp % 86400) / 86400 * 2 * M_PI;

        return round($base * (1 + 0.02 * sin($angle)), 2);
    }

    /**
     * Consulta real: suma los balances de todas las wallets (denominados en BTC
     * por el endpoint oficial) y los convierte a EUR con el ticker BTCEUR.
     * Simplificación deliberada del MVP: una sola conversión en lugar de
     * valorar activo por activo.
     *
     * @throws BinanceInvalidCredentialsException
     * @throws BinanceException
     */
    protected function handleRealBalance(string $apiKey, string $secretKey): float
    {
        $timestamp = now()->timestamp * 1000;
        $queryString = http_build_query([
            'timestamp' => $timestamp,
            'quoteAsset' => 'EUR',
        ]);
        $signature = hash_hmac('sha256', $queryString, $secretKey);

        $apiUrl = config('services.binance.api_url', 'https://api.binance.com');

        try {
            $response = Http::withHeaders([
                'X-MBX-APIKEY' => $apiKey,
            ])->get("{$apiUrl}/sapi/v1/asset/wallet/balance?{$queryString}&signature={$signature}");

            if ($response->status() === 400 || $response->status() === 401) {
                $data = $response->json();
                if (isset($data['code']) && $data['code'] === -2015) {
                    throw new BinanceInvalidCredentialsException;
                }
                throw new BinanceException($data['msg'] ?? 'Error de autenticación o permisos en Binance.');
            }

            if ($response->failed()) {
                throw new BinanceException('No se pudo obtener el balance de Binance: HTTP '.$response->status());
            }

            $totalEur = array_sum(array_map(
                fn (array $wallet) => (float) ($wallet['balance'] ?? 0),
                $response->json() ?? [],
            ));

            return round($totalEur, 2);
        } catch (\Exception $e) {
            if ($e instanceof BinanceInvalidCredentialsException || $e instanceof BinanceException) {
                throw $e;
            }
            Log::error('Binance balance check failed: '.$e->getMessage());
            throw new BinanceException('Error al conectar con la API de Binance: '.$e->getMessage());
        }
    }

    /**
     * Maneja la simulación local del broker de Binance para desarrollo y pruebas.
     *
     *
     * @throws BinanceInvalidCredentialsException
     */
    protected function handleMock(string $apiKey, string $secretKey): array
    {
        // Si contiene invalid, simulamos credenciales incorrectas
        if (str_contains($apiKey, 'invalid') || str_contains($secretKey, 'invalid')) {
            throw new BinanceInvalidCredentialsException;
        }

        // Si contiene withdraw, simulamos permisos de retiro habilitados
        if (str_contains($apiKey, 'withdraw') || str_contains($secretKey, 'withdraw')) {
            return [
                'enableWithdrawals' => true,
                'ipRestrict' => false,
                'enableSpotAndMarginTrading' => true,
            ];
        }

        // Caso exitoso por defecto
        return [
            'enableWithdrawals' => false,
            'ipRestrict' => false,
            'enableSpotAndMarginTrading' => true,
        ];
    }

    /**
     * Realiza la llamada HTTPS real a la API oficial de Binance.
     *
     *
     * @throws BinanceInvalidCredentialsException
     * @throws BinanceException
     */
    protected function handleReal(string $apiKey, string $secretKey): array
    {
        $timestamp = now()->timestamp * 1000;
        $params = ['timestamp' => $timestamp];
        $queryString = http_build_query($params);
        $signature = hash_hmac('sha256', $queryString, $secretKey);

        $apiUrl = config('services.binance.api_url', 'https://api.binance.com');
        $endpoint = '/sapi/v1/account/apiRestrictions';

        try {
            $response = Http::withHeaders([
                'X-MBX-APIKEY' => $apiKey,
            ])->get("{$apiUrl}{$endpoint}?{$queryString}&signature={$signature}");

            if ($response->status() === 400 || $response->status() === 401) {
                $data = $response->json();
                // Código de error -2015 en Binance indica credenciales o permisos inválidos
                if (isset($data['code']) && $data['code'] === -2015) {
                    throw new BinanceInvalidCredentialsException;
                }
                throw new BinanceException($data['msg'] ?? 'Error de autenticación o permisos en Binance.');
            }

            if ($response->failed()) {
                throw new BinanceException('No se pudo establecer conexión con Binance: HTTP '.$response->status());
            }

            return $response->json();
        } catch (\Exception $e) {
            if ($e instanceof BinanceInvalidCredentialsException) {
                throw $e;
            }
            Log::error('Binance API restrictions check failed: '.$e->getMessage());
            throw new BinanceException('Error al conectar con la API de Binance: '.$e->getMessage());
        }
    }

    /**
     * Cancela todas las órdenes abiertas y gestiona el cierre preventivo
     * de las posiciones en Binance para mitigar el riesgo al pausar el bot.
     *
     * @throws BinanceInvalidCredentialsException
     * @throws BinanceException
     */
    public function closeOpenPositions(string $apiKey, string $secretKey): bool
    {
        if (config('services.binance.mock')) {
            return $this->handleMockClose($apiKey, $secretKey);
        }

        return $this->handleRealClose($apiKey, $secretKey);
    }

    /**
     * Simulación de cierre preventivo en desarrollo y pruebas.
     *
     * @throws BinanceInvalidCredentialsException
     * @throws BinanceException
     */
    protected function handleMockClose(string $apiKey, string $secretKey): bool
    {
        if (str_contains($apiKey, 'invalid') || str_contains($secretKey, 'invalid')) {
            throw new BinanceInvalidCredentialsException;
        }

        if (str_contains($apiKey, 'fail_close') || str_contains($secretKey, 'fail_close')) {
            throw new BinanceException('Simulated failure closing positions.');
        }

        // Aplanar la posición simulada: tras cerrar no queda ninguna abierta.
        Cache::put(self::mockPositionCacheKey($apiKey), 'CLOSE');

        return true;
    }

    /**
     * Cierre real en Futuros USDⓈ-M: cancela las órdenes abiertas del par y, si
     * hay una posición abierta, la aplana con una orden de mercado reduceOnly en
     * sentido contrario.
     *
     * @throws BinanceInvalidCredentialsException
     * @throws BinanceException
     */
    protected function handleRealClose(string $apiKey, string $secretKey): bool
    {
        $symbol = config('services.binance.symbol', 'BTCUSDT');
        $futuresUrl = config('services.binance.futures_url', 'https://fapi.binance.com');

        // 1. Cancelar todas las órdenes abiertas del par en futuros.
        $cancelQuery = http_build_query([
            'symbol' => $symbol,
            'timestamp' => now()->timestamp * 1000,
        ]);
        $cancelSignature = hash_hmac('sha256', $cancelQuery, $secretKey);

        try {
            $response = Http::withHeaders([
                'X-MBX-APIKEY' => $apiKey,
            ])->delete("{$futuresUrl}/fapi/v1/allOpenOrders?{$cancelQuery}&signature={$cancelSignature}");

            if ($response->status() === 400 || $response->status() === 401) {
                $data = $response->json();
                if (isset($data['code']) && $data['code'] === -2015) {
                    throw new BinanceInvalidCredentialsException;
                }
                throw new BinanceException($data['msg'] ?? 'Error de autenticación o permisos al cerrar posiciones.');
            }

            if ($response->failed()) {
                throw new BinanceException('No se pudo cancelar las órdenes en Binance: HTTP '.$response->status());
            }
        } catch (\Exception $e) {
            if ($e instanceof BinanceInvalidCredentialsException || $e instanceof BinanceException) {
                throw $e;
            }
            Log::error('Binance cancel orders failed: '.$e->getMessage());
            throw new BinanceException('Error al conectar con la API de Binance para cerrar posiciones: '.$e->getMessage());
        }

        // 2. Aplanar la posición abierta (si existe) con una orden reduceOnly opuesta.
        $positionAmt = $this->fetchRealPositionAmt($apiKey, $secretKey);
        if ($positionAmt !== 0.0) {
            $side = $positionAmt > 0 ? 'SELL' : 'BUY';
            $this->sendFuturesMarketOrder($apiKey, $secretKey, $side, abs($positionAmt), true);
        }

        return true;
    }

    /**
     * Clave de caché que almacena la posición abierta simulada por API Key (modo mock).
     */
    public static function mockPositionCacheKey(string $apiKey): string
    {
        return 'binance:mock_position:'.md5($apiKey);
    }

    /**
     * Clave de caché con el contexto de la última orden de apertura simulada
     * (perfil, fracción, apalancamiento, capital y nocional). Útil para validar
     * el dimensionamiento de la posición en pruebas sin tocar Binance.
     */
    public static function mockLastOrderCacheKey(string $apiKey): string
    {
        return 'binance:mock_last_order:'.md5($apiKey);
    }

    /**
     * Devuelve la posición abierta actualmente en Binance: 'LONG', 'SHORT' o 'CLOSE'.
     */
    public function getOpenPosition(string $apiKey, string $secretKey): string
    {
        if (config('services.binance.mock')) {
            if (str_contains($apiKey, 'invalid') || str_contains($secretKey, 'invalid')) {
                throw new BinanceInvalidCredentialsException;
            }

            return Cache::get(self::mockPositionCacheKey($apiKey), 'CLOSE');
        }

        return $this->handleRealOpenPosition($apiKey, $secretKey);
    }

    /**
     * Consulta la posición abierta real vía Binance Futures y la traduce a
     * 'LONG' (positionAmt > 0), 'SHORT' (< 0) o 'CLOSE' (= 0).
     *
     * @throws BinanceInvalidCredentialsException
     * @throws BinanceException
     */
    protected function handleRealOpenPosition(string $apiKey, string $secretKey): string
    {
        $positionAmt = $this->fetchRealPositionAmt($apiKey, $secretKey);

        return match (true) {
            $positionAmt > 0 => 'LONG',
            $positionAmt < 0 => 'SHORT',
            default => 'CLOSE',
        };
    }

    /**
     * Devuelve la cantidad firmada de la posición abierta en Futuros USDⓈ-M
     * (positionAmt: positivo = LONG, negativo = SHORT, 0 = sin posición).
     *
     * @throws BinanceInvalidCredentialsException
     * @throws BinanceException
     */
    protected function fetchRealPositionAmt(string $apiKey, string $secretKey): float
    {
        $symbol = config('services.binance.symbol', 'BTCUSDT');
        $queryString = http_build_query([
            'symbol' => $symbol,
            'timestamp' => now()->timestamp * 1000,
        ]);
        $signature = hash_hmac('sha256', $queryString, $secretKey);
        $futuresUrl = config('services.binance.futures_url', 'https://fapi.binance.com');

        try {
            $response = Http::withHeaders([
                'X-MBX-APIKEY' => $apiKey,
            ])->get("{$futuresUrl}/fapi/v2/positionRisk?{$queryString}&signature={$signature}");

            if ($response->status() === 400 || $response->status() === 401) {
                $data = $response->json();
                if (isset($data['code']) && $data['code'] === -2015) {
                    throw new BinanceInvalidCredentialsException;
                }
                throw new BinanceException($data['msg'] ?? 'Error de autenticación o permisos al consultar la posición en Binance.');
            }

            if ($response->failed()) {
                throw new BinanceException('No se pudo consultar la posición en Binance: HTTP '.$response->status());
            }

            return (float) ($response->json('0.positionAmt') ?? 0);
        } catch (\Exception $e) {
            if ($e instanceof BinanceInvalidCredentialsException || $e instanceof BinanceException) {
                throw $e;
            }
            Log::error('Binance position query failed: '.$e->getMessage());
            throw new BinanceException('Error al conectar con la API de Binance para consultar la posición: '.$e->getMessage());
        }
    }

    /**
     * Ajusta la posición en Binance según la señal objetivo (LONG, SHORT, CLOSE)
     * partiendo del estado real consultado en el exchange (US06).
     *
     * @return bool true si se ejecutó un cambio de estado; false si fue idempotente.
     */
    public function adjustPosition(string $apiKey, string $secretKey, string $position, string $riskLevel = 'balanceado'): bool
    {
        $position = strtoupper($position);

        if (config('services.binance.mock')) {
            return $this->handleMockAdjustPosition($apiKey, $secretKey, $position, $riskLevel);
        }

        return $this->handleRealAdjustPosition($apiKey, $secretKey, $position, $riskLevel);
    }

    /**
     * Simulación de ajuste de posición en mock, replicando las reglas de
     * gestión de posición y dimensionamiento para que sean verificables en tests.
     */
    protected function handleMockAdjustPosition(string $apiKey, string $secretKey, string $position, string $riskLevel): bool
    {
        if (str_contains($apiKey, 'invalid') || str_contains($secretKey, 'invalid')) {
            throw new BinanceInvalidCredentialsException;
        }

        $current = $this->getOpenPosition($apiKey, $secretKey);

        // Señal de cierre: solo se cierra si efectivamente hay algo abierto.
        if ($position === 'CLOSE') {
            if ($current === 'CLOSE') {
                return false;
            }

            $this->closeOpenPositions($apiKey, $secretKey);
            Cache::put(self::mockPositionCacheKey($apiKey), 'CLOSE');

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

        // Se consulta el capital disponible más actualizado y se dimensiona la orden.
        Cache::put(self::mockLastOrderCacheKey($apiKey), $this->buildOrderContext($apiKey, $secretKey, $position, $riskLevel));
        Cache::put(self::mockPositionCacheKey($apiKey), $position);

        return true;
    }

    /**
     * Ajusta la posición real en Binance Futures aplicando las reglas de US06.
     */
    protected function handleRealAdjustPosition(string $apiKey, string $secretKey, string $position, string $riskLevel): bool
    {
        $current = $this->handleRealOpenPosition($apiKey, $secretKey);

        if ($position === 'CLOSE') {
            if ($current === 'CLOSE') {
                return false;
            }

            $this->closeOpenPositions($apiKey, $secretKey);

            return true;
        }

        // No reabrir si ya estamos en la posición objetivo.
        if ($current === $position) {
            return false;
        }

        // Cerrar la posición contraria antes de abrir la nueva.
        if ($current !== 'CLOSE') {
            $this->closeOpenPositions($apiKey, $secretKey);
        }

        $context = $this->buildOrderContext($apiKey, $secretKey, $position, $riskLevel);

        // Apalancamiento objetivo (10x por defecto) "de ser posible".
        $this->setLeverage($apiKey, $secretKey, (int) $context['leverage']);

        $this->placeMarketOrder($apiKey, $secretKey, $position, (float) $context['quantity']);

        return true;
    }

    /**
     * Calcula el contexto de la orden de apertura a partir del capital disponible
     * más actualizado (consultado en Binance), la fracción del perfil de riesgo y
     * el apalancamiento configurado. El nocional = capital × fracción × apalancamiento.
     *
     * @return array{position: string, risk_level: string, fraction: float, leverage: int, balance: float, notional: float, price: float, quantity: float}
     */
    protected function buildOrderContext(string $apiKey, string $secretKey, string $position, string $riskLevel): array
    {
        // Capital realmente utilizable para abrir: saldo disponible del wallet de futuros.
        $balance = $this->getAvailableBalance($apiKey, $secretKey);
        $fraction = RiskProfile::fromString($riskLevel)->capitalFraction();
        $leverage = max(1, (int) config('services.binance.leverage', 10));
        $price = $this->getMarketPrice($apiKey, $secretKey);

        $notional = round($balance * $fraction * $leverage, 2);
        $quantity = $price > 0 ? round($notional / $price, 3) : 0.0;

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
     * Obtiene el precio de mercado del par operado. En modo mock devuelve un
     * precio determinista para que el dimensionamiento sea reproducible.
     */
    protected function getMarketPrice(string $apiKey, string $secretKey): float
    {
        if (config('services.binance.mock')) {
            return 50000.0;
        }

        $symbol = config('services.binance.symbol', 'BTCUSDT');
        $futuresUrl = config('services.binance.futures_url', 'https://fapi.binance.com');

        try {
            $response = Http::get("{$futuresUrl}/fapi/v1/ticker/price?symbol={$symbol}");

            if ($response->failed()) {
                throw new BinanceException('No se pudo obtener el precio de Binance: HTTP '.$response->status());
            }

            return (float) ($response->json('price') ?? 0);
        } catch (\Exception $e) {
            if ($e instanceof BinanceException) {
                throw $e;
            }
            Log::error('Binance price query failed: '.$e->getMessage());
            throw new BinanceException('Error al conectar con la API de Binance para obtener el precio: '.$e->getMessage());
        }
    }

    /**
     * Establece el apalancamiento del par en Binance Futures (POST /fapi/v1/leverage).
     * El fallo no es bloqueante: se registra y se continúa con el apalancamiento vigente.
     */
    protected function setLeverage(string $apiKey, string $secretKey, int $leverage): void
    {
        $symbol = config('services.binance.symbol', 'BTCUSDT');
        $queryString = http_build_query([
            'symbol' => $symbol,
            'leverage' => $leverage,
            'timestamp' => now()->timestamp * 1000,
        ]);
        $signature = hash_hmac('sha256', $queryString, $secretKey);
        $futuresUrl = config('services.binance.futures_url', 'https://fapi.binance.com');

        try {
            Http::withHeaders([
                'X-MBX-APIKEY' => $apiKey,
            ])->post("{$futuresUrl}/fapi/v1/leverage?{$queryString}&signature={$signature}");
        } catch (\Exception $e) {
            Log::warning("No se pudo fijar el apalancamiento {$leverage}x en Binance: ".$e->getMessage());
        }
    }

    /**
     * Abre una posición colocando una orden de mercado en Binance Futures.
     *
     * @throws BinanceInvalidCredentialsException
     * @throws BinanceException
     */
    protected function placeMarketOrder(string $apiKey, string $secretKey, string $position, float $quantity): bool
    {
        $side = $position === 'LONG' ? 'BUY' : 'SELL';

        return $this->sendFuturesMarketOrder($apiKey, $secretKey, $side, $quantity, false);
    }

    /**
     * Envía una orden de mercado a Binance Futures. Con $reduceOnly=true se usa
     * para aplanar (cerrar) una posición existente sin abrir una nueva.
     *
     * @throws BinanceInvalidCredentialsException
     * @throws BinanceException
     */
    protected function sendFuturesMarketOrder(string $apiKey, string $secretKey, string $side, float $quantity, bool $reduceOnly): bool
    {
        $symbol = config('services.binance.symbol', 'BTCUSDT');

        $params = [
            'symbol' => $symbol,
            'side' => $side,
            'type' => 'MARKET',
            'quantity' => $quantity,
            'timestamp' => now()->timestamp * 1000,
        ];

        if ($reduceOnly) {
            $params['reduceOnly'] = 'true';
        }

        $queryString = http_build_query($params);
        $signature = hash_hmac('sha256', $queryString, $secretKey);
        $futuresUrl = config('services.binance.futures_url', 'https://fapi.binance.com');

        try {
            $response = Http::withHeaders([
                'X-MBX-APIKEY' => $apiKey,
            ])->post("{$futuresUrl}/fapi/v1/order?{$queryString}&signature={$signature}");

            if ($response->status() === 400 || $response->status() === 401) {
                $data = $response->json();
                if (isset($data['code']) && $data['code'] === -2015) {
                    throw new BinanceInvalidCredentialsException;
                }
                throw new BinanceException($data['msg'] ?? 'Error de autenticación o permisos al colocar orden en Binance.');
            }

            if ($response->failed()) {
                throw new BinanceException('No se pudo colocar la orden en Binance: HTTP '.$response->status());
            }

            return true;
        } catch (\Exception $e) {
            if ($e instanceof BinanceInvalidCredentialsException || $e instanceof BinanceException) {
                throw $e;
            }
            Log::error('Binance order placement failed: '.$e->getMessage());
            throw new BinanceException('Error al conectar con la API de Binance para ajustar la posición: '.$e->getMessage());
        }
    }
}
