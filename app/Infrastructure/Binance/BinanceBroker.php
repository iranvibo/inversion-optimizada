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
     * @param string $apiKey
     * @param string $secretKey
     * @return array
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
     * Maneja la simulación local del broker de Binance para desarrollo y pruebas.
     *
     * @param string $apiKey
     * @param string $secretKey
     * @return array
     *
     * @throws BinanceInvalidCredentialsException
     */
    protected function handleMock(string $apiKey, string $secretKey): array
    {
        // Si contiene invalid, simulamos credenciales incorrectas
        if (str_contains($apiKey, 'invalid') || str_contains($secretKey, 'invalid')) {
            throw new BinanceInvalidCredentialsException();
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
     * @param string $apiKey
     * @param string $secretKey
     * @return array
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
        $endpoint = "/sapi/v1/account/apiRestrictions";

        try {
            $response = Http::withHeaders([
                'X-MBX-APIKEY' => $apiKey,
            ])->get("{$apiUrl}{$endpoint}?{$queryString}&signature={$signature}");

            if ($response->status() === 400 || $response->status() === 401) {
                $data = $response->json();
                // Código de error -2015 en Binance indica credenciales o permisos inválidos
                if (isset($data['code']) && $data['code'] === -2015) {
                    throw new BinanceInvalidCredentialsException();
                }
                throw new BinanceException($data['msg'] ?? 'Error de autenticación o permisos en Binance.');
            }

            if ($response->failed()) {
                throw new BinanceException('No se pudo establecer conexión con Binance: HTTP ' . $response->status());
            }

            return $response->json();
        } catch (\Exception $e) {
            if ($e instanceof BinanceInvalidCredentialsException) {
                throw $e;
            }
            Log::error('Binance API restrictions check failed: ' . $e->getMessage());
            throw new BinanceException('Error al conectar con la API de Binance: ' . $e->getMessage());
        }
    }
}
