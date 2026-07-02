<?php

namespace App\Core\Simulation;

use App\Core\Contracts\SignalProviderInterface;
use DateTimeImmutable;

/**
 * Caso de uso compartido: proyecta la curva de capital simulada a partir del
 * historial de señales de la API externa, partiendo de un capital inicial.
 *
 * Es la ÚNICA fuente de verdad de la simulación: tanto el dashboard en modo
 * simulación como el onboarding la usan, de modo que ambas pantallas muestran
 * exactamente los mismos valores (solo escalados por el capital estimado).
 */
class SimulatedBalanceProjector
{
    public function __construct(
        private readonly SignalProviderInterface $signalProvider,
    ) {}

    /**
     * Construye la serie de balances aplicando, en orden cronológico, el
     * rendimiento de cada señal sobre el capital acumulado.
     *
     * @param  DateTimeImmutable|null  $startAt  Si se indica, antepone un punto
     *                                           "Inicio" con el capital intacto en ese instante (lo usa el
     *                                           dashboard para anclar el arranque de la ventana temporal). El
     *                                           onboarding lo omite porque la primera señal ya parte del capital.
     * @return list<array{captured_at: DateTimeImmutable, balance: float}>
     */
    public function project(string $riskLevel, float $capital, ?DateTimeImmutable $startAt = null): array
    {
        $snapshots = [];

        if ($startAt !== null) {
            $snapshots[] = ['captured_at' => $startAt, 'balance' => $capital];
        }

        $history = $this->signalProvider->getSignalHistory($riskLevel);
        $this->sortChronologically($history);

        foreach ($history as $signal) {
            $capturedAt = DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $signal['date'].' '.$signal['time'],
            );

            if (! $capturedAt) {
                continue;
            }

            $capital *= (1 + (float) $signal['profit']);
            $snapshots[] = ['captured_at' => $capturedAt, 'balance' => $capital];
        }

        return $snapshots;
    }

    /**
     * Ordena el historial de señales de más antigua a más reciente, resolviendo
     * empates de fecha colocando los cierres (CLOSE) antes que las aperturas.
     *
     * @param  array<int, array{date: string, time: string, position: string, profit: float}>  $history
     */
    private function sortChronologically(array &$history): void
    {
        usort($history, function ($a, $b) {
            $timeComparison = strcmp($a['date'].' '.$a['time'], $b['date'].' '.$b['time']);
            if ($timeComparison !== 0) {
                return $timeComparison;
            }

            $aPos = strtoupper($a['position'] ?? '');
            $bPos = strtoupper($b['position'] ?? '');

            if ($aPos === 'CLOSE' && $bPos !== 'CLOSE') {
                return -1;
            }
            if ($bPos === 'CLOSE' && $aPos !== 'CLOSE') {
                return 1;
            }

            return 0;
        });
    }
}
