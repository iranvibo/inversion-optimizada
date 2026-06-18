<?php

namespace Tests\Feature;

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
     * En modo simulación la activación no encola ajustes reales (no toca Binance).
     */
    public function test_activation_in_simulation_mode_does_not_dispatch_adjust(): void
    {
        Event::fake([BotStatusUpdated::class]);
        Queue::fake();

        // El usuario por defecto está en modo simulación.
        $this->actingAs($this->user)->post(route('bot.toggle'))->assertRedirect();

        Queue::assertNotPushed(AdjustPositionJob::class);
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
     * Requisito de seguridad al activar en modo REAL
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
        $this->assertStringContainsString('Para activar el bot en modo REAL debes vincular', session('error'));

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
     * Test de la US07: Alternancia de modo real a simulación con bot activo y Binance conectado.
     */
    public function test_changes_mode_from_real_to_simulation_and_closes_positions(): void
    {
        // El usuario arranca en modo real con bot activo
        $this->user->update([
            'bot_mode' => 'real',
            'bot_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('bot.toggle-mode'));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertStringContainsString('Modo cambiado a: SIMULATION', session('success'));

        $this->user->refresh();
        $this->assertSame('simulation', $this->user->bot_mode);
        // El bot sigue activo localmente pero operará solo en simulación
        $this->assertTrue($this->user->bot_active);
    }

    /**
     * Test de la US07: Fail-Safe al cambiar a simulación cuando el broker de Binance falla.
     */
    public function test_changes_mode_from_real_to_simulation_even_if_broker_fails(): void
    {
        // Usamos una clave que cause fallo al cerrar
        $this->user->update([
            'bot_mode' => 'real',
            'bot_active' => true,
            'binance_api_key' => 'fail_close_key',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('bot.toggle-mode'));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('El modo cambió a simulación, pero hubo un problema al cerrar posiciones', session('error'));

        $this->user->refresh();
        $this->assertSame('simulation', $this->user->bot_mode);
        $this->assertTrue($this->user->bot_active);
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
}
