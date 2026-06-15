<?php

namespace Tests\Feature;

use App\Core\Contracts\BinanceBrokerInterface;
use App\Core\Contracts\SignalProviderInterface;
use App\Events\BalanceUpdated;
use App\Infrastructure\Signals\MockSignalProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * Pruebas de integración de US06: Sincronización de Señales en Tiempo Real.
 */
class SignalSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected $brokerMock;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.binance.mock' => true]);

        // Crear usuario por defecto
        $this->user = User::factory()->create([
            'binance_api_key' => 'my_secure_api_key',
            'binance_secret_key' => 'my_secret',
            'binance_verified' => true,
            'bot_active' => true,
            'bot_mode' => 'real',
            'risk_level' => 'balanceado',
            'estimated_capital' => 1000,
            'onboarding_completed_at' => now(),
        ]);

        // Mockear el broker de Binance para interceptar llamadas
        $this->brokerMock = Mockery::mock(BinanceBrokerInterface::class);
        $this->app->instance(BinanceBrokerInterface::class, $this->brokerMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ─── Escenario 1: Detección de cambio de señal y ajuste de posición ───

    public function test_signal_change_triggers_position_adjustment_real_mode(): void
    {
        Event::fake([BalanceUpdated::class]);

        // Precondición: última posición conocida en cache es LONG
        Cache::put('signal:last_known_position:balanceado', 'LONG');

        // La nueva señal leída del mock será CLOSE
        Cache::put('mock_signal:balanceado', 'CLOSE');

        // Configurar expectativas en el broker
        $this->brokerMock->shouldReceive('adjustPosition')
            ->once()
            ->with($this->user->binance_api_key, $this->user->binance_secret_key, 'CLOSE')
            ->andReturn(true);

        $this->brokerMock->shouldReceive('getTotalBalance')
            ->once()
            ->with($this->user->binance_api_key, $this->user->binance_secret_key)
            ->andReturn(1015.00);

        // Ejecutar el scheduler
        $this->artisan('signals:poll')->assertExitCode(0);

        // Verificar que la posición en cache se actualizó
        $this->assertSame('CLOSE', Cache::get('signal:last_known_position:balanceado'));

        // Verificar que se creó la actividad en el historial
        $this->assertDatabaseHas('bot_activities', [
            'user_id' => $this->user->id,
            'type' => 'close',
            'action' => 'close_profit',
            'risk_alert' => false,
        ]);

        // Verificar que se registró el nuevo snapshot
        $this->assertDatabaseHas('balance_snapshots', [
            'user_id' => $this->user->id,
            'balance' => 1015.00,
        ]);

        Event::assertDispatched(BalanceUpdated::class);
    }

    // ─── Escenario 2: Señal sin cambios (Idempotencia) ───────────────────

    public function test_no_changes_are_triggered_when_signal_is_identical(): void
    {
        // Precondición: última posición conocida es LONG
        Cache::put('signal:last_known_position:balanceado', 'LONG');

        // Nueva señal vuelve a ser LONG
        Cache::put('mock_signal:balanceado', 'LONG');

        // El broker NO debe recibir llamadas de ajuste de posición
        $this->brokerMock->shouldNotReceive('adjustPosition');

        // Ejecutar sondeo
        $this->artisan('signals:poll')->assertExitCode(0);

        // Sin nuevas actividades en la base de datos
        $this->assertSame(0, $this->user->botActivities()->count());
    }

    // ─── Escenario 3: Histórico de señales para el gráfico simulado ──────

    public function test_history_endpoint_returns_simulated_capital_evolution_in_simulation_mode(): void
    {
        // Pasar bot a modo simulación
        $this->user->update([
            'bot_mode' => 'simulation',
            'estimated_capital' => 1000.00,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('dashboard.balance', ['range' => 'month']));

        $response->assertOk();
        $response->assertJsonStructure([
            'range',
            'series' => [['t', 'value']],
            'current_balance',
            'change_percent',
            'change_message',
        ]);

        $data = $response->json();
        $this->assertSame('month', $data['range']);
        $this->assertNotEmpty($data['series']);

        // El primer punto de la serie debe ser el capital inicial
        $this->assertEquals(1000.00, $data['series'][0]['value']);

        // Los siguientes puntos deben calcularse acumulando el profit
        $this->assertGreaterThan(1000.00, $data['current_balance']);
        $this->assertStringContainsString('Tu balance ha subido', $data['change_message']);
    }

    // ─── Escenario 4: Proveedor mock por defecto ─────────────────────────

    public function test_mock_provider_is_default_and_resolves_interface(): void
    {
        $provider = app(SignalProviderInterface::class);

        $this->assertInstanceOf(MockSignalProvider::class, $provider);

        $signal = $provider->getCurrentSignal('balanceado');
        $this->assertArrayHasKey('position', $signal);
        $this->assertArrayHasKey('issued_at', $signal);
        $this->assertArrayHasKey('signal_id', $signal);

        $history = $provider->getSignalHistory('balanceado');
        $this->assertNotEmpty($history);
        $this->assertArrayHasKey('profit', $history[0]);
    }

    // ─── Escenario 5: Indisponibilidad de la API externa ─────────────────

    public function test_handles_signal_provider_errors_by_keeping_state_and_alerting(): void
    {
        // Forzar error mockeando la interfaz
        $errorProvider = Mockery::mock(SignalProviderInterface::class);
        $errorProvider->shouldReceive('getCurrentSignal')
            ->andThrow(new \Exception("Conexión perdida"));
        
        $this->app->instance(SignalProviderInterface::class, $errorProvider);

        // Precondición: última posición conocida es LONG
        Cache::put('signal:last_known_position:balanceado', 'LONG');

        // Ejecutar sondeo
        $this->artisan('signals:poll')->assertExitCode(0);

        // Se mantiene la posición anterior en cache
        $this->assertSame('LONG', Cache::get('signal:last_known_position:balanceado'));

        // Se marca inestabilidad en cache
        $this->assertTrue(Cache::has('signal_provider_unstable:balanceado'));

        // Acceder al dashboard y verificar que el aviso amigable se renderiza
        $response = $this->actingAs($this->user)->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('Conexión temporalmente inestable con el proveedor de señales, tus fondos están seguros.');
    }
}
