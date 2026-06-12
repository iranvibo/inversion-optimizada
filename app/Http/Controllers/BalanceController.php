<?php

namespace App\Http\Controllers;

use App\Core\Contracts\SignalProviderInterface;
use App\Core\Portfolio\PortfolioPerformanceService;
use App\Events\BalanceUpdated;
use App\Http\Requests\BalanceHistoryRequest;
use App\Infrastructure\Demo\FakeBalanceHistoryGenerator;
use App\Models\BalanceSnapshot;
use Illuminate\Support\Facades\Auth;

/**
 * Adaptador de interfaz (US03): expone la evolución del balance del usuario
 * delegando el cálculo al caso de uso del Core. No contiene lógica de negocio.
 */
class BalanceController extends Controller
{
    public function __construct(
        private readonly PortfolioPerformanceService $performanceService,
        private readonly SignalProviderInterface $signalProvider,
    ) {}

    /**
     * Escenarios 1 y 2: serie temporal del balance + % de cambio en lenguaje
     * humano para el rango solicitado (Día / Semana / Mes), en JSON.
     */
    public function history(BalanceHistoryRequest $request)
    {
        $user = Auth::user();
        $range = $request->range();
        $now = now()->toImmutable();

        if ($user->bot_mode === 'simulation') {
            $capital = (float) ($user->estimated_capital ?? 1000.0);
            $snapshots = [];

            // Punto de partida inicial
            $snapshots[] = [
                'captured_at' => $now->modify('-1 month'),
                'balance' => $capital,
            ];

            try {
                $history = $this->signalProvider->getSignalHistory($user->risk_level);
                // Ordenar cronológicamente
                usort($history, fn ($a, $b) => strcmp($a['date'] . ' ' . $a['time'], $b['date'] . ' ' . $b['time']));

                foreach ($history as $signal) {
                    $dateTimeStr = $signal['date'] . ' ' . $signal['time'];
                    $capturedAt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateTimeStr);
                    if (!$capturedAt) {
                        continue;
                    }
                    $capital *= (1 + (float) $signal['profit']);
                    $snapshots[] = [
                        'captured_at' => $capturedAt,
                        'balance' => $capital,
                    ];
                }
            } catch (\Exception $e) {
                // Si falla el proveedor, mostramos el punto inicial o logeamos
                \Illuminate\Support\Facades\Log::error("Error loading signal history for balance chart: " . $e->getMessage());
            }

            $report = $this->performanceService->report($snapshots, $range, $now);

            return response()->json($report->toArray());
        }

        // Filtrado y orden en SQL (índice user_id + captured_at): solo se
        // hidratan los puntos de la ventana solicitada, nunca todo el historial.
        $snapshots = $user->balanceSnapshots()
            ->where('captured_at', '>=', $range->startAt($now))
            ->orderBy('captured_at')
            ->get(['balance', 'captured_at'])
            ->map(fn (BalanceSnapshot $snapshot) => [
                'captured_at' => $snapshot->captured_at->toImmutable(),
                'balance' => (float) $snapshot->balance,
            ])
            ->all();

        $report = $this->performanceService->report($snapshots, $range, $now);

        return response()->json($report->toArray());
    }

    /**
     * Herramienta del simulador (Escenario 3): emula una sincronización del
     * backend con Binance. Si el usuario aún no tiene historial, lo
     * retro-rellena con datos demo deterministas; después registra un nuevo
     * snapshot y emite el evento reactivo por WebSockets.
     */
    public function simulateSync(FakeBalanceHistoryGenerator $generator)
    {
        $user = Auth::user();

        if (! $user->isBinanceLinked()) {
            return back()->with('error', 'Debes conectar tu cuenta de Binance antes de poder simular la sincronización de balance.');
        }

        $now = now()->toImmutable();

        if (! $user->balanceSnapshots()->exists()) {
            $base = (float) ($user->estimated_capital ?? 1000);
            $history = $generator->generate($base, $now->modify('-1 hour'), seed: (int) $user->id);

            // Inserción masiva: evita el problema N+1 de crear punto a punto.
            $user->balanceSnapshots()->createMany(array_map(
                fn (array $point) => [
                    'balance' => $point['balance'],
                    'captured_at' => $point['captured_at'],
                ],
                $history,
            ));
        }

        // Nuevo snapshot "recién sincronizado" con una variación pequeña,
        // como si el saldo real hubiese cambiado por operaciones del bot.
        $lastBalance = (float) $user->balanceSnapshots()
            ->latest('captured_at')
            ->value('balance');

        $newBalance = round($lastBalance * (1 + mt_rand(-120, 180) / 10000), 2);

        $user->balanceSnapshots()->create([
            'balance' => $newBalance,
            'captured_at' => $now,
        ]);

        event(new BalanceUpdated($user, $newBalance, $now));

        return back()->with('success', 'Sincronización simulada: se registró un nuevo snapshot de balance y se notificó al dashboard en tiempo real.');
    }
}
