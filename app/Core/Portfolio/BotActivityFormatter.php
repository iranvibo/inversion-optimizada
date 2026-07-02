<?php

namespace App\Core\Portfolio;

use App\Models\BotActivity;

class BotActivityFormatter
{
    /**
     * Convierte la actividad del bot a un mensaje en lenguaje humano y coloquial (español).
     */
    public static function format(BotActivity $activity): string
    {
        $percentage = $activity->profit_percentage !== null
            ? number_format(abs($activity->profit_percentage), 2, ',', '.')
            : null;

        $value = $activity->profit_value !== null
            ? number_format(abs($activity->profit_value), 2, ',', '.')
            : null;

        switch ($activity->action) {
            case 'buy_opportunity':
            case 'open_long':
                return 'Se inició una inversión al alza (LONG) esperando una subida del precio.';

            case 'open_short':
                return 'Se inició una inversión a la baja (SHORT) esperando una caída del precio.';

            case 'close_profit':
                if ($percentage !== null && $value !== null) {
                    return "Inversión finalizada: posición cerrada con un +{$percentage}% de beneficio (+{$value}$).";
                }

                return 'Inversión finalizada: posición cerrada con beneficio.';

            case 'close_loss':
                if ($percentage !== null && $value !== null) {
                    return "Inversión finalizada: posición cerrada con una pérdida del {$percentage}% (-{$value}$).";
                }
                if ($value !== null) {
                    return "Inversión finalizada: posición cerrada con una pérdida de -{$value}$.";
                }

                return 'Inversión finalizada: posición cerrada con pérdida.';

            case 'stop_loss_trigger':
                return 'Protección diaria activada: El bot se pausó automáticamente para proteger tu capital.';

            case 'withdrawal_security_trigger':
                return 'Protección diaria activada: El bot se pausó automáticamente debido a permisos de retiro indebidos detectados.';

            default:
                return $activity->description ?? 'Acción del bot registrada.';
        }
    }
}
