<?php

namespace Tests\Unit;

use App\Core\Portfolio\BotActivityFormatter;
use App\Models\BotActivity;
use Tests\TestCase;

class BotActivityFormatterTest extends TestCase
{
    /**
     * Prueba que formatea correctamente la compra de oportunidad / long.
     */
    public function test_formats_buy_opportunity(): void
    {
        $activity = new BotActivity([
            'action' => 'open_long',
            'type' => 'long',
        ]);

        $message = BotActivityFormatter::format($activity);
        $this->assertSame('Se inició una inversión al alza (LONG) esperando una subida del precio.', $message);
    }

    /**
     * Prueba que formatea correctamente la venta corta / short.
     */
    public function test_formats_open_short(): void
    {
        $activity = new BotActivity([
            'action' => 'open_short',
            'type' => 'short',
        ]);

        $message = BotActivityFormatter::format($activity);
        $this->assertSame('Se inició una inversión a la baja (SHORT) esperando una caída del precio.', $message);
    }

    /**
     * Prueba que formatea correctamente el cierre con beneficio.
     */
    public function test_formats_close_profit(): void
    {
        $activity = new BotActivity([
            'action' => 'close_profit',
            'type' => 'close',
            'profit_percentage' => 1.55,
            'profit_value' => 15.50,
        ]);

        $message = BotActivityFormatter::format($activity);
        $this->assertSame('Inversión finalizada: posición cerrada con un +1,55% de beneficio (+15,50$).', $message);
    }

    /**
     * Prueba que formatea correctamente el cierre con pérdida (protección).
     */
    public function test_formats_close_loss(): void
    {
        $activity = new BotActivity([
            'action' => 'close_loss',
            'type' => 'close',
            'profit_percentage' => -1.00,
            'profit_value' => -10.00,
        ]);

        $message = BotActivityFormatter::format($activity);
        $this->assertSame('Inversión finalizada: posición cerrada con una pérdida del 1,00% (-10,00$).', $message);
    }

    /**
     * Prueba que formatea correctamente la alerta de stop-loss diario.
     */
    public function test_formats_stop_loss_trigger(): void
    {
        $activity = new BotActivity([
            'action' => 'stop_loss_trigger',
            'type' => 'risk_protection',
        ]);

        $message = BotActivityFormatter::format($activity);
        $this->assertSame('Protección diaria activada: El bot se pausó automáticamente para proteger tu capital.', $message);
    }

    /**
     * Prueba que formatea la alerta de auditoría de retiros.
     */
    public function test_formats_withdrawal_security_trigger(): void
    {
        $activity = new BotActivity([
            'action' => 'withdrawal_security_trigger',
            'type' => 'risk_protection',
        ]);

        $message = BotActivityFormatter::format($activity);
        $this->assertSame('Protección diaria activada: El bot se pausó automáticamente debido a permisos de retiro indebidos detectados.', $message);
    }

    /**
     * Prueba el valor por defecto si la acción no se conoce.
     */
    public function test_formats_default_fallback(): void
    {
        $activity = new BotActivity([
            'action' => 'unknown_action',
            'type' => 'system',
            'description' => 'Un evento del sistema desconocido.',
        ]);

        $message = BotActivityFormatter::format($activity);
        $this->assertSame('Un evento del sistema desconocido.', $message);
    }
}
