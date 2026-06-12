<?php

namespace App\Http\Controllers;

use App\Core\Contracts\BinanceBrokerInterface;
use App\Events\BotStatusUpdated;
use Illuminate\Http\Request;
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
        $activities = $user->botActivities()->latest()->get();
        $riskAlerts = $activities->where('risk_alert', true);

        $signalProviderUnstable = Cache::has("signal_provider_unstable:" . strtolower($user->risk_level));

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

        $activities = $user->botActivities()->latest()->get();

        return response()->json([
            'activities' => $activities->map(fn($act) => [
                'id' => $act->id,
                'type' => $act->type,
                'action' => $act->action,
                'human_description' => $act->human_description,
                'profit_percentage' => $act->profit_percentage,
                'profit_value' => $act->profit_value,
                'risk_alert' => $act->risk_alert,
                'created_at' => $act->created_at->toISOString(),
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

        // 1. Compra de oportunidad (Escenario 1)
        $user->botActivities()->create([
            'type' => 'buy',
            'action' => 'buy_opportunity',
            'risk_alert' => false,
            'created_at' => now()->subMinutes(15),
        ]);

        // 2. Cierre con beneficio de +1.5% (+15€) (Escenario 2)
        $user->botActivities()->create([
            'type' => 'sell',
            'action' => 'close_profit',
            'profit_percentage' => 1.5,
            'profit_value' => 15.00,
            'risk_alert' => false,
            'created_at' => now()->subMinutes(10),
        ]);

        // 3. Cierre con pérdidas / Protección (Escenario 2)
        $user->botActivities()->create([
            'type' => 'sell',
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
            if ($user->isBinanceLinked()) {
                try {
                    $this->binanceBroker->closeOpenPositions($user->binance_api_key, $user->binance_secret_key);
                    Log::info("Cierre preventivo de posiciones ejecutado para el usuario ID: {$user->id}");
                } catch (\Exception $e) {
                    $closeError = "Advertencia: El bot se pausó localmente, pero hubo un problema al cerrar posiciones en Binance: " . $e->getMessage();
                    Log::critical("Fallo al cerrar posiciones preventivamente para el usuario ID: {$user->id}. Detalle: " . $e->getMessage());
                }
            }
        } else {
            // Transición a ACTIVO: enviar señal al motor de ejecución de órdenes (Escenario 1)
            Log::info("Señal enviada al motor de ejecución de órdenes para activar el bot del usuario ID: {$user->id}");
        }

        $newStatus = ! $user->bot_active;
        $user->update([
            'bot_active' => $newStatus,
        ]);

        // Disparar evento de WebSocket en tiempo real
        event(new BotStatusUpdated($user, $newStatus));

        $statusMessage = $newStatus ? 'Bot ACTIVADO con éxito.' : 'Bot PAUSADO de inmediato.';

        if ($closeError) {
            $statusMessage .= ' ' . $closeError;
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'bot_active' => $newStatus,
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
                'message' => $statusMessage,
            ]);
        }

        return back()->with('success', $statusMessage);
    }

    /**
     * Alterna el modo del Bot (Simulación / Real).
     */
    public function toggleMode(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return back();
        }

        $newMode = $user->bot_mode === 'real' ? 'simulation' : 'real';

        // Si intenta cambiar a modo real y no está vinculado, mostrar error
        if ($newMode === 'real' && ! $user->isBinanceLinked()) {
            return back()->with('error', 'Requisito de Seguridad: Para operar en modo REAL, primero debes vincular tu cuenta de Binance de manera segura sin permisos de retiro.');
        }

        // Si pasa a simulación, el bot se mantiene (o se cierra preventivamente, pero para US01 mantengámoslo simple)
        $user->update([
            'bot_mode' => $newMode,
        ]);

        return back()->with('success', 'Modo cambiado a: '.strtoupper($newMode));
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
}
