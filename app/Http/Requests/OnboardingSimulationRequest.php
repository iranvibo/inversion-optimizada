<?php

namespace App\Http\Requests;

use App\Core\Simulation\RiskProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida los parámetros de la simulación interactiva del onboarding (US02).
 * Se reutiliza tanto para previsualizar la simulación como para confirmarla,
 * ya que ambos casos comparten exactamente los mismos parámetros.
 */
class OnboardingSimulationRequest extends FormRequest
{
    /** Límites del deslizador de capital (negociables según producto). */
    public const MIN_CAPITAL = 100;
    public const MAX_CAPITAL = 100000;

    public function authorize(): bool
    {
        // La ruta ya está protegida por el middleware 'auth'.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'risk_profile' => ['required', 'string', Rule::in(RiskProfile::values())],
            'capital' => ['required', 'numeric', 'min:' . self::MIN_CAPITAL, 'max:' . self::MAX_CAPITAL],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'risk_profile.required' => 'Debes seleccionar un perfil de riesgo.',
            'risk_profile.in' => 'El perfil de riesgo seleccionado no es válido.',
            'capital.required' => 'Debes indicar tu capital estimado.',
            'capital.numeric' => 'El capital debe ser un valor numérico.',
            'capital.min' => 'El capital mínimo de simulación es de ' . self::MIN_CAPITAL . '€.',
            'capital.max' => 'El capital máximo de simulación es de ' . number_format(self::MAX_CAPITAL, 0, ',', '.') . '€.',
        ];
    }

    /**
     * Perfil de riesgo ya validado, como objeto de dominio.
     */
    public function riskProfile(): RiskProfile
    {
        return RiskProfile::from($this->validated('risk_profile'));
    }

    /**
     * Capital ya validado, como float.
     */
    public function capital(): float
    {
        return (float) $this->validated('capital');
    }
}
