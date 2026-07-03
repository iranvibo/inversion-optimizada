<?php

namespace Tests\Feature;

use App\Core\Contracts\BinanceBrokerInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crea el usuario administrador (email de config 'app.admin_email').
     */
    private function createAdmin(): User
    {
        return User::factory()->create([
            'email' => config('app.admin_email'),
        ]);
    }

    /**
     * Un invitado (guest) es redirigido al login.
     */
    public function test_guest_cannot_access_admin_users(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    }

    /**
     * Un usuario normal no puede acceder a la administración de usuarios.
     */
    public function test_non_admin_cannot_access_admin_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($user)->delete(route('admin.users.destroy', $other))->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $other->id]);
    }

    /**
     * El administrador ve el listado completo de usuarios sin campos sensibles.
     */
    public function test_admin_can_list_all_users(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create([
            'binance_api_key' => 'my_api_key',
            'binance_secret_key' => 'my_secret_key',
            'binance_verified' => true,
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.users.index'));

        $response->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(2, 'users')
            ->assertJsonFragment(['email' => $user->email, 'broker_linked' => true])
            ->assertJsonFragment(['email' => $admin->email, 'is_admin' => true]);

        // Nunca se exponen credenciales ni contraseñas
        $response->assertJsonMissingPath('users.0.password');
        $this->assertStringNotContainsString('my_api_key', $response->getContent());
        $this->assertStringNotContainsString('binance_api_key', $response->getContent());
    }

    /**
     * El dashboard muestra la pestaña "Usuarios" con el contador solo al admin.
     */
    public function test_users_tab_is_only_visible_to_admin(): void
    {
        $admin = $this->createAdmin();
        User::factory()->count(2)->create();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="tab-btn-users"', false)
            ->assertSeeInOrder(['Usuarios (', '3', ')']);

        $regular = User::factory()->create();
        $this->actingAs($regular)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('id="tab-btn-users"', false);
    }

    /**
     * El administrador puede eliminar definitivamente a otro usuario.
     */
    public function test_admin_can_delete_another_user(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create(['bot_active' => false]);

        $response = $this->actingAs($admin)->deleteJson(route('admin.users.destroy', $user));

        $response->assertOk()->assertJsonPath('total', 1);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    /**
     * Antes de eliminar a un usuario con el bot activo y Binance vinculado se
     * cierran preventivamente sus posiciones abiertas (misma garantía que la
     * auto-eliminación GDPR).
     */
    public function test_admin_deletion_closes_open_positions_first(): void
    {
        $admin = $this->createAdmin();
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

        $this->actingAs($admin)->deleteJson(route('admin.users.destroy', $user))->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    /**
     * El administrador no puede eliminarse a sí mismo desde la administración.
     */
    public function test_admin_cannot_delete_own_account_from_admin_panel(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->deleteJson(route('admin.users.destroy', $admin));

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
