<?php

namespace App\Infrastructure\Signals;

use App\Core\Contracts\SignalProviderInterface;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proveedor de señales HTTP real (US06).
 */
class HttpSignalProvider implements SignalProviderInterface
{
    private string $baseUrl;
    private ?string $token;
    private int $timeout;
    private int $retries;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('signals.http.base_url', ''), '/');
        $this->token = config('signals.http.token');
        $this->timeout = (int) config('signals.http.timeout', 5);
        $this->retries = (int) config('signals.http.retries', 2);
    }

    /**
     * Obtiene la señal de posición objetivo actual para un nivel de riesgo.
     */
    public function getCurrentSignal(string $riskLevel): array
    {
        $url = "{$this->baseUrl}/api/v1/signal";

        try {
            $request = Http::timeout($this->timeout);

            if ($this->token) {
                $request = $request->withToken($this->token);
            }

            if ($this->retries > 0) {
                $request = $request->retry($this->retries, 100);
            }

            $response = $request->get($url, [
                'risk_level' => strtolower($riskLevel),
            ]);

            if ($response->failed()) {
                throw new Exception("HTTP request failed with status: " . $response->status());
            }

            $data = $response->json();

            if (!isset($data['position'])) {
                throw new Exception("Invalid response structure: 'position' key missing.");
            }

            return [
                'position' => strtoupper($data['position']),
                'issued_at' => $data['issued_at'] ?? now()->toIso8601String(),
                'signal_id' => $data['signal_id'] ?? uniqid('sig-'),
                'profit' => (float) ($data['profit'] ?? 0.0),
            ];
        } catch (Exception $e) {
            Log::error("HttpSignalProvider connection failed: " . $e->getMessage());
            throw new Exception("No se pudo conectar con el proveedor de señales externo: " . $e->getMessage());
        }
    }

    /**
     * Obtiene el historial de señales para un nivel de riesgo.
     */
    public function getSignalHistory(string $riskLevel): array
    {
        $url = "{$this->baseUrl}/api/v1/signals/history";

        try {
            $request = Http::timeout($this->timeout);

            if ($this->token) {
                $request = $request->withToken($this->token);
            }

            if ($this->retries > 0) {
                $request = $request->retry($this->retries, 100);
            }

            $response = $request->get($url, [
                'risk_level' => strtolower($riskLevel),
            ]);

            if ($response->failed()) {
                throw new Exception("HTTP request failed with status: " . $response->status());
            }

            $data = $response->json();

            if (!is_array($data)) {
                throw new Exception("Invalid response structure: expected array.");
            }

            return array_map(fn($item) => [
                'date' => $item['date'] ?? now()->format('Y-m-d'),
                'time' => $item['time'] ?? now()->format('H:i:s'),
                'position' => strtoupper($item['position'] ?? 'CLOSE'),
                'profit' => (float) ($item['profit'] ?? 0.0),
            ], $data);
        } catch (Exception $e) {
            Log::error("HttpSignalProvider history fetch failed: " . $e->getMessage());
            throw new Exception("No se pudo obtener el historial de señales: " . $e->getMessage());
        }
    }
}
