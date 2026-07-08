<?php

namespace Tests\Feature;

use App\Core\Contracts\BinanceBrokerInterface;
use App\Models\BalanceSnapshot;
use App\Models\BotActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Un invitado (guest) no puede eliminar su cuenta.
     */
    public function test_guest_cannot_delete_account(): void
    {
        $response = $this->delete(route('account.delete'));

        $response->assertRedirect(route('login'));
    }

    /**
     * Un usuario autenticado puede eliminar su cuenta de forma permanente,
     * lo cual borra sus datos en cascada y cierra su sesión en Laravel.
     */
    public function test_authenticated_user_can_delete_account(): void
    {
        $user = User::factory()->create([
            'bot_active' => false,
        ]);

        // Sembrar algunos registros dependientes para probar el borrado en cascada
        BalanceSnapshot::create([
            'user_id' => $user->id,
            'balance' => 500.00,
            'captured_at' => now(),
        ]);

        $user->botActivities()->create([
            'bot_mode' => 'simulation',
            'type' => 'close',
            'action' => 'close_profit',
            'description' => 'Test description',
        ]);

        // Verificar pre-condiciones en la BD
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('balance_snapshots', ['user_id' => $user->id]);
        $this->assertDatabaseHas('bot_activities', ['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->delete(route('account.delete'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success', 'Tu cuenta y todos tus datos personales han sido eliminados de forma permanente.');

        // Verifica que la sesión se cerró
        $this->assertGuest();

        // Verifica que los datos del usuario se borraron de forma definitiva
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('balance_snapshots', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('bot_activities', ['user_id' => $user->id]);
    }

    /**
     * Si el bot del usuario está activo y tiene Binance conectado, se debe
     * intentar cerrar de forma preventiva las posiciones abiertas antes de
     * eliminar definitivamente la cuenta.
     */
    public function test_closing_binance_positions_preventively_on_deletion_when_bot_is_active(): void
    {
        $user = User::factory()->create([
            'binance_api_key' => 'my_api_key',
            'binance_secret_key' => 'my_secret_key',
            'binance_verified' => true,
            'bot_active' => true,
        ]);

        $brokerMock = Mockery::mock(BinanceBrokerInterface::class);
        $brokerMock->shouldReceive('closeOpenPositions')
            ->once()
            ->with($user->binance_api_key, $user->binance_secret_key)
            ->andReturn(true);

        $this->app->instance(BinanceBrokerInterface::class, $brokerMock);

        $response = $this->actingAs($user)
            ->delete(route('account.delete'));

        $response->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    /**
     * Si falla el cierre en Binance, se registra el fallo pero se continúa
     * de todos modos con la eliminación de los datos personales (cumplimiento GDPR).
     */
    public function test_deletion_continues_even_if_binance_broker_throws_exception(): void
    {
        $user = User::factory()->create([
            'binance_api_key' => 'my_api_key',
            'binance_secret_key' => 'my_secret_key',
            'binance_verified' => true,
            'bot_active' => true,
        ]);

        $brokerMock = Mockery::mock(BinanceBrokerInterface::class);
        $brokerMock->shouldReceive('closeOpenPositions')
            ->once()
            ->with($user->binance_api_key, $user->binance_secret_key)
            ->andThrow(new \Exception('Binance API is down'));

        $this->app->instance(BinanceBrokerInterface::class, $brokerMock);

        $response = $this->actingAs($user)
            ->delete(route('account.delete'));

        $response->assertRedirect(route('login'));

        // Los datos deben eliminarse de igual manera por cumplimiento de GDPR
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
