<?php

namespace Tests\Feature;

use App\Models\InvitationCode;
use App\Models\User;
use App\Services\FirebaseTokenVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Pruebas del flujo de autenticación ampliado (US01):
 * registro con contraseña, aceptación de política de privacidad,
 * inicio de sesión con Google (Firebase) y registro sólo con invitación.
 */
class AuthRegistrationTest extends TestCase
{
    use RefreshDatabase;

    // ─── Registro con correo y contraseña ────────────────────────────────

    public function test_user_can_register_with_valid_data(): void
    {
        $code = $this->issueInvitation('ada@example.com');

        $response = $this->post(route('register'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'invitation_code' => $code,
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

        // El código queda marcado como canjeado por el nuevo usuario.
        $invitation = InvitationCode::where('email', 'ada@example.com')->first();
        $this->assertNotNull($invitation->used_at);
        $this->assertSame($user->id, $invitation->used_by);
    }

    public function test_registration_requires_invitation_code(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'privacy' => '1',
            'terms' => '1',
        ]);

        $response->assertSessionHasErrors('invitation_code');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
    }

    public function test_registration_rejects_unknown_invitation_code(): void
    {
        $response = $this->post(route('register'), $this->registrationPayload([
            'invitation_code' => 'AAAA-BBBB-CCCC-DDDD',
        ]));

        $response->assertSessionHasErrors('invitation_code');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
    }

    public function test_registration_rejects_code_issued_for_another_email(): void
    {
        $code = $this->issueInvitation('otra-persona@example.com');

        $response = $this->post(route('register'), $this->registrationPayload([
            'invitation_code' => $code,
        ]));

        $response->assertSessionHasErrors('invitation_code');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
    }

    public function test_registration_rejects_expired_code(): void
    {
        $code = $this->issueInvitation('ada@example.com');
        InvitationCode::query()->update(['expires_at' => now()->subMinute()]);

        $response = $this->post(route('register'), $this->registrationPayload([
            'invitation_code' => $code,
        ]));

        $response->assertSessionHasErrors('invitation_code');
        $this->assertGuest();
    }

    public function test_registration_rejects_already_used_code(): void
    {
        $code = $this->issueInvitation('ada@example.com');
        InvitationCode::query()->update([
            'used_at' => now(),
            'used_by' => User::factory()->create()->id,
        ]);

        $response = $this->post(route('register'), $this->registrationPayload([
            'invitation_code' => $code,
        ]));

        $response->assertSessionHasErrors('invitation_code');
        $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
    }

    public function test_invitation_code_is_forgiving_with_format(): void
    {
        // Minúsculas, sin guiones y con espacios alrededor: debe aceptarse.
        $code = $this->issueInvitation('ada@example.com');
        $sloppy = ' '.strtolower(str_replace('-', '', $code)).' ';

        $response = $this->post(route('register'), $this->registrationPayload([
            'invitation_code' => $sloppy,
        ]));

        $response->assertRedirect(route('onboarding.show'));
        $this->assertAuthenticated();
    }

    public function test_register_page_prefills_invitation_code_from_query_string(): void
    {
        // La URL compartible (register_url de la API) precarga el campo.
        $this->get(route('register', ['invitation' => 'K7KM-W3QP-9TXR-4HAB']))
            ->assertOk()
            ->assertSee('value="K7KM-W3QP-9TXR-4HAB"', false);
    }

    public function test_registration_requires_privacy_acceptance(): void
    {
        $response = $this->post(route('register'), $this->registrationPayload([
            'invitation_code' => $this->issueInvitation('ada@example.com'),
            'privacy' => null,
        ]));

        $response->assertSessionHasErrors('privacy');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
    }

    public function test_registration_requires_terms_acceptance(): void
    {
        $response = $this->post(route('register'), $this->registrationPayload([
            'invitation_code' => $this->issueInvitation('ada@example.com'),
            'terms' => null,
        ]));

        $response->assertSessionHasErrors('terms');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
    }

    public function test_registration_rejects_weak_or_mismatched_password(): void
    {
        $this->post(route('register'), $this->registrationPayload([
            'invitation_code' => $this->issueInvitation('ada@example.com'),
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ]))->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    // ─── Inicio de sesión con Google (Firebase) ──────────────────────────

    public function test_google_login_creates_user_with_valid_invitation(): void
    {
        $code = $this->issueInvitation('nuevo@gmail.com');

        $this->mockVerifier([
            'sub' => 'firebase-uid-123',
            'email' => 'nuevo@gmail.com',
            'email_verified' => true,
            'name' => 'Nuevo Usuario',
            'picture' => 'https://example.com/photo.png',
        ]);

        $response = $this->postJson(route('auth.google'), [
            'id_token' => 'fake-token',
            'invitation_code' => $code,
        ]);

        $response->assertOk()->assertJsonStructure(['redirect']);
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'nuevo@gmail.com',
            'firebase_uid' => 'firebase-uid-123',
        ]);
    }

    public function test_google_registration_requires_invitation_code(): void
    {
        $this->mockVerifier([
            'sub' => 'firebase-uid-123',
            'email' => 'nuevo@gmail.com',
            'email_verified' => true,
            'name' => 'Nuevo Usuario',
        ]);

        $this->postJson(route('auth.google'), ['id_token' => 'fake-token'])
            ->assertStatus(422);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'nuevo@gmail.com']);
    }

    public function test_google_registration_rejects_invitation_for_another_email(): void
    {
        $code = $this->issueInvitation('otra-persona@example.com');

        $this->mockVerifier([
            'sub' => 'firebase-uid-123',
            'email' => 'nuevo@gmail.com',
            'email_verified' => true,
            'name' => 'Nuevo Usuario',
        ]);

        $this->postJson(route('auth.google'), [
            'id_token' => 'fake-token',
            'invitation_code' => $code,
        ])->assertStatus(422);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'nuevo@gmail.com']);
    }

    public function test_google_login_links_existing_email_account_without_invitation(): void
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

        // Iniciar sesión en una cuenta ya creada no requiere invitación.
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
        // de la cuenta de correo existente vinculándose a ella, ni crear una
        // cuenta nueva (sin email verificado no se puede casar la invitación).
        $this->mockVerifier([
            'sub' => 'firebase-uid-789',
            'email' => 'victima@gmail.com',
            'email_verified' => false,
            'name' => 'Atacante',
        ]);

        $this->postJson(route('auth.google'), ['id_token' => 'fake-token'])
            ->assertStatus(422);

        // La cuenta original sigue sin identidad de Google vinculada.
        $this->assertNull($existing->fresh()->firebase_uid);
        $this->assertGuest();
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

    // ─── Auxiliares ───────────────────────────────────────────────────────

    /**
     * Emite un código de invitación válido para el email dado y devuelve el
     * código en claro, como haría la API interna de invitaciones.
     */
    private function issueInvitation(string $email): string
    {
        return InvitationCode::issueFor($email)['code'];
    }

    /**
     * Payload completo de registro tradicional; las claves con valor null se
     * eliminan para simular campos ausentes.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function registrationPayload(array $overrides = []): array
    {
        return array_filter(array_merge([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'privacy' => '1',
            'terms' => '1',
        ], $overrides), fn ($value) => $value !== null);
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
