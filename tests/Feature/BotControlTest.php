<?php

namespace Tests\Feature;

use App\Core\Contracts\BinanceBrokerInterface;
use App\Core\Contracts\SignalProviderInterface;
use App\Events\BotStatusUpdated;
use App\Jobs\AdjustPositionJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class BotControlTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.binance.mock' => true]);

        // Usuario con Binance conectado por defecto
        $this->user = User::factory()->create([
            'binance_api_key' => 'my_secure_api_key',
            'binance_secret_key' => 'my_secure_secret',
            'binance_verified' => true,
            'bot_active' => false,
            'bot_mode' => 'simulation',
        ]);
    }

    /**
     * Escenario 1: Activación instantánea del bot
     */
    public function test_activates_bot_and_broadcasts_event(): void
    {
        Event::fake([BotStatusUpdated::class]);

        $response = $this->actingAs($this->user)
            ->post(route('bot.toggle'));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Bot ACTIVADO con éxito.');

        $this->user->refresh();
        $this->assertTrue($this->user->bot_active);

        Event::assertDispatched(BotStatusUpdated::class, function (BotStatusUpdated $event) {
            return $event->user->is($this->user) && $event->botActive === true;
        });
    }

    /**
     * Reconciliación al activar en modo real: encola un ajuste hacia la señal
     * vigente (aunque no haya habido un "cambio" de señal), para no quedarse en
     * CLOSE tras un pausa→cierre cuando la señal sigue siendo SHORT/LONG.
     */
    public function test_activation_in_real_mode_reconciles_with_current_signal(): void
    {
        Event::fake([BotStatusUpdated::class]);
        Queue::fake();

        $provider = Mockery::mock(SignalProviderInterface::class);
        $provider->shouldReceive('getCurrentSignal')
            ->andReturn(['position' => 'SHORT', 'issued_at' => now()->toIso8601String(), 'signal_id' => 'sig-test']);
        $this->app->instance(SignalProviderInterface::class, $provider);

        $this->user->update(['bot_mode' => 'real', 'bot_active' => false, 'risk_level' => 'conservador']);

        $response = $this->actingAs($this->user)->post(route('bot.toggle'));

        $response->assertRedirect();
        $this->user->refresh();
        $this->assertTrue($this->user->bot_active);

        Queue::assertPushed(AdjustPositionJob::class, fn (AdjustPositionJob $job) => $job->userId === $this->user->id && $job->newPosition === 'SHORT');
    }

    /**
     * En modo simulación la activación reconcilia con la señal vigente encolando el ajuste.
     */
    public function test_activation_in_simulation_mode_reconciles_with_current_signal(): void
    {
        Event::fake([BotStatusUpdated::class]);
        Queue::fake();

        $provider = Mockery::mock(SignalProviderInterface::class);
        $provider->shouldReceive('getCurrentSignal')
            ->andReturn(['position' => 'LONG', 'issued_at' => now()->toIso8601String(), 'signal_id' => 'sig-test']);
        $this->app->instance(SignalProviderInterface::class, $provider);

        // El usuario por defecto está en modo simulación.
        $this->user->update(['risk_level' => 'agresivo']);

        $this->actingAs($this->user)->post(route('bot.toggle'))->assertRedirect();

        Queue::assertPushed(
            AdjustPositionJob::class,
            fn (AdjustPositionJob $job) => $job->userId === $this->user->id && $job->newPosition === 'LONG'
        );
        $this->assertSame('LONG', \Illuminate\Support\Facades\Cache::get('signal:last_known_position:agresivo'));
    }

    /**
     * Escenario 2 & 3: Pausado instantáneo del bot con cierre preventivo de posiciones
     */
    public function test_pauses_bot_triggers_mitigation_and_broadcasts_event(): void
    {
        Event::fake([BotStatusUpdated::class]);

        // Aseguramos que empiece activo
        $this->user->update(['bot_active' => true]);

        $response = $this->actingAs($this->user)
            ->post(route('bot.toggle'));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Bot PAUSADO de inmediato.');

        $this->user->refresh();
        $this->assertFalse($this->user->bot_active);

        Event::assertDispatched(BotStatusUpdated::class, function (BotStatusUpdated $event) {
            return $event->user->is($this->user) && $event->botActive === false;
        });
    }

    /**
     * Robustez: Qué pasa si falla el broker al cerrar posiciones preventivamente
     */
    public function test_pauses_bot_even_if_broker_fails(): void
    {
        Event::fake([BotStatusUpdated::class]);

        // Configuramos la API Key para simular fallo en el cierre
        $this->user->update([
            'bot_active' => true,
            'binance_api_key' => 'fail_close_key',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('bot.toggle'));

        $response->assertRedirect();
        // Debe avisar que falló el cierre en Binance, pero el bot se pausa de todos modos en local por seguridad
        $response->assertSessionHas('error');
        $sessionError = session('error');
        $this->assertStringContainsString('Advertencia: El bot se pausó localmente, pero hubo un problema al cerrar posiciones', $sessionError);

        $this->user->refresh();
        $this->assertFalse($this->user->bot_active);

        Event::assertDispatched(BotStatusUpdated::class, function (BotStatusUpdated $event) {
            return $event->user->is($this->user) && $event->botActive === false;
        });
    }

    /**
     * Soporte de JSON / AJAX para actualización en vivo
     */
    public function test_ajax_toggle_returns_json(): void
    {
        Event::fake([BotStatusUpdated::class]);

        $response = $this->actingAs($this->user)
            ->postJson(route('bot.toggle'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'bot_active' => true,
            'message' => 'Bot ACTIVADO con éxito.',
        ]);
    }

    /**
     * Requisito de seguridad al activar en modo REAL sin Binance vinculado
     */
    public function test_prevents_activation_in_real_mode_without_binance_linked(): void
    {
        Event::fake([BotStatusUpdated::class]);

        $unlinkedUser = User::factory()->create([
            'binance_verified' => false,
            'bot_active' => false,
            'bot_mode' => 'real',
        ]);

        $response = $this->actingAs($unlinkedUser)
            ->post(route('bot.toggle'));

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Para activar el bot debes vincular una cuenta de Binance autorizada', session('error'));

        $unlinkedUser->refresh();
        $this->assertFalse($unlinkedUser->bot_active);

        Event::assertNotDispatched(BotStatusUpdated::class);
    }

    /**
     * Requisito de seguridad al activar en modo SIMULATION sin Binance vinculado
     */
    public function test_prevents_activation_in_simulation_mode_without_binance_linked(): void
    {
        Event::fake([BotStatusUpdated::class]);

        $unlinkedUser = User::factory()->create([
            'binance_verified' => false,
            'bot_active' => false,
            'bot_mode' => 'simulation',
        ]);

        $response = $this->actingAs($unlinkedUser)
            ->post(route('bot.toggle'));

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Para activar el bot debes vincular una cuenta de Binance autorizada', session('error'));

        $unlinkedUser->refresh();
        $this->assertFalse($unlinkedUser->bot_active);

        Event::assertNotDispatched(BotStatusUpdated::class);
    }

    /**
     * Test de la US07: Actualización de nivel de riesgo exitosa vía POST.
     */
    public function test_updates_risk_level_successfully(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('bot.update-risk'), ['risk_level' => 'agresivo']);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Nivel de riesgo actualizado a: Agresivo');

        $this->user->refresh();
        $this->assertSame('agresivo', $this->user->risk_level);
    }

    /**
     * Al actualizar el nivel de riesgo con el bot activo, se reconcilia con la señal del nuevo nivel.
     */
    public function test_updates_risk_level_reconciles_when_bot_is_active(): void
    {
        Queue::fake();

        $provider = Mockery::mock(SignalProviderInterface::class);
        $provider->shouldReceive('getCurrentSignal')
            ->with('conservador')
            ->andReturn(['position' => 'SHORT', 'issued_at' => now()->toIso8601String(), 'signal_id' => 'sig-test']);
        $this->app->instance(SignalProviderInterface::class, $provider);

        $this->user->update([
            'bot_active' => true,
            'risk_level' => 'balanceado',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('bot.update-risk'), ['risk_level' => 'conservador']);

        $response->assertRedirect();
        $this->user->refresh();
        $this->assertSame('conservador', $this->user->risk_level);

        Queue::assertPushed(
            AdjustPositionJob::class,
            fn (AdjustPositionJob $job) => $job->userId === $this->user->id && $job->newPosition === 'SHORT'
        );
        $this->assertSame('SHORT', \Illuminate\Support\Facades\Cache::get('signal:last_known_position:conservador'));
    }

    /**
     * Test de la US07: Actualización de nivel de riesgo vía AJAX/JSON.
     */
    public function test_updates_risk_level_via_ajax(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('bot.update-risk'), ['risk_level' => 'balanceado']);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'risk_level' => 'balanceado',
            'message' => 'Nivel de riesgo actualizado a: Balanceado',
        ]);

        $this->user->refresh();
        $this->assertSame('balanceado', $this->user->risk_level);
    }

    /**
     * Test de la US07: Validación de nivel de riesgo no válido.
     */
    public function test_prevents_invalid_risk_level(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('bot.update-risk'), ['risk_level' => 'super_agresivo']);

        $response->assertSessionHasErrors(['risk_level']);
    }

    /**
     * Test de la US07: Alternancia de modo real a simulación sin tocar Binance ni alterar la posición real en caché.
     */
    public function test_changes_mode_from_real_to_simulation_without_touching_binance(): void
    {
        $broker = Mockery::mock(BinanceBrokerInterface::class);
        $broker->shouldNotReceive('closeOpenPositions');
        $this->app->instance(BinanceBrokerInterface::class, $broker);

        // El usuario arranca en modo real con bot activo
        $this->user->update([
            'bot_mode' => 'real',
            'bot_active' => true,
        ]);

        // Simular que el caché de posición real tiene LONG
        \Illuminate\Support\Facades\Cache::put("user:{$this->user->id}:real_position", 'LONG');

        $response = $this->actingAs($this->user)
            ->post(route('bot.toggle-mode'));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertStringContainsString('Modo cambiado a: SIMULATION', session('success'));

        $this->user->refresh();
        $this->assertSame('simulation', $this->user->bot_mode);
        // El bot sigue activo localmente pero operará solo en simulación
        $this->assertTrue($this->user->bot_active);

        // La posición real en caché NO se debe haber alterado
        $this->assertSame('LONG', \Illuminate\Support\Facades\Cache::get("user:{$this->user->id}:real_position"));
    }

    /**
     * Test de la US07: Separación correcta de actividades reales y simuladas.
     */
    public function test_switching_to_simulation_mode_displays_simulation_activities_only(): void
    {
        // Crear una actividad en modo real
        $this->user->botActivities()->create([
            'bot_mode' => 'real',
            'type' => 'long',
            'action' => 'open_long',
        ]);

        // Crear una actividad en modo simulación
        $this->user->botActivities()->create([
            'bot_mode' => 'simulation',
            'type' => 'close',
            'action' => 'close_profit',
            'profit_percentage' => 1.5,
            'profit_value' => 15.00,
        ]);

        // Cuando el bot_mode es real, solo vemos la actividad real
        $this->user->update(['bot_mode' => 'real']);
        $response = $this->actingAs($this->user)->getJson(route('dashboard.activities'));
        $response->assertOk();
        $this->assertCount(1, $response->json('activities'));
        $this->assertSame('long', $response->json('activities.0.type'));

        // Cuando el bot_mode es simulación, el feed se deriva del historial de la
        // API externa (no de las filas simuladas persistidas en bot_activities).
        $mockProvider = $this->createMock(\App\Core\Contracts\SignalProviderInterface::class);
        $mockProvider->method('getSignalHistory')
            ->willReturn([
                [
                    'date' => '2026-06-10',
                    'time' => '10:00:00',
                    'position' => 'SHORT',
                    'profit' => 0.0,
                ],
                [
                    'date' => '2026-06-10',
                    'time' => '12:00:00',
                    'position' => 'CLOSE',
                    'profit' => 0.02,
                ],
            ]);
        $this->app->instance(\App\Core\Contracts\SignalProviderInterface::class, $mockProvider);

        $this->user->update(['bot_mode' => 'simulation']);
        $response = $this->actingAs($this->user)->getJson(route('dashboard.activities'));
        $response->assertOk();
        // Refleja las 2 señales de la API, no la única fila simulada en BD.
        $this->assertCount(2, $response->json('activities'));
        $this->assertSame('short', $response->json('activities.1.type'));
        $this->assertSame('close', $response->json('activities.0.type'));
    }

    /**
     * Al pasar a modo real con el bot activo, la posición se reconcilia con la
     * señal vigente (no se queda en CLOSE tras forzar el cierre del cambio).
     */
    public function test_changing_to_real_mode_with_active_bot_reconciles_with_current_signal(): void
    {
        Queue::fake();

        $provider = Mockery::mock(SignalProviderInterface::class);
        $provider->shouldReceive('getCurrentSignal')
            ->andReturn(['position' => 'LONG', 'issued_at' => now()->toIso8601String(), 'signal_id' => 'sig-test']);
        $this->app->instance(SignalProviderInterface::class, $provider);

        $this->user->update([
            'bot_mode' => 'simulation',
            'bot_active' => true,
            'risk_level' => 'balanceado',
        ]);

        $this->actingAs($this->user)
            ->post(route('bot.toggle-mode'))
            ->assertRedirect();

        $this->user->refresh();
        $this->assertSame('real', $this->user->bot_mode);

        Queue::assertPushed(
            AdjustPositionJob::class,
            fn (AdjustPositionJob $job) => $job->userId === $this->user->id && $job->newPosition === 'LONG'
        );
    }

    /**
     * Test de la US07: Bloqueo de modo real si el usuario no tiene Binance vinculado.
     */
    public function test_prevents_mode_change_to_real_without_binance_linked(): void
    {
        $unlinkedUser = User::factory()->create([
            'binance_verified' => false,
            'bot_mode' => 'simulation',
        ]);

        $response = $this->actingAs($unlinkedUser)
            ->post(route('bot.toggle-mode'));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Para activar el modo REAL debes tener una cuenta de Binance vinculada', session('error'));

        $unlinkedUser->refresh();
        $this->assertSame('simulation', $unlinkedUser->bot_mode);
    }

    /**
     * Al pausar el bot manualmente en modo simulación con una posición activa (LONG/SHORT),
     * se debe registrar el cierre con un 1.5% de beneficio en la tabla bot_activities.
     */
    public function test_manual_pause_in_simulation_mode_records_close_activity(): void
    {
        $this->user->update([
            'bot_active' => true,
            'bot_mode' => 'simulation',
            'risk_level' => 'balanceado',
        ]);

        // Simular que el balance actual es 1000 y que hay una posición activa en caché
        $this->user->balanceSnapshots()->create(['balance' => 1000.00, 'captured_at' => now()]);
        \Illuminate\Support\Facades\Cache::put("signal:last_known_position:balanceado", 'LONG');

        $response = $this->actingAs($this->user)
            ->post(route('bot.toggle'));

        $response->assertRedirect();
        $this->user->refresh();
        $this->assertFalse($this->user->bot_active);

        // Se debe haber registrado la actividad de cierre
        $this->assertDatabaseHas('bot_activities', [
            'user_id' => $this->user->id,
            'type' => 'close',
            'action' => 'close_profit',
            'profit_percentage' => 1.50,
            'profit_value' => 15.00,
        ]);

        // Se debe haber actualizado el balance snapshot a 1015 (1000 + 1.5%)
        $this->assertDatabaseHas('balance_snapshots', [
            'user_id' => $this->user->id,
            'balance' => 1015.00,
        ]);
    }

    /**
     * Al pausar el bot manualmente en modo real con una posición activa (LONG/SHORT),
     * se interactúa con el broker para cerrar, calcular profit real y registrarlo.
     */
    public function test_manual_pause_in_real_mode_records_close_activity_with_real_profit(): void
    {
        $this->user->update([
            'bot_active' => true,
            'bot_mode' => 'real',
            'risk_level' => 'balanceado',
        ]);

        // Guardar capital de apertura de 1000 y posición real actual en caché
        \Illuminate\Support\Facades\Cache::put("user:{$this->user->id}:open_capital", 1000.00);
        \Illuminate\Support\Facades\Cache::put("user:{$this->user->id}:real_position", 'SHORT');

        // Mock del Broker de Binance
        $broker = Mockery::mock(BinanceBrokerInterface::class);
        $broker->shouldReceive('closeOpenPositions')
            ->once()
            ->with($this->user->binance_api_key, $this->user->binance_secret_key)
            ->andReturn(true);
        $broker->shouldReceive('getTotalBalance')
            ->once()
            ->with($this->user->binance_api_key, $this->user->binance_secret_key)
            ->andReturn(1050.00); // Beneficio de +50 (+5%)

        $this->app->instance(BinanceBrokerInterface::class, $broker);

        $response = $this->actingAs($this->user)
            ->post(route('bot.toggle'));

        $response->assertRedirect();
        $this->user->refresh();
        $this->assertFalse($this->user->bot_active);

        // Se debe haber registrado la actividad de cierre real
        $this->assertDatabaseHas('bot_activities', [
            'user_id' => $this->user->id,
            'type' => 'close',
            'action' => 'close_profit',
            'profit_percentage' => 5.00,
            'profit_value' => 50.00,
        ]);

        // Sincronizar el nuevo balance
        $this->assertDatabaseHas('balance_snapshots', [
            'user_id' => $this->user->id,
            'balance' => 1050.00,
        ]);
    }

    /**
     * Si no hay posición abierta al pausar (CLOSE), no debe registrar ninguna actividad de cierre.
     */
    public function test_manual_pause_with_no_open_position_does_not_record_activity(): void
    {
        $this->user->update([
            'bot_active' => true,
            'bot_mode' => 'simulation',
            'risk_level' => 'balanceado',
        ]);

        // Simular que la posición ya era CLOSE
        \Illuminate\Support\Facades\Cache::put("signal:last_known_position:balanceado", 'CLOSE');

        $response = $this->actingAs($this->user)
            ->post(route('bot.toggle'));

        $response->assertRedirect();
        $this->user->refresh();
        $this->assertFalse($this->user->bot_active);

        // No se debe registrar actividad tipo close
        $this->assertDatabaseMissing('bot_activities', [
            'user_id' => $this->user->id,
            'type' => 'close',
        ]);
    }

    /**
     * Al activar el bot, se deben limpiar/desactivar las alertas de riesgo previas.
     */
    public function test_activating_bot_clears_previous_risk_alerts(): void
    {
        $this->user->update([
            'bot_active' => false,
            'bot_mode' => 'real',
        ]);

        // Crear una actividad con una alerta de riesgo activa
        $this->user->botActivities()->create([
            'bot_mode' => 'real',
            'type' => 'risk_protection',
            'action' => 'stop_loss_trigger',
            'risk_alert' => true,
        ]);

        // Asegurarnos que existe en base de datos con risk_alert = true
        $this->assertDatabaseHas('bot_activities', [
            'user_id' => $this->user->id,
            'risk_alert' => true,
        ]);

        // Activar el bot
        $response = $this->actingAs($this->user)
            ->post(route('bot.toggle'));

        $response->assertRedirect();
        $this->user->refresh();
        $this->assertTrue($this->user->bot_active);

        // La alerta de riesgo debe haberse desactivado (risk_alert = false)
        $this->assertDatabaseMissing('bot_activities', [
            'user_id' => $this->user->id,
            'risk_alert' => true,
        ]);
        $this->assertDatabaseHas('bot_activities', [
            'user_id' => $this->user->id,
            'risk_alert' => false,
        ]);
    }
}
