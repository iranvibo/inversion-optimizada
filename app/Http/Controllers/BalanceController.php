<?php

namespace App\Http\Controllers;

use App\Core\Portfolio\PortfolioPerformanceService;
use App\Core\Simulation\SimulatedBalanceProjector;
use App\Core\Trading\BrokerResolver;
use App\Core\Trading\PositionReconciler;
use App\Events\BalanceUpdated;
use App\Http\Requests\BalanceHistoryRequest;
use App\Infrastructure\Demo\FakeBalanceHistoryGenerator;
use App\Models\BalanceSnapshot;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Adaptador de interfaz (US03): expone la evolución del balance del usuario
 * delegando el cálculo al caso de uso del Core. No contiene lógica de negocio.
 */
class BalanceController extends Controller
{
    public function __construct(
        private readonly PortfolioPerformanceService $performanceService,
        private readonly BrokerResolver $brokerResolver,
        private readonly SimulatedBalanceProjector $balanceProjector,
        private readonly PositionReconciler $positionReconciler,
    ) {}

    /**
     * Patrimonio neto en vivo (US03): devuelve el equity actual de la cuenta para
     * que la cabecera "Balance Total" se mueva con el P/L de la posición abierta,
     * sin persistir un snapshot (eso lo hace el job programado cada 15 min).
     *
     * El sondeo del navegador es frecuente (cada pocos segundos), así que la
     * llamada real a Binance se cachea unos segundos por usuario para no agotar
     * el rate limit del exchange ni multiplicar peticiones firmadas.
     */
    public function live()
    {
        $user = Auth::user();

        // Solo el modo real con el canal vinculado tiene patrimonio "en vivo":
        // en simulación el balance no está atado a una posición real del exchange.
        if ($user->bot_mode !== 'real' || ! $user->isBrokerLinked()) {
            return response()->json(['live' => false, 'balance' => null]);
        }

        try {
            $balance = Cache::remember(
                "user:{$user->id}:live_equity",
                now()->addSeconds(5),
                fn () => $this->brokerResolver->forUser($user)->getTotalBalance(
                    $user->brokerApiKey(),
                    $user->brokerSecretKey(),
                ),
            );

            // Reconciliar la posición real del exchange y registrar cierres (TP/SL)
            $this->positionReconciler->reconcile($user, $balance);
        } catch (\Throwable $e) {
            // Fallo transitorio (red/API/permisos): la UI conserva el último valor.
            return response()->json(['live' => false, 'balance' => null]);
        }

        return response()->json([
            'live' => true,
            'balance' => $balance,
            'current_position' => $user->current_position,
        ]);
    }

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
            $capital = (float) ($user->estimated_capital ?? 100.0);

            try {
                // Misma proyección que el onboarding (única fuente de verdad),
                // anclada con un punto de partida al inicio de la ventana.
                $snapshots = $this->balanceProjector->project(
                    $user->risk_level,
                    $capital,
                    $now->modify('-1 month'),
                );
            } catch (\Throwable $e) {
                Log::error('Error loading signal history for balance chart: '.$e->getMessage());
                // Si falla el proveedor, mostramos al menos el punto inicial.
                $snapshots = [['captured_at' => $now->modify('-1 month'), 'balance' => $capital]];
            }

            $report = $this->performanceService->report($snapshots, $range, $now);

            return response()->json($report->toArray());
        }

        if ($user->isBrokerLinked() && ! $user->balanceSnapshots()->where('trading_channel', $user->tradingChannel())->exists() && ! app()->environment('testing') && ! $this->brokerResolver->isMock($user->tradingChannel())) {
            try {
                $broker = $this->brokerResolver->forUser($user);
                $balance = $broker->getTotalBalance($user->brokerApiKey(), $user->brokerSecretKey());
                $user->balanceSnapshots()->create([
                    'balance' => $balance,
                    'captured_at' => now(),
                    'trading_channel' => $user->tradingChannel(),
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to fetch initial balance for history: '.$e->getMessage());
            }
        }

        // Filtrado y orden en SQL (índice user_id + captured_at): solo se
        // hidratan los puntos de la ventana solicitada, nunca todo el historial.
        $snapshots = $user->balanceSnapshots()
            ->where('trading_channel', $user->tradingChannel())
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

        // Herramienta de prueba: solo en simulación, para no contaminar el
        // histórico real con datos demo (la curva real refleja solo Binance).
        if ($user->bot_mode === 'real') {
            return back()->with('error', 'La sincronización simulada solo está disponible en modo simulación.');
        }

        $now = now()->toImmutable();

        if (! $user->balanceSnapshots()->where('trading_channel', $user->tradingChannel())->exists()) {
            $base = (float) ($user->estimated_capital ?? 100);
            $history = $generator->generate($base, $now->modify('-1 hour'), seed: (int) $user->id);

            // Inserción masiva: evita el problema N+1 de crear punto a punto.
            $user->balanceSnapshots()->createMany(array_map(
                fn (array $point) => [
                    'balance' => $point['balance'],
                    'captured_at' => $point['captured_at'],
                    'trading_channel' => $user->tradingChannel(),
                ],
                $history,
            ));
        }

        // Nuevo snapshot "recién sincronizado" con una variación pequeña,
        // como si el saldo real hubiese cambiado por operaciones del bot.
        $lastBalance = (float) $user->balanceSnapshots()
            ->where('trading_channel', $user->tradingChannel())
            ->latest('captured_at')
            ->value('balance');

        $newBalance = round($lastBalance * (1 + mt_rand(-120, 180) / 10000), 2);

        $user->balanceSnapshots()->create([
            'balance' => $newBalance,
            'captured_at' => $now,
            'trading_channel' => $user->tradingChannel(),
        ]);

        event(new BalanceUpdated($user, $newBalance, $now));

        return back()->with('success', 'Sincronización simulada: se registró un nuevo snapshot de balance y se notificó al dashboard en tiempo real.');
    }
}
