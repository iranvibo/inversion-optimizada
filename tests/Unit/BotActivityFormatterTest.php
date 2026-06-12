<?php

namespace Tests\Unit;

use App\Core\Portfolio\BotActivityFormatter;
use App\Models\BotActivity;
use Tests\TestCase;

class BotActivityFormatterTest extends TestCase
{
    /**
     * Prueba que formatea correctamente la compra de oportunidad.
     */
    public function test_formats_buy_opportunity(): void
    {
        $activity = new BotActivity([
            'action' => 'buy_opportunity',
            'type' => 'buy',
        ]);

        $message = BotActivityFormatter::format($activity);
        $this->assertSame('Se realizó una compra para aprovechar una caída temporal de precio.', $message);
    }

    /**
     * Prueba que formatea correctamente el cierre con beneficio.
     */
    public function test_formats_close_profit(): void
    {
        $activity = new BotActivity([
            'action' => 'close_profit',
            'type' => 'sell',
            'profit_percentage' => 1.55,
            'profit_value' => 15.50,
        ]);

        $message = BotActivityFormatter::format($activity);
        $this->assertSame('Posición cerrada con un +1,55% de beneficio (+15,50€).', $message);
    }

    /**
     * Prueba que formatea correctamente el cierre con pérdida (protección).
     */
    public function test_formats_close_loss(): void
    {
        $activity = new BotActivity([
            'action' => 'close_loss',
            'type' => 'sell',
            'profit_percentage' => -1.00,
            'profit_value' => -10.00,
        ]);

        $message = BotActivityFormatter::format($activity);
        $this->assertSame('Protección de pérdida activada para asegurar tu capital (-10,00€).', $message);
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
