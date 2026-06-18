<?php

namespace Tests\Unit;

use App\Core\Contracts\BinanceBrokerInterface;
use App\Core\Exceptions\BinanceInvalidCredentialsException;
use App\Infrastructure\Binance\BinanceBroker;
use Illuminate\Support\Facades\Cache;
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

    // ─── Cierre preventivo de posiciones (US04) ──────────────────────────

    /**
     * Prueba que el cierre preventivo devuelva true en modo mock.
     */
    public function test_close_open_positions_returns_true_in_mock_mode(): void
    {
        $result = $this->binanceBroker->closeOpenPositions('my_secure_api_key', 'some_secret');

        $this->assertTrue($result);
    }

    /**
     * Prueba que el cierre preventivo rechace credenciales inválidas.
     */
    public function test_close_open_positions_throws_on_invalid_credentials(): void
    {
        $this->expectException(BinanceInvalidCredentialsException::class);

        $this->binanceBroker->closeOpenPositions('invalid_key', 'some_secret');
    }

    // ─── Gestión de posición y dimensionamiento (US06) ───────────────────

    /**
     * Sin operaciones previas, no hay posición abierta en Binance.
     */
    public function test_open_position_defaults_to_close(): void
    {
        $this->assertSame('CLOSE', $this->binanceBroker->getOpenPosition('my_key', 'my_secret'));
    }

    /**
     * Abrir una posición la deja registrada como abierta y devuelve true (cambio ejecutado).
     */
    public function test_adjust_position_opens_and_returns_changed(): void
    {
        $changed = $this->binanceBroker->adjustPosition('my_key', 'my_secret', 'LONG', 'balanceado');

        $this->assertTrue($changed);
        $this->assertSame('LONG', $this->binanceBroker->getOpenPosition('my_key', 'my_secret'));
    }

    /**
     * Si ya hay una posición abierta en la misma dirección, no se reabre (idempotente).
     */
    public function test_adjust_position_does_not_reopen_same_direction(): void
    {
        $this->binanceBroker->adjustPosition('my_key', 'my_secret', 'LONG', 'balanceado');

        $changed = $this->binanceBroker->adjustPosition('my_key', 'my_secret', 'LONG', 'balanceado');

        $this->assertFalse($changed, 'No debe reabrir una posición ya abierta en la misma dirección.');
        $this->assertSame('LONG', $this->binanceBroker->getOpenPosition('my_key', 'my_secret'));
    }

    /**
     * Una señal contraria cierra la posición actual y abre la nueva.
     */
    public function test_adjust_position_flips_opposite_direction(): void
    {
        $this->binanceBroker->adjustPosition('my_key', 'my_secret', 'LONG', 'balanceado');

        $changed = $this->binanceBroker->adjustPosition('my_key', 'my_secret', 'SHORT', 'balanceado');

        $this->assertTrue($changed);
        $this->assertSame('SHORT', $this->binanceBroker->getOpenPosition('my_key', 'my_secret'));
    }

    /**
     * Una señal de cierre cierra la posición abierta.
     */
    public function test_adjust_position_closes_open_position(): void
    {
        $this->binanceBroker->adjustPosition('my_key', 'my_secret', 'LONG', 'balanceado');

        $changed = $this->binanceBroker->adjustPosition('my_key', 'my_secret', 'CLOSE', 'balanceado');

        $this->assertTrue($changed);
        $this->assertSame('CLOSE', $this->binanceBroker->getOpenPosition('my_key', 'my_secret'));
    }

    /**
     * Cerrar cuando no hay nada abierto es idempotente (no ejecuta cambios).
     */
    public function test_adjust_position_close_with_no_open_position_is_noop(): void
    {
        $changed = $this->binanceBroker->adjustPosition('my_key', 'my_secret', 'CLOSE', 'balanceado');

        $this->assertFalse($changed);
        $this->assertSame('CLOSE', $this->binanceBroker->getOpenPosition('my_key', 'my_secret'));
    }

    /**
     * El saldo disponible de futuros en mock es positivo y determinista por clave.
     */
    public function test_available_balance_is_positive_and_deterministic(): void
    {
        $first = $this->binanceBroker->getAvailableBalance('my_key', 'my_secret');
        $second = $this->binanceBroker->getAvailableBalance('my_key', 'my_secret');

        $this->assertGreaterThan(0, $first);
        $this->assertSame($first, $second);
    }

    /**
     * El saldo disponible rechaza credenciales inválidas.
     */
    public function test_available_balance_throws_on_invalid_credentials(): void
    {
        $this->expectException(BinanceInvalidCredentialsException::class);

        $this->binanceBroker->getAvailableBalance('invalid_key', 'some_secret');
    }

    /**
     * Cerrar posiciones (cierre preventivo / pausa) aplana la posición abierta.
     */
    public function test_close_open_positions_flattens_open_position(): void
    {
        $this->binanceBroker->adjustPosition('my_key', 'my_secret', 'LONG', 'balanceado');
        $this->assertSame('LONG', $this->binanceBroker->getOpenPosition('my_key', 'my_secret'));

        $this->binanceBroker->closeOpenPositions('my_key', 'my_secret');

        $this->assertSame('CLOSE', $this->binanceBroker->getOpenPosition('my_key', 'my_secret'));
    }

    /**
     * El tamaño de la posición usa el capital disponible actual, la fracción del
     * perfil de riesgo y el apalancamiento 10x. Nocional = capital × fracción × 10.
     */
    public function test_position_size_uses_risk_fraction_balance_and_leverage(): void
    {
        config(['services.binance.leverage' => 10]);

        $apiKey = 'sizing_key';
        $secret = 'sizing_secret';
        $balance = $this->binanceBroker->getAvailableBalance($apiKey, $secret);

        $this->binanceBroker->adjustPosition($apiKey, $secret, 'LONG', 'agresivo');

        $order = Cache::get(BinanceBroker::mockLastOrderCacheKey($apiKey));

        $this->assertSame(0.90, $order['fraction']);
        $this->assertSame(10, $order['leverage']);
        $this->assertSame($balance, $order['balance']);
        $this->assertSame(round($balance * 0.90 * 10, 2), $order['notional']);
    }

    /**
     * A mayor agresividad, mayor nocional comprometido (90% > 50% > 20%),
     * a igualdad de capital disponible (misma clave y mismo instante).
     */
    public function test_aggressive_profile_commits_more_capital_than_conservative(): void
    {
        $this->travelTo(now());

        $key = 'profile_key';
        $secret = 'secret';

        $this->binanceBroker->adjustPosition($key, $secret, 'LONG', 'agresivo');
        $aggressive = Cache::get(BinanceBroker::mockLastOrderCacheKey($key))['notional'];

        $this->binanceBroker->adjustPosition($key, $secret, 'CLOSE', 'agresivo');
        $this->binanceBroker->adjustPosition($key, $secret, 'LONG', 'balanceado');
        $balanced = Cache::get(BinanceBroker::mockLastOrderCacheKey($key))['notional'];

        $this->binanceBroker->adjustPosition($key, $secret, 'CLOSE', 'balanceado');
        $this->binanceBroker->adjustPosition($key, $secret, 'LONG', 'conservador');
        $conservative = Cache::get(BinanceBroker::mockLastOrderCacheKey($key))['notional'];

        $this->assertGreaterThan($balanced, $aggressive);
        $this->assertGreaterThan($conservative, $balanced);
    }
}
