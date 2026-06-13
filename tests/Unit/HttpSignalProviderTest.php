<?php

namespace Tests\Unit;

use App\Infrastructure\Signals\HttpSignalProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pruebas unitarias de HttpSignalProvider (US06).
 */
class HttpSignalProviderTest extends TestCase
{
    private HttpSignalProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        // Configurar variables necesarias
        config([
            'signals.http.base_url' => 'https://trading.vibo-solutions.com',
            'signals.http.token' => 'mi_token_secreto',
            'signals.http.timeout' => 5,
            'signals.http.retries' => 0, // 0 reintentos para simplificar tests fakes
        ]);

        $this->provider = new HttpSignalProvider();
    }

    /**
     * Prueba que getCurrentSignal consulte correctamente el endpoint real.
     */
    public function test_get_current_signal_sends_correct_request_and_parses_response(): void
    {
        Http::fake([
            'https://trading.vibo-solutions.com/api/v1/signal*' => Http::response([
                'position' => 'LONG',
                'issued_at' => '2026-06-14T00:30:15+02:00',
                'signal_id' => 'sig-bal-1718195415'
            ], 200)
        ]);

        $result = $this->provider->getCurrentSignal('balanceado');

        $this->assertSame('LONG', $result['position']);
        $this->assertSame('2026-06-14T00:30:15+02:00', $result['issued_at']);
        $this->assertSame('sig-bal-1718195415', $result['signal_id']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://trading.vibo-solutions.com/api/v1/signal?risk_level=balanceado'
                && $request->hasHeader('Authorization', 'Bearer mi_token_secreto')
                && $request->method() === 'GET';
        });
    }

    /**
     * Prueba que getSignalHistory consulte correctamente el endpoint de historial y lo formatee.
     */
    public function test_get_signal_history_sends_correct_request_and_parses_response(): void
    {
        Http::fake([
            'https://trading.vibo-solutions.com/api/v1/signals/history*' => Http::response([
                [
                    'date' => '2026-06-13',
                    'time' => '18:40:00',
                    'position' => 'LONG',
                    'profit' => 0
                ],
                [
                    'date' => '2026-06-13',
                    'time' => '18:55:00',
                    'position' => 'close',
                    'profit' => 0.0185
                ]
            ], 200)
        ]);

        $result = $this->provider->getSignalHistory('balanceado');

        $this->assertCount(2, $result);
        
        $this->assertSame('2026-06-13', $result[0]['date']);
        $this->assertSame('18:40:00', $result[0]['time']);
        $this->assertSame('LONG', $result[0]['position']);
        $this->assertSame(0.0, $result[0]['profit']);

        $this->assertSame('2026-06-13', $result[1]['date']);
        $this->assertSame('18:55:00', $result[1]['time']);
        $this->assertSame('CLOSE', $result[1]['position']);
        $this->assertSame(0.0185, $result[1]['profit']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://trading.vibo-solutions.com/api/v1/signals/history?risk_level=balanceado'
                && $request->hasHeader('Authorization', 'Bearer mi_token_secreto')
                && $request->method() === 'GET';
        });
    }

    /**
     * Prueba que se lance una excepción cuando la API de señales falla.
     */
    public function test_throws_exception_on_api_failure(): void
    {
        Http::fake([
            'https://trading.vibo-solutions.com/api/v1/signal*' => Http::response([], 500)
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No se pudo conectar con el proveedor de señales externo');

        $this->provider->getCurrentSignal('balanceado');
    }

    /**
     * Prueba que se lance una excepción si falta la posición en la respuesta.
     */
    public function test_throws_exception_on_invalid_response_data(): void
    {
        Http::fake([
            'https://trading.vibo-solutions.com/api/v1/signal*' => Http::response([
                'issued_at' => '2026-06-14T00:30:15+02:00'
            ], 200)
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid response structure');

        $this->provider->getCurrentSignal('balanceado');
    }
}
