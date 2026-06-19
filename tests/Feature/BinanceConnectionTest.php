<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BinanceConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Forzamos el modo mock en la configuración para las pruebas
        config(['services.binance.mock' => true]);

        // Crear un usuario de prueba con el correo autorizado por defecto para simplificar los tests existentes
        $this->user = User::factory()->create([
            'email' => 'vicenteiran@gmail.com',
            'binance_verified' => false,
            'bot_active' => false,
            'bot_mode' => 'simulation',
        ]);
    }

    /**
     * Escenario 1: Detección de permisos de retiro activos durante la vinculación.
     */
    public function test_blocks_linking_when_withdrawals_are_enabled(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('binance.store'), [
                'binance_api_key' => 'withdrawals_enabled_key',
                'binance_secret_key' => 'my_secret_key_123',
            ]);

        // Debe redirigir de vuelta con el error de retiros activos
        $response->assertStatus(302);
        $response->assertSessionHas('withdrawal_error');

        // El usuario en base de datos NO debe quedar verificado
        $this->user->refresh();
        $this->assertFalse($this->user->binance_verified);
        $this->assertNull($this->user->binance_api_key);
    }

    /**
     * Escenario 2: Vinculación exitosa con permisos de retiro desactivados.
     */
    public function test_allows_linking_when_withdrawals_are_disabled(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('binance.store'), [
                'binance_api_key' => 'my_secure_api_key',
                'binance_secret_key' => 'my_secure_secret_key',
            ]);

        // Debe redirigir al dashboard con el banner verde de confirmación
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('security_verified', 'Seguridad Verificada: ViBo Invest no puede retirar tus fondos.');

        // El usuario en base de datos debe estar verificado y con las llaves guardadas
        $this->user->refresh();
        $this->assertTrue($this->user->binance_verified);
        $this->assertEquals('my_secure_api_key', $this->user->binance_api_key);
        $this->assertEquals('my_secure_secret_key', $this->user->binance_secret_key);
    }

    /**
     * Escenario 3: Verificación periódica en segundo plano.
     */
    public function test_periodic_background_check_pauses_bot_on_withdrawals_detected(): void
    {
        // Vincular previamente al usuario y activar el bot en modo real
        $this->user->update([
            'binance_api_key' => 'withdrawals_enabled_key', // API Key que simulará retiros activos
            'binance_secret_key' => 'my_secret_key_123',
            'binance_verified' => true,
            'bot_active' => true,
            'bot_mode' => 'real',
            'binance_withdrawal_alert' => false,
        ]);

        // Ejecutar el comando Artisan en segundo plano
        Artisan::call('binance:verify-permissions');

        // El usuario en base de datos debe tener el bot PAUSADO (bot_active = false)
        // y la alerta de retiros activa (binance_withdrawal_alert = true)
        $this->user->refresh();
        $this->assertFalse($this->user->bot_active);
        $this->assertTrue($this->user->binance_withdrawal_alert);
    }

    /**
     * Escenario 4: Un usuario no autorizado intenta vincular su cuenta de Binance.
     */
    public function test_blocks_linking_for_unauthorized_emails(): void
    {
        $unauthorizedUser = User::factory()->create([
            'email' => 'otheruser@example.com',
            'binance_verified' => false,
        ]);

        $response = $this->actingAs($unauthorizedUser)
            ->post(route('binance.store'), [
                'binance_api_key' => 'my_secure_api_key',
                'binance_secret_key' => 'my_secure_secret_key',
            ]);

        // Debe redirigir de vuelta con el error específico
        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'binance_api_key' => 'No se ha podido conectar.'
        ]);

        // El usuario no autorizado no debe quedar verificado ni tener las claves guardadas
        $unauthorizedUser->refresh();
        $this->assertFalse($unauthorizedUser->binance_verified);
        $this->assertNull($unauthorizedUser->binance_api_key);
    }
}
