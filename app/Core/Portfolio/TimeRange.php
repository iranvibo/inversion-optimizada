<?php

namespace App\Core\Portfolio;

use DateTimeImmutable;

/**
 * Filtro temporal del gráfico de evolución del balance (US03, Escenario 2).
 */
enum TimeRange: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    /**
     * Inicio de la ventana temporal relativa al instante indicado.
     */
    public function startAt(DateTimeImmutable $now): DateTimeImmutable
    {
        return match ($this) {
            self::Day => $now->modify('-1 day'),
            self::Week => $now->modify('-7 days'),
            self::Month => $now->modify('-1 month'),
        };
    }

    /**
     * Etiqueta en lenguaje humano para componer mensajes de cambio.
     */
    public function label(): string
    {
        return match ($this) {
            self::Day => 'el último día',
            self::Week => 'la última semana',
            self::Month => 'el último mes',
        };
    }
}
