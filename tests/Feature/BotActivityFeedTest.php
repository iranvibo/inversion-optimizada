<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\BotActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotActivityFeedTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'binance_api_key' => 'my_secure_api_key',
            'binance_secret_key' => 'my_secure_secret',
            'binance_verified' => true,
        ]);
    }

    /**
     * Prueba que las rutas requieran autenticación.
     */
    public function test_activity_routes_require_authentication(): void
    {
        $this->get(route('dashboard.activities'))->assertRedirect(route('login'));
        $this->post(route('bot.simulate-activity'))->assertRedirect(route('login'));
    }

    /**
     * Prueba que el endpoint JSON devuelva el feed de actividades formateado.
     */
    public function test_activities_endpoint_returns_json_feed(): void
    {
        // Creamos actividades de prueba
        $this->user->botActivities()->create([
            'type' => 'long',
            'action' => 'open_long',
        ]);

        $this->user->botActivities()->create([
            'type' => 'close',
            'action' => 'close_profit',
            'profit_percentage' => 1.5,
            'profit_value' => 15.00,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('dashboard.activities'));

        $response->assertOk();
        $response->assertJsonStructure([
            'activities' => [
                '*' => [
                    'id',
                    'type',
                    'action',
                    'human_description',
                    'profit_percentage',
                    'profit_value',
                    'risk_alert',
                    'created_at',
                ]
            ]
        ]);

        $data = $response->json('activities');
        $this->assertCount(2, $data);
        $this->assertSame('Se inició una inversión al alza (LONG) esperando una subida del precio.', $data[1]['human_description']);
        $this->assertSame('Inversión finalizada: posición cerrada con un +1,50% de beneficio (+15,00€).', $data[0]['human_description']);
    }

    /**
     * Prueba que el endpoint de simulación siembre los 4 eventos clave.
     */
    public function test_simulate_activity_endpoint_seeds_events_and_redirects(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('bot.simulate-activity'));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Simulación de Actividad exitosa. Se han generado 4 eventos en tu historial.');
        $response->assertSessionHas('active_tab', 'activity');

        // Verificar la persistencia en base de datos
        $this->assertDatabaseCount('bot_activities', 4);

        $activities = $this->user->botActivities()->orderBy('created_at', 'desc')->get();
        
        // El último evento creado es la alerta de protección de riesgo (Stop loss)
        $this->assertSame('stop_loss_trigger', $activities[0]->action);
        $this->assertTrue($activities[0]->risk_alert);
        $this->assertSame('Protección diaria activada: El bot se pausó automáticamente para proteger tu capital.', $activities[0]->human_description);

        // Tercer evento: close_loss
        $this->assertSame('close_loss', $activities[1]->action);
        $this->assertSame('Inversión finalizada: protección de pérdida activada para asegurar tu capital (-10,00€).', $activities[1]->human_description);

        // Segundo evento: close_profit
        $this->assertSame('close_profit', $activities[2]->action);
        $this->assertSame('Inversión finalizada: posición cerrada con un +1,50% de beneficio (+15,00€).', $activities[2]->human_description);

        // Primer evento: open_long
        $this->assertSame('open_long', $activities[3]->action);
        $this->assertSame('Se inició una inversión al alza (LONG) esperando una subida del precio.', $activities[3]->human_description);
    }

    /**
     * Prueba que la vista del dashboard renderiza las actividades cargadas del servidor.
     */
    public function test_dashboard_renders_activities_and_risk_alerts(): void
    {
        // Crear una alerta de riesgo y una compra
        $this->user->botActivities()->create([
            'type' => 'risk_protection',
            'action' => 'stop_loss_trigger',
            'risk_alert' => true,
        ]);

        $this->user->botActivities()->create([
            'type' => 'long',
            'action' => 'open_long',
            'risk_alert' => false,
        ]);

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('ALERTA DE PROTECCIÓN DE RIESGO');
        $response->assertSee('Protección diaria activada: El bot se pausó automáticamente para proteger tu capital.');
        $response->assertSee('Se inició una inversión al alza (LONG) esperando una subida del precio.');
    }
}
