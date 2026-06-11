<?php

namespace App\Http\Controllers;

use App\Core\Simulation\HistoricalSimulationService;
use App\Core\Simulation\RiskProfile;
use App\Http\Requests\OnboardingSimulationRequest;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Adaptador de interfaz (US02): orquesta el onboarding interactivo
 * delegando toda la lógica de simulación al caso de uso del Core.
 */
class OnboardingController extends Controller
{
    public function __construct(
        private readonly HistoricalSimulationService $simulationService,
    ) {
    }

    /**
     * Muestra la pantalla de onboarding con la simulación interactiva.
     */
    public function show()
    {
        $user = Auth::user();

        // Si ya completó el onboarding, no tiene sentido repetirlo
        if ($user->hasCompletedOnboarding()) {
            return redirect()->route('dashboard');
        }

        return view('onboarding.simulation', [
            'profiles' => RiskProfile::cases(),
            'minCapital' => OnboardingSimulationRequest::MIN_CAPITAL,
            'maxCapital' => OnboardingSimulationRequest::MAX_CAPITAL,
            'defaultProfile' => RiskProfile::Balanceado,
            'defaultCapital' => 1000,
        ]);
    }

    /**
     * Escenarios 1 y 2: proyección dinámica (JSON) según perfil y capital,
     * incluyendo rendimiento acumulado y peor drawdown en lenguaje natural.
     */
    public function simulate(OnboardingSimulationRequest $request)
    {
        try {
            $result = $this->simulationService->simulate(
                $request->riskProfile(),
                $request->capital(),
            );
        } catch (InvalidArgumentException $e) {
            // Mensaje claro sin exponer trazas internas
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result->toArray());
    }

    /**
     * Escenario 3: confirma el onboarding persistiendo el perfil de riesgo
     * y el capital estimado como configuración por defecto del bot en simulación.
     */
    public function complete(OnboardingSimulationRequest $request)
    {
        $user = Auth::user();

        $user->update([
            'risk_level' => $request->riskProfile()->value,
            'estimated_capital' => $request->capital(),
            'bot_mode' => 'simulation', // El bot arranca siempre en modo simulación
            'onboarding_completed_at' => now(),
        ]);

        return redirect()->route('dashboard')
            ->with('success', 'Onboarding completado: tu perfil ' . $request->riskProfile()->label() . ' y capital estimado quedaron configurados en modo simulación.');
    }
}
