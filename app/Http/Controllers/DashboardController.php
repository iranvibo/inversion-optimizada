<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
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

        return view('dashboard', compact('user', 'latestSnapshot'));
    }

    /**
     * Alterna la activación del Bot.
     */
    public function toggleBot(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return back()->with('error', 'Sesión no válida.');
        }

        // Si intenta encender el bot en modo Real sin Binance vinculado, lo bloquea (US07 Escenario 3)
        if (! $user->bot_active && $user->bot_mode === 'real' && ! $user->isBinanceLinked()) {
            return back()->with('error', 'Operación Bloqueada: Para activar el bot en modo REAL debes vincular una cuenta de Binance autorizada.');
        }

        $user->update([
            'bot_active' => ! $user->bot_active,
        ]);

        $statusMessage = $user->bot_active ? 'Bot ACTIVADO con éxito.' : 'Bot PAUSADO de inmediato.';

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
