<?php

namespace Tests\Feature;

use App\Core\Contracts\BinanceBrokerInterface;
use App\Core\Exceptions\BinanceInvalidCredentialsException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Pruebas de integración de la auditoría periódica de permisos de Binance.
 *
 * Verifica el pilar de seguridad: el bot se pausa si la API Key habilita
 * retiros o si las credenciales fueron revocadas.
 */
class VerifyBinancePermissionsTest extends TestCase
{
    use RefreshDatabase;

    /** @var \Mockery\MockInterface&BinanceBrokerInterface */
    protected $broker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->broker = Mockery::mock(BinanceBrokerInterface::class);
        $this->app->instance(BinanceBrokerInterface::class, $this->broker);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function realActiveUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'binance_api_key' => 'key',
            'binance_secret_key' => 'secret',
            'binance_verified' => true,
            'bot_active' => true,
            'bot_mode' => 'real',
            'binance_withdrawal_alert' => false,
            'risk_level' => 'balanceado',
        ], $overrides));
    }

    public function test_does_nothing_when_no_eligible_users(): void
    {
        $this->broker->shouldNotReceive('checkApiRestrictions');

        $this->artisan('binance:verify-permissions')
            ->expectsOutputToContain('No hay usuarios activos en modo real para auditar.')
            ->assertExitCode(0);
    }

    public function test_pauses_bot_and_alerts_when_withdrawals_enabled(): void
    {
        $user = $this->realActiveUser();

        $this->broker->shouldReceive('checkApiRestrictions')
            ->once()
            ->with($user->binance_api_key, $user->binance_secret_key)
            ->andReturn(['enableWithdrawals' => true]);

        $this->artisan('binance:verify-permissions')->assertExitCode(0);

        $user->refresh();
        $this->assertFalse($user->bot_active, 'El bot debe pausarse si se detectan permisos de retiro.');
        $this->assertTrue($user->binance_withdrawal_alert, 'Debe activarse la alerta de retiro.');
    }

    public function test_keeps_bot_active_when_restrictions_are_safe(): void
    {
        $user = $this->realActiveUser();

        $this->broker->shouldReceive('checkApiRestrictions')
            ->once()
            ->andReturn(['enableWithdrawals' => false]);

        $this->artisan('binance:verify-permissions')->assertExitCode(0);

        $user->refresh();
        $this->assertTrue($user->bot_active);
        $this->assertFalse($user->binance_withdrawal_alert);
    }

    public function test_pauses_bot_when_credentials_are_invalid(): void
    {
        $user = $this->realActiveUser();

        $this->broker->shouldReceive('checkApiRestrictions')
            ->once()
            ->andThrow(new BinanceInvalidCredentialsException());

        $this->artisan('binance:verify-permissions')->assertExitCode(0);

        $user->refresh();
        $this->assertFalse($user->bot_active, 'Credenciales revocadas deben pausar el bot.');
        // Las credenciales inválidas no son una alerta de retiro.
        $this->assertFalse($user->binance_withdrawal_alert);
    }

    public function test_transient_errors_do_not_pause_bot(): void
    {
        $user = $this->realActiveUser();

        $this->broker->shouldReceive('checkApiRestrictions')
            ->once()
            ->andThrow(new \RuntimeException('Timeout de red'));

        $this->artisan('binance:verify-permissions')->assertExitCode(0);

        $user->refresh();
        $this->assertTrue($user->bot_active, 'Un error temporal de red no debe pausar el bot en modo real.');
    }

    public function test_only_audits_real_mode_verified_active_users(): void
    {
        // Estos usuarios NO deben auditarse.
        $this->realActiveUser(['bot_active' => false]);              // bot pausado
        $this->realActiveUser(['bot_mode' => 'simulation']);        // modo simulación
        $this->realActiveUser(['binance_verified' => false]);       // sin verificar

        // Solo este usuario es elegible.
        $eligible = $this->realActiveUser();

        $this->broker->shouldReceive('checkApiRestrictions')
            ->once()
            ->with($eligible->binance_api_key, $eligible->binance_secret_key)
            ->andReturn(['enableWithdrawals' => false]);

        $this->artisan('binance:verify-permissions')->assertExitCode(0);
    }
}
