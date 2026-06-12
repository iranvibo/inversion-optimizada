<?php

namespace Tests\Feature;

use App\Events\BotStatusUpdated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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
}
