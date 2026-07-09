<?php

namespace App\Infrastructure\Signals;

use App\Core\Contracts\SignalProviderInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Proveedor Mock interno de señales (US06).
 */
class MockSignalProvider implements SignalProviderInterface
{
    /**
     * Obtiene la señal de posición objetivo actual para un nivel de riesgo.
     * Puede sobrescribirse en tests/desarrollo usando el Cache.
     */
    public function getCurrentSignal(string $riskLevel): array
    {
        $riskLevel = strtolower($riskLevel);
        $position = strtoupper(Cache::get("mock_signal:{$riskLevel}", 'LONG'));

        // Rendimiento no realizado de la posición abierta, en porcentaje sobre
        // el margen (mismo contrato que la API real). Sobrescribible en tests.
        $defaultProfit = $position === 'CLOSE' ? 0.0 : 1.25;
        $profit = (float) Cache::get("mock_signal_profit:{$riskLevel}", $defaultProfit);

        return [
            'position' => $position,
            'issued_at' => now()->toIso8601String(),
            'signal_id' => 'mock-sig-' . $riskLevel . '-' . now()->format('YmdH'),
            'profit' => $profit,
        ];
    }

    /**
     * Obtiene el historial de señales deterministas para simular la curva de capital.
     */
    public function getSignalHistory(string $riskLevel): array
    {
        $riskLevel = strtolower($riskLevel);

        $baseHistory = [
            [
                'date' => '2026-05-15',
                'time' => '10:00:00',
                'position' => 'LONG',
                'profit' => 0.00,
            ],
            [
                'date' => '2026-05-18',
                'time' => '16:00:00',
                'position' => 'CLOSE',
                'profit' => $this->getProfitForRisk($riskLevel, 1),
            ],
            [
                'date' => '2026-05-20',
                'time' => '09:00:00',
                'position' => 'SHORT',
                'profit' => 0.00,
            ],
            [
                'date' => '2026-05-22',
                'time' => '14:00:00',
                'position' => 'CLOSE',
                'profit' => $this->getProfitForRisk($riskLevel, 2),
            ],
            [
                'date' => '2026-05-25',
                'time' => '11:00:00',
                'position' => 'LONG',
                'profit' => 0.00,
            ],
            [
                'date' => '2026-05-28',
                'time' => '17:00:00',
                'position' => 'CLOSE',
                'profit' => $this->getProfitForRisk($riskLevel, 3),
            ],
            [
                'date' => '2026-06-05',
                'time' => '10:00:00',
                'position' => 'LONG',
                'profit' => 0.00,
            ],
            [
                'date' => '2026-06-08',
                'time' => '12:00:00',
                'position' => 'CLOSE',
                'profit' => $this->getProfitForRisk($riskLevel, 4),
            ],
        ];

        return $baseHistory;
    }

    /**
     * Retorna rendimientos diferenciados según el riesgo.
     */
    private function getProfitForRisk(string $riskLevel, int $index): float
    {
        $profiles = [
            'conservador' => [
                1 => 0.008,
                2 => 0.004,
                3 => -0.003,
                4 => 0.012,
            ],
            'balanceado' => [
                1 => 0.024,
                2 => 0.012,
                3 => -0.009,
                4 => 0.035,
            ],
            'agresivo' => [
                1 => 0.052,
                2 => 0.027,
                3 => -0.021,
                4 => 0.078,
            ],
        ];

        return $profiles[$riskLevel][$index] ?? 0.00;
    }
}
