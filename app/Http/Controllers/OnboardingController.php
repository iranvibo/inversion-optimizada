<?php

namespace App\Http\Controllers;

use App\Core\Simulation\RiskProfile;
use App\Core\Simulation\SimulatedBalanceProjector;
use App\Http\Requests\OnboardingSimulationRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Adaptador de interfaz (US02): orquesta el onboarding interactivo
 * delegando la proyección de la curva al caso de uso del Core.
 */
class OnboardingController extends Controller
{
    public function __construct(
        private readonly SimulatedBalanceProjector $projector,
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
            'defaultCapital' => 100,
        ]);
    }

    /**
     * Escenarios 1 y 2: proyección dinámica (JSON) según perfil y capital.
     *
     * Usa exactamente la misma fuente de datos que el dashboard en modo
     * simulación (historial de señales), de modo que los valores coinciden y
     * solo escalan con el capital estimado. El rendimiento acumulado es el real
     * al final de la curva y la caída temporal es la cifra honesta del perfil.
     */
    public function simulate(OnboardingSimulationRequest $request)
    {
        $profile = $request->riskProfile();
        $capital = $request->capital();

        try {
            $snapshots = $this->projector->project($profile->value, $capital);
        } catch (\Throwable $e) {
            Log::error('Error al proyectar la simulación de onboarding: '.$e->getMessage());

            return response()->json(['message' => 'No se pudo calcular la simulación en este momento.'], 422);
        }

        $series = array_map(
            fn (array $point) => [
                't' => $point['captured_at']->format(DATE_ATOM),
                'value' => round($point['balance'], 2),
            ],
            $snapshots,
        );

        $finalValue = $snapshots === [] ? $capital : end($snapshots)['balance'];
        $totalReturn = ($finalValue - $capital) / $capital * 100;

        return response()->json([
            'profile' => $profile->value,
            'initial_capital' => round($capital, 2),
            'series' => $series,
            'final_value' => round($finalValue, 2),
            'total_return_percent' => round($totalReturn, 2),
            'max_drawdown_percent' => $profile->honestyDrawdownPercent(),
            'drawdown_message' => $profile->honestyDrawdownMessage(),
        ]);
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
