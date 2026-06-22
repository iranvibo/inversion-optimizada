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
            self::Conservador => 'Prioriza preservar tu capital. Crecimiento moderado con caídas temporales pequeñas de hasta un 15%-20%.',
            self::Balanceado => 'Equilibrio entre crecimiento y estabilidad. Asume caídas temporales moderadas de hasta un 30%-50%.',
            self::Agresivo => 'Busca el máximo crecimiento. Debes tolerar caídas temporales pronunciadas de hasta un 50%-90%.',
        };
    }

    /**
     * Peor caída temporal (drawdown) que el usuario debe estar dispuesto a
     * tolerar con este perfil, como cifra única "de honestidad" para el
     * onboarding. Es un dato de transparencia, no un cálculo sobre la serie.
     *
     *   Conservador → 15%
     *   Balanceado  → 30%
     *   Agresivo    → 50%
     */
    public function honestyDrawdownPercent(): int
    {
        return match ($this) {
            self::Conservador => 15,
            self::Balanceado => 30,
            self::Agresivo => 50,
        };
    }

    /**
     * Mensaje de transparencia en lenguaje natural sobre la peor caída esperable.
     */
    public function honestyDrawdownMessage(): string
    {
        return sprintf(
            'La cuenta ha tenido caídas temporales de hasta un %d%%.',
            $this->honestyDrawdownPercent(),
        );
    }

    /**
     * Fracción del capital disponible que se compromete al abrir una posición
     * en modo real (US06). Cuanto más agresivo el perfil, mayor exposición.
     *
     *   Conservador → 20% del capital
     *   Balanceado  → 50% del capital
     *   Agresivo    → 90% del capital
     */
    public function capitalFraction(): float
    {
        return match ($this) {
            self::Conservador => 0.20,
            self::Balanceado => 0.50,
            self::Agresivo => 0.90,
        };
    }

    /**
     * Resuelve el perfil desde un string arbitrario (insensible a mayúsculas),
     * cayendo a Balanceado si el valor no es reconocido.
     */
    public static function fromString(?string $value): self
    {
        return self::tryFrom(strtolower((string) $value)) ?? self::Balanceado;
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
