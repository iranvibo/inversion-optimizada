<?php

namespace Tests\Unit;

use App\Core\Contracts\BinanceBrokerInterface;
use App\Core\Exceptions\BinanceInvalidCredentialsException;
use Tests\TestCase;

class BinanceBrokerTest extends TestCase
{
    protected BinanceBrokerInterface $binanceBroker;

    protected function setUp(): void
    {
        parent::setUp();

        // Forzamos el modo mock en la configuración para las pruebas
        config(['services.binance.mock' => true]);

        $this->binanceBroker = $this->app->make(BinanceBrokerInterface::class);
    }

    /**
     * Prueba que el broker lance una excepción si las credenciales son inválidas.
     */
    public function test_throws_exception_on_invalid_credentials(): void
    {
        $this->expectException(BinanceInvalidCredentialsException::class);

        $this->binanceBroker->checkApiRestrictions('invalid_key', 'some_secret');
    }

    /**
     * Prueba que detecte correctamente cuando los permisos de retiro están activos.
     */
    public function test_detects_when_withdrawals_are_enabled(): void
    {
        $restrictions = $this->binanceBroker->checkApiRestrictions('withdrawals_enabled_key', 'some_secret');

        $this->assertTrue($restrictions['enableWithdrawals']);
    }

    /**
     * Prueba que permita la vinculación si los permisos de retiro están desactivados.
     */
    public function test_passes_when_withdrawals_are_disabled(): void
    {
        $restrictions = $this->binanceBroker->checkApiRestrictions('my_secure_api_key', 'some_secret');

        $this->assertFalse($restrictions['enableWithdrawals']);
    }

    // ─── Balance consolidado (US03) ──────────────────────────────────────

    /**
     * Prueba que el balance mock sea un importe positivo utilizable por la UI.
     */
    public function test_returns_positive_total_balance_in_mock_mode(): void
    {
        $balance = $this->binanceBroker->getTotalBalance('my_secure_api_key', 'some_secret');

        $this->assertGreaterThan(0, $balance);
    }

    /**
     * Prueba que el balance mock sea determinista: misma clave y mismo
     * instante producen el mismo valor (reproducible en demos y tests).
     */
    public function test_total_balance_is_deterministic_for_same_key_and_instant(): void
    {
        $this->travelTo(now());

        $first = $this->binanceBroker->getTotalBalance('my_secure_api_key', 'some_secret');
        $second = $this->binanceBroker->getTotalBalance('my_secure_api_key', 'some_secret');

        $this->assertSame($first, $second);
    }

    /**
     * Prueba que la consulta de balance rechace credenciales inválidas.
     */
    public function test_total_balance_throws_on_invalid_credentials(): void
    {
        $this->expectException(BinanceInvalidCredentialsException::class);

        $this->binanceBroker->getTotalBalance('invalid_key', 'some_secret');
    }
}
