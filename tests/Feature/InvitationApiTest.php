<?php

namespace Tests\Feature;

use App\Models\InvitationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de la API interna de invitaciones (POST /api/invitations):
 * autenticación por token Bearer, emisión de códigos y sus invariantes
 * (hash en reposo, un código activo por email, caducidad).
 */
class InvitationApiTest extends TestCase
{
    use RefreshDatabase;

    private const API_TOKEN = 'test-invitation-token';

    protected function setUp(): void
    {
        parent::setUp();

        config(['invitations.api_token' => self::API_TOKEN]);
    }

    public function test_api_generates_invitation_code_for_email(): void
    {
        $response = $this->postJson(route('api.invitations.store'), [
            'email' => 'Invitada@Example.com',
        ], ['Authorization' => 'Bearer '.self::API_TOKEN]);

        $response->assertCreated()->assertJsonStructure(['email', 'code', 'expires_at']);

        // El email se normaliza y el código en claro nunca se persiste.
        $this->assertSame('invitada@example.com', $response->json('email'));
        $invitation = InvitationCode::where('email', 'invitada@example.com')->first();
        $this->assertNotNull($invitation);
        $this->assertNotSame($response->json('code'), $invitation->code_hash);
        $this->assertNull($invitation->used_at);

        // Y el código emitido es canjeable para ese email.
        $this->assertNotNull(
            InvitationCode::findRedeemable('invitada@example.com', $response->json('code'))
        );
    }

    public function test_api_requires_bearer_token(): void
    {
        $this->postJson(route('api.invitations.store'), ['email' => 'a@b.com'])
            ->assertUnauthorized();

        $this->postJson(route('api.invitations.store'), ['email' => 'a@b.com'], [
            'Authorization' => 'Bearer token-incorrecto',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('invitation_codes', 0);
    }

    public function test_api_fails_closed_when_token_not_configured(): void
    {
        config(['invitations.api_token' => null]);

        $this->postJson(route('api.invitations.store'), ['email' => 'a@b.com'], [
            'Authorization' => 'Bearer '.self::API_TOKEN,
        ])->assertServiceUnavailable();

        $this->assertDatabaseCount('invitation_codes', 0);
    }

    public function test_api_validates_email(): void
    {
        $this->postJson(route('api.invitations.store'), ['email' => 'no-es-un-email'], [
            'Authorization' => 'Bearer '.self::API_TOKEN,
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_api_revokes_previous_unused_codes_for_same_email(): void
    {
        $first = InvitationCode::issueFor('invitada@example.com')['code'];

        $this->postJson(route('api.invitations.store'), [
            'email' => 'invitada@example.com',
        ], ['Authorization' => 'Bearer '.self::API_TOKEN])->assertCreated();

        // Sólo queda un código activo: el anterior deja de ser canjeable.
        $this->assertDatabaseCount('invitation_codes', 1);
        $this->assertNull(InvitationCode::findRedeemable('invitada@example.com', $first));
    }

    public function test_api_rejects_email_with_existing_account(): void
    {
        User::factory()->create(['email' => 'registrada@example.com']);

        $this->postJson(route('api.invitations.store'), [
            'email' => 'registrada@example.com',
        ], ['Authorization' => 'Bearer '.self::API_TOKEN])->assertStatus(409);

        $this->assertDatabaseCount('invitation_codes', 0);
    }

    public function test_api_honors_custom_expiry(): void
    {
        $response = $this->postJson(route('api.invitations.store'), [
            'email' => 'invitada@example.com',
            'expires_in_days' => 1,
        ], ['Authorization' => 'Bearer '.self::API_TOKEN]);

        $response->assertCreated();

        $expiresAt = InvitationCode::firstOrFail()->expires_at;
        $this->assertTrue($expiresAt->between(now()->addHours(23), now()->addHours(25)));
    }
}
