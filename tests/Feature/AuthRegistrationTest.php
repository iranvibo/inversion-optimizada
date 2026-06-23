<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FirebaseTokenVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Pruebas del flujo de autenticación ampliado (US01):
 * registro con contraseña, aceptación de política de privacidad e
 * inicio de sesión con Google (Firebase).
 */
class AuthRegistrationTest extends TestCase
{
    use RefreshDatabase;

    // ─── Registro con correo y contraseña ────────────────────────────────

    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'privacy' => '1',
            'terms' => '1',
        ]);

        // Usuario nuevo: pasa primero por el onboarding.
        $response->assertRedirect(route('onboarding.show'));
        $this->assertAuthenticated();

        $user = User::where('email', 'ada@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->accepted_privacy_at);
        $this->assertNotNull($user->accepted_terms_at);
    }

    public function test_registration_requires_privacy_acceptance(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'terms' => '1',
        ]);

        $response->assertSessionHasErrors('privacy');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
    }

    public function test_registration_requires_terms_acceptance(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'privacy' => '1',
        ]);

        $response->assertSessionHasErrors('terms');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
    }

    public function test_registration_rejects_weak_or_mismatched_password(): void
    {
        $this->post(route('register'), [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
            'privacy' => '1',
            'terms' => '1',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    // ─── Inicio de sesión con Google (Firebase) ──────────────────────────

    public function test_google_login_creates_user_from_verified_token(): void
    {
        $this->mockVerifier([
            'sub' => 'firebase-uid-123',
            'email' => 'nuevo@gmail.com',
            'email_verified' => true,
            'name' => 'Nuevo Usuario',
            'picture' => 'https://example.com/photo.png',
        ]);

        $response = $this->postJson(route('auth.google'), ['id_token' => 'fake-token']);

        $response->assertOk()->assertJsonStructure(['redirect']);
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'nuevo@gmail.com',
            'firebase_uid' => 'firebase-uid-123',
        ]);
    }

    public function test_google_login_links_existing_email_account(): void
    {
        $existing = User::factory()->create([
            'email' => 'existente@gmail.com',
            'firebase_uid' => null,
        ]);

        $this->mockVerifier([
            'sub' => 'firebase-uid-456',
            'email' => 'existente@gmail.com',
            'email_verified' => true,
            'name' => 'Existente',
        ]);

        $this->postJson(route('auth.google'), ['id_token' => 'fake-token'])->assertOk();

        $this->assertEquals('firebase-uid-456', $existing->fresh()->firebase_uid);
        $this->assertSame($existing->id, Auth::id());
    }

    public function test_google_login_does_not_link_account_when_email_unverified(): void
    {
        $existing = User::factory()->create([
            'email' => 'victima@gmail.com',
            'firebase_uid' => null,
        ]);

        // Token con un email NO verificado por Google: no debe poder apropiarse
        // de la cuenta de correo existente vinculándose a ella.
        $this->mockVerifier([
            'sub' => 'firebase-uid-789',
            'email' => 'victima@gmail.com',
            'email_verified' => false,
            'name' => 'Atacante',
        ]);

        $this->postJson(route('auth.google'), ['id_token' => 'fake-token']);

        // La cuenta original sigue sin identidad de Google vinculada.
        $this->assertNull($existing->fresh()->firebase_uid);
    }

    public function test_google_login_rejects_invalid_token(): void
    {
        $this->mock(FirebaseTokenVerifier::class, function ($mock) {
            $mock->shouldReceive('verify')->andThrow(new \RuntimeException('inválido'));
        });

        $this->postJson(route('auth.google'), ['id_token' => 'bad-token'])
            ->assertStatus(422);

        $this->assertGuest();
    }

    /**
     * Sustituye el verificador real por un doble que devuelve los claims dados.
     *
     * @param  array<string, mixed>  $claims
     */
    private function mockVerifier(array $claims): void
    {
        $this->mock(FirebaseTokenVerifier::class, function ($mock) use ($claims) {
            $mock->shouldReceive('verify')->andReturn($claims);
        });
    }
}
