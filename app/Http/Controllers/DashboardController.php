<?php

namespace App\Http\Controllers;

use App\Core\Contracts\BinanceBrokerInterface;
use App\Core\Contracts\SignalProviderInterface;
use App\Events\BotStatusUpdated;
use App\Jobs\AdjustPositionJob;
use App\Models\BotActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    protected BinanceBrokerInterface $binanceBroker;

    public function __construct(BinanceBrokerInterface $binanceBroker)
    {
        $this->binanceBroker = $binanceBroker;
    }

    /**
     * Muestra el Dashboard principal de ViBo Invest.
     */
    public function index()
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        // Balance inicial renderizado en servidor (US03, Escenario 1);
        // la serie del gráfico se carga después vía JSON según el filtro.
        $latestSnapshot = $user->balanceSnapshots()
            ->latest('captured_at')
            ->first(['balance', 'captured_at']);

        // Historial de actividad y alertas de riesgo (US05)
        $activities = $this->getActivitiesForUser($user);
        $riskAlerts = $activities->where('risk_alert', true);

        $signalProviderUnstable = Cache::has('signal_provider_unstable:'.strtolower($user->risk_level));

        return view('dashboard', compact('user', 'latestSnapshot', 'activities', 'riskAlerts', 'signalProviderUnstable'));
    }

    /**
     * Devuelve el feed de actividades del bot en JSON (US05).
     */
    public function activities(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $activities = $this->getActivitiesForUser($user);

        return response()->json([
            'activities' => $activities->map(fn ($act) => [
                'id' => $act->id,
                'type' => $act->type,
                'action' => $act->action,
                'human_description' => $act->human_description,
                'profit_percentage' => $act->profit_percentage,
                'profit_value' => $act->profit_value,
                'risk_alert' => $act->risk_alert,
                'created_at' => $act->created_at->toISOString(),
                'created_at_formatted' => $act->created_at->format('d/m/Y H:i'),
                'created_at_human' => $act->created_at->diffForHumans(),
            ]),
        ]);
    }

    /**
     * Simula eventos de actividad en la cuenta del usuario para validación de la US05.
     */
    public function simulateActivity(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return back()->with('error', 'Sesión no válida.');
        }

        // Limpiar actividades anteriores de simulación para tener un feed limpio
        $user->botActivities()->delete();

        // 1. Compra de oportunidad (Escenario 1) -> LONG
        $user->botActivities()->create([
            'type' => 'long',
            'action' => 'open_long',
            'risk_alert' => false,
            'created_at' => now()->subMinutes(15),
        ]);

        // 2. Cierre con beneficio de +1.5% (+15€) (Escenario 2) -> CLOSE
        $user->botActivities()->create([
            'type' => 'close',
            'action' => 'close_profit',
            'profit_percentage' => 1.5,
            'profit_value' => 15.00,
            'risk_alert' => false,
            'created_at' => now()->subMinutes(10),
        ]);

        // 3. Cierre con pérdidas / Protección (Escenario 2) -> CLOSE
        $user->botActivities()->create([
            'type' => 'close',
            'action' => 'close_loss',
            'profit_percentage' => -1.0,
            'profit_value' => -10.00,
            'risk_alert' => false,
            'created_at' => now()->subMinutes(5),
        ]);

        // 4. Protección diaria de riesgo (Escenario 3)
        $user->botActivities()->create([
            'type' => 'risk_protection',
            'action' => 'stop_loss_trigger',
            'risk_alert' => true,
            'created_at' => now(),
        ]);

        return back()->with([
            'success' => 'Simulación de Actividad exitosa. Se han generado 4 eventos en tu historial.',
            'active_tab' => 'activity',
        ]);
    }

    /**
     * Alterna la activación del Bot.
     */
    public function toggleBot(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Sesión no válida.'], 401);
            }

            return back()->with('error', 'Sesión no válida.');
        }

        // Si intenta encender el bot en modo Real sin Binance vinculado, lo bloquea (US07 Escenario 3)
        if (! $user->bot_active && $user->bot_mode === 'real' && ! $user->isBinanceLinked()) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Operación Bloqueada: Para activar el bot en modo REAL debes vincular una cuenta de Binance autorizada.'], 403);
            }

            return back()->with('error', 'Operación Bloqueada: Para activar el bot en modo REAL debes vincular una cuenta de Binance autorizada.');
        }

        $closeError = null;
        if ($user->bot_active) {
            // Transición a PAUSADO (Escenario 3: Cierre preventivo al pausar)
            $previousPosition = $user->current_position;
            $hasOpenPosition = ($previousPosition === 'LONG' || $previousPosition === 'SHORT');

            // Cierre preventivo en Binance si la cuenta está vinculada (para cualquier modo, como salvaguarda)
            if ($user->isBinanceLinked()) {
                try {
                    $this->binanceBroker->closeOpenPositions($user->binance_api_key, $user->binance_secret_key);
                    Log::info("Cierre preventivo de posiciones ejecutado para el usuario ID: {$user->id}");

                    if ($user->bot_mode === 'real' && $hasOpenPosition) {
                        $latestSnapshot = $user->balanceSnapshots()->latest('captured_at')->first();
                        $currentBalance = $latestSnapshot ? (float) $latestSnapshot->balance : (float) ($user->estimated_capital ?? 100.0);

                        if (! app()->runningUnitTests()) {
                            sleep(2);
                        }

                        $newBalance = $this->binanceBroker->getTotalBalance($user->binance_api_key, $user->binance_secret_key);

                        // Sincronizar el nuevo balance
                        $now = now()->toImmutable();
                        $user->balanceSnapshots()->create([
                            'balance' => $newBalance,
                            'captured_at' => $now,
                        ]);

                        // Recuperar el capital de apertura
                        $openCapital = (float) Cache::pull("user:{$user->id}:open_capital", $currentBalance);

                        // Calcular el profit/loss
                        $profitValue = round($newBalance - $openCapital, 2);
                        $profitPercentage = $openCapital > 0 ? round(($profitValue / $openCapital) * 100, 2) : 0.0;

                        // Registrar la actividad
                        $user->botActivities()->create([
                            'type' => 'close',
                            'action' => $profitValue >= 0 ? 'close_profit' : 'close_loss',
                            'profit_percentage' => $profitPercentage,
                            'profit_value' => $profitValue,
                            'risk_alert' => false,
                        ]);

                        // Emitir evento para balance
                        event(new \App\Events\BalanceUpdated($user, $newBalance, $now));
                    }
                } catch (\Exception $e) {
                    $closeError = 'Advertencia: El bot se pausó localmente, pero hubo un problema al cerrar posiciones en Binance: '.$e->getMessage();
                    Log::critical("Fallo al cerrar posiciones preventivamente para el usuario ID: {$user->id}. Detalle: ".$e->getMessage());
                }
            }

            // Si es simulación y tiene posición abierta, registramos el cierre simulado
            if ($user->bot_mode === 'simulation' && $hasOpenPosition) {
                $latestSnapshot = $user->balanceSnapshots()->latest('captured_at')->first();
                $currentBalance = $latestSnapshot ? (float) $latestSnapshot->balance : (float) ($user->estimated_capital ?? 100.0);

                $profitPercent = 1.5;
                $profitVal = round($currentBalance * ($profitPercent / 100), 2);
                $newBalance = round($currentBalance + $profitVal, 2);

                $user->botActivities()->create([
                    'type' => 'close',
                    'action' => 'close_profit',
                    'profit_percentage' => $profitPercent,
                    'profit_value' => $profitVal,
                    'risk_alert' => false,
                    'description' => "Inversión finalizada: posición cerrada con un +{$profitPercent}% de beneficio (+{$profitVal}€).",
                ]);

                // Registrar un nuevo balance snapshot simulado
                $now = now()->toImmutable();
                $user->balanceSnapshots()->create([
                    'balance' => $newBalance,
                    'captured_at' => $now,
                ]);

                // Emitir evento WebSocket para actualizar balance en tiempo real
                event(new \App\Events\BalanceUpdated($user, $newBalance, $now));
            }
        } else {
            // Transición a ACTIVO: enviar señal al motor de ejecución de órdenes (Escenario 1)
            Log::info("Señal enviada al motor de ejecución de órdenes para activar el bot del usuario ID: {$user->id}");
        }

        $newStatus = ! $user->bot_active;
        $user->update([
            'bot_active' => $newStatus,
        ]);

        if (! $newStatus) {
            Cache::put("user:{$user->id}:real_position", 'CLOSE');
            Cache::put("user:{$user->id}:simulation_position", 'CLOSE');
        } elseif ($user->bot_mode === 'real' && $user->isBinanceLinked()) {
            // Reconciliación al activar: el motor solo reacciona a CAMBIOS de señal,
            // así que tras un pausa→cierre la posición real queda en CLOSE mientras la
            // señal vigente puede seguir siendo SHORT/LONG. Al encender, alineamos la
            // posición con la señal actual encolando un ajuste idempotente (no reabre
            // si ya coincide), en lugar de esperar a que la señal cambie.
            $this->reconcilePositionWithCurrentSignal($user);
        }

        // Disparar evento de WebSocket en tiempo real
        event(new BotStatusUpdated($user, $newStatus));

        $statusMessage = $newStatus ? 'Bot ACTIVADO con éxito.' : 'Bot PAUSADO de inmediato.';

        if ($closeError) {
            $statusMessage .= ' '.$closeError;
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'bot_active' => $newStatus,
                    'current_position' => $user->current_position,
                    'message' => $statusMessage,
                    'warning' => $closeError,
                ]);
            }

            return back()->with('error', $statusMessage);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'bot_active' => $newStatus,
                'current_position' => $user->current_position,
                'message' => $statusMessage,
            ]);
        }

        return back()->with('success', $statusMessage);
    }

    /**
     * Encola un ajuste de posición hacia la señal vigente del proveedor para el
     * nivel de riesgo del usuario. Idempotente: si la posición real ya coincide,
     * el job no hace nada. Un fallo al consultar la señal no bloquea la activación.
     */
    private function reconcilePositionWithCurrentSignal(User $user): void
    {
        try {
            $signal = app(SignalProviderInterface::class)
                ->getCurrentSignal($user->risk_level ?? 'balanceado');
            $position = strtoupper($signal['position'] ?? 'CLOSE');

            AdjustPositionJob::dispatch($user->id, $position);
            Log::info("Reconciliación al activar: encolado ajuste a '{$position}' para el usuario ID: {$user->id}.");
        } catch (\Throwable $e) {
            // Sin señal disponible: el bot queda activo y el sondeo normal actuará
            // en el próximo cambio. No se interrumpe la activación.
            Log::warning("No se pudo reconciliar la posición al activar el bot del usuario ID: {$user->id}. Detalle: ".$e->getMessage());
        }
    }

    /**
     * Alterna el modo del Bot (Simulación / Real).
     */
    public function toggleMode(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Sesión no válida.'], 401);
            }

            return back();
        }

        $newMode = $user->bot_mode === 'real' ? 'simulation' : 'real';

        // Si intenta cambiar a modo real y no está vinculado, mostrar error guiando al flujo de vinculación
        if ($newMode === 'real' && ! $user->isBinanceLinked()) {
            $errorMessage = 'Requisito de Seguridad: Para activar el modo REAL debes tener una cuenta de Binance vinculada y validada. Por favor, conéctala en la sección "Conexión de Binance".';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $errorMessage], 403);
            }

            return back()->with('error', $errorMessage);
        }

        $closeError = null;
        if ($newMode === 'simulation' && $user->bot_active && $user->isBinanceLinked()) {
            try {
                $this->binanceBroker->closeOpenPositions($user->binance_api_key, $user->binance_secret_key);
                Log::info("Cierre preventivo de posiciones ejecutado al cambiar a modo simulación para el usuario ID: {$user->id}");
            } catch (\Exception $e) {
                $closeError = 'Advertencia: El modo cambió a simulación, pero hubo un problema al cerrar posiciones en Binance: '.$e->getMessage();
                Log::critical("Fallo al cerrar posiciones preventivamente al cambiar a simulación para el usuario ID: {$user->id}. Detalle: ".$e->getMessage());
            }
        }

        Cache::put("user:{$user->id}:real_position", 'CLOSE');

        $user->update([
            'bot_mode' => $newMode,
        ]);

        // Al pasar a modo real con el bot ya activo, la posición real se acaba de
        // forzar a CLOSE; el sondeo solo reacciona a CAMBIOS de señal, así que
        // reconciliamos de inmediato con la señal vigente para no quedarnos en
        // CLOSE mientras la señal sigue siendo LONG/SHORT (mismo criterio que la
        // activación del bot).
        if ($newMode === 'real' && $user->bot_active && $user->isBinanceLinked()) {
            $this->reconcilePositionWithCurrentSignal($user);
        }

        $statusMessage = 'Modo cambiado a: '.strtoupper($newMode);
        if ($closeError) {
            $statusMessage .= ' '.$closeError;
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'bot_mode' => $newMode,
                    'current_position' => $user->current_position,
                    'message' => $statusMessage,
                    'warning' => $closeError,
                ]);
            }

            return back()->with('error', $statusMessage);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'bot_mode' => $newMode,
                'current_position' => $user->current_position,
                'message' => $statusMessage,
            ]);
        }

        return back()->with('success', $statusMessage);
    }

    /**
     * Actualiza el nivel de riesgo del bot del usuario.
     */
    public function updateRisk(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Sesión no válida.'], 401);
            }

            return back()->with('error', 'Sesión no válida.');
        }

        $request->validate([
            'risk_level' => 'required|string|in:conservador,balanceado,agresivo',
        ]);

        $riskLevel = strtolower($request->input('risk_level'));
        $user->update([
            'risk_level' => $riskLevel,
        ]);

        $statusMessage = 'Nivel de riesgo actualizado a: '.ucfirst($riskLevel);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'risk_level' => $riskLevel,
                'current_position' => $user->current_position,
                'message' => $statusMessage,
            ]);
        }

        return back()->with('success', $statusMessage);
    }

    /**
     * Simula el evento de alerta de retiro en Binance (para probar el Escenario 3 manualmente).
     */
    public function triggerWithdrawalSimulation()
    {
        $user = Auth::user();
        if (! $user) {
            return back();
        }

        if (! $user->isBinanceLinked()) {
            return back()->with('error', 'Debes conectar tu cuenta de Binance antes de poder simular este caso.');
        }

        // Simula cambiar la API Key por una que tiene retiros activos
        $user->update([
            'binance_api_key' => 'withdrawals_enabled_key',
            'bot_active' => true,
        ]);

        // Ejecutar inmediatamente la validación
        Artisan::call('binance:verify-permissions');

        return back()->with('success', 'Simulación Activada: Se modificó la API Key a una con permisos de retiro y se ejecutó la auditoría de seguridad. El bot se ha pausado y se ha activado la alerta.');
    }

    /**
     * Recupera y mapea las actividades del bot para un usuario.
     * En modo simulación, se obtienen dinámicamente del historial de señales de la API.
     */
    protected function getActivitiesForUser($user)
    {
        if ($user->bot_mode === 'real') {
            $activities = $user->botActivities()->latest()->get();

            return $this->sortActivities($activities);
        }

        $dbActivities = $user->botActivities()->latest()->get();
        if ($dbActivities->isNotEmpty()) {
            return $this->sortActivities($dbActivities);
        }

        try {
            $signalProvider = app(SignalProviderInterface::class);
            $history = $signalProvider->getSignalHistory($user->risk_level ?? 'balanceado');

            // Ordenar por fecha descendente (más recientes primero)
            usort($history, function ($a, $b) {
                $timeComparison = strcmp($b['date'].' '.$b['time'], $a['date'].' '.$a['time']);
                if ($timeComparison !== 0) {
                    return $timeComparison;
                }

                $aPos = strtoupper($a['position'] ?? '');
                $bPos = strtoupper($b['position'] ?? '');

                if ($aPos === 'CLOSE' && $bPos !== 'CLOSE') {
                    return 1;
                }
                if ($bPos === 'CLOSE' && $aPos !== 'CLOSE') {
                    return -1;
                }

                return 0;
            });

            $activities = collect();
            $capital = (float) ($user->estimated_capital ?? 100.0);

            // Para calcular los valores en euros (profit_value) a partir del profit_percentage,
            // necesitamos procesar la serie en orden cronológico primero para calcular el capital acumulado
            // en cada punto, y luego mapearlo.
            $chronological = $history;
            usort($chronological, function ($a, $b) {
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

            $capitalsAtTime = [];
            foreach ($chronological as $sig) {
                $dateTimeStr = $sig['date'].' '.$sig['time'];
                $posKey = $dateTimeStr.'_'.strtoupper($sig['position'] ?? 'CLOSE');
                $profit = (float) ($sig['profit'] ?? 0.0);
                $capitalBefore = $capital;
                $capital *= (1 + $profit);
                $capitalsAtTime[$posKey] = [
                    'before' => $capitalBefore,
                    'after' => $capital,
                    'profit_value' => round($capitalBefore * $profit, 2),
                ];
            }

            foreach ($history as $sig) {
                $dateTimeStr = $sig['date'].' '.$sig['time'];
                $profit = (float) ($sig['profit'] ?? 0.0);
                $position = strtoupper($sig['position'] ?? 'CLOSE');

                $type = 'close';
                $action = 'close_profit';
                $profitPct = null;
                $profitVal = null;

                if ($position === 'LONG') {
                    $type = 'long';
                    $action = 'open_long';
                } elseif ($position === 'SHORT') {
                    $type = 'short';
                    $action = 'open_short';
                } else {
                    $type = 'close';
                    $action = $profit >= 0 ? 'close_profit' : 'close_loss';
                    $profitPct = $profit * 100;
                    $posKey = $dateTimeStr.'_'.$position;
                    $profitVal = $capitalsAtTime[$posKey]['profit_value'] ?? 0.0;
                }

                $activity = new BotActivity([
                    'type' => $type,
                    'action' => $action,
                    'profit_percentage' => $profitPct,
                    'profit_value' => $profitVal,
                    'risk_alert' => false,
                ]);

                // Asignar timestamps virtualmente
                $activity->created_at = Carbon::parse($dateTimeStr);
                // Forzar el ID para que Vue/Blade tengan una llave única
                $activity->id = crc32($dateTimeStr.$position.$profit);

                $activities->push($activity);
            }

            return $this->sortActivities($activities);

        } catch (\Exception $e) {
            Log::error('Error loading simulated activity history: '.$e->getMessage());

            return collect();
        }
    }

    /**
     * Ordena una colección de actividades garantizando que las aperturas (LONG/SHORT)
     * queden por encima de los cierres (CLOSE) cuando ocurren en el mismo segundo.
     */
    protected function sortActivities($activities)
    {
        return $activities->sort(function ($a, $b) {
            $timeA = $a->created_at;
            $timeB = $b->created_at;

            if ($timeA->ne($timeB)) {
                return $timeB->greaterThan($timeA) ? 1 : -1;
            }

            $aType = $a->type;
            $bType = $b->type;

            if ($aType === 'close' && $bType !== 'close') {
                return 1;
            }
            if ($bType === 'close' && $aType !== 'close') {
                return -1;
            }

            if (isset($a->id) && isset($b->id)) {
                return $b->id <=> $a->id;
            }

            return 0;
        })->values();
    }
}
