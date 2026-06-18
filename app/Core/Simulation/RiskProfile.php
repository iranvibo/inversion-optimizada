<?php

namespace App\Core\Simulation;

/**
 * Entidad de dominio: Perfil de riesgo del inversor.
 *
 * Los textos descriptivos son negociables (criterio N de INVEST),
 * por eso viven centralizados aquí y no dispersos en las vistas.
 */
enum RiskProfile: string
{
    case Conservador = 'conservador';
    case Balanceado = 'balanceado';
    case Agresivo = 'agresivo';

    /**
     * Etiqueta legible para la interfaz.
     */
    public function label(): string
    {
        return match ($this) {
            self::Conservador => 'Conservador',
            self::Balanceado => 'Balanceado',
            self::Agresivo => 'Agresivo',
        };
    }

    /**
     * Descripción honesta del perfil (transparencia ante el usuario).
     */
    public function description(): string
    {
        return match ($this) {
            self::Conservador => 'Prioriza preservar tu capital. Crecimiento moderado con caídas temporales pequeñas de hasta un 15%.',
            self::Balanceado => 'Equilibrio entre crecimiento y estabilidad. Asume caídas temporales moderadas de hasta un 30%.',
            self::Agresivo => 'Busca el máximo crecimiento. Debes tolerar caídas temporales pronunciadas de hasta un 50%.',
        };
    }

    /**
     * Lista de valores válidos para reglas de validación.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
