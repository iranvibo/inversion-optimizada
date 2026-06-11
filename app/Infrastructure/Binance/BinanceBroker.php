<?php

namespace App\Infrastructure\Binance;

use App\Core\Contracts\BinanceBrokerInterface;
use App\Core\Exceptions\BinanceException;
use App\Core\Exceptions\BinanceInvalidCredentialsException;
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
        $queryString = http_build_query(['timestamp' => $timestamp]);
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

            $totalBtc = array_sum(array_map(
                fn (array $wallet) => (float) ($wallet['balance'] ?? 0),
                $response->json() ?? [],
            ));

            $ticker = Http::get("{$apiUrl}/api/v3/ticker/price", ['symbol' => 'BTCEUR']);

            if ($ticker->failed() || ! isset($ticker->json()['price'])) {
                throw new BinanceException('No se pudo obtener el precio de conversión BTC/EUR.');
            }

            return round($totalBtc * (float) $ticker->json()['price'], 2);
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
}
