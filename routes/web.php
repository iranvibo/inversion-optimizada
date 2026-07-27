<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BalanceController;
use App\Http\Controllers\BinanceConnectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HyperliquidConnectionController;
use App\Http\Controllers\MoonPayController;
use App\Http\Controllers\OnboardingController;
use Illuminate\Support\Facades\Route;

// Redirección inicial
Route::get('/', function () {
    return redirect()->route('login');
});

// Autenticación
// Los endpoints que aceptan credenciales o tokens llevan rate limiting para
// frenar fuerza bruta, credential stuffing y abuso de verificación de tokens.
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/auth/google', [AuthController::class, 'google'])->name('auth.google')->middleware('throttle:10,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Política de privacidad
Route::view('/privacidad', 'legal.privacy')->name('privacy');
Route::view('/terminos', 'legal.terms')->name('terms');

// Rutas protegidas
Route::middleware('auth')->group(function () {
    // Onboarding interactivo (US02)
    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('/onboarding/simulate', [OnboardingController::class, 'simulate'])->name('onboarding.simulate');
    Route::post('/onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/activities', [DashboardController::class, 'activities'])->name('dashboard.activities');

    // Evolución del balance (US03)
    Route::get('/dashboard/balance', [BalanceController::class, 'history'])->name('dashboard.balance');
    // Patrimonio neto en vivo para refrescar la cabecera con el P/L de la posición
    Route::get('/dashboard/balance/live', [BalanceController::class, 'live'])->name('dashboard.balance.live');

    Route::post('/bot/toggle', [DashboardController::class, 'toggleBot'])->name('bot.toggle');
    Route::post('/bot/toggle-mode', [DashboardController::class, 'toggleMode'])->name('bot.toggle-mode');
    Route::post('/bot/update-risk', [DashboardController::class, 'updateRisk'])->name('bot.update-risk');
    // Canal por el que se ejecutan las operaciones en real (Binance ↔ Hyperliquid)
    Route::post('/bot/switch-channel', [DashboardController::class, 'switchChannel'])->name('bot.switch-channel');

    // Vinculación de Binance. El alta de credenciales se limita para no permitir
    // martillear la API del exchange (y agotar su rate limit) desde una sesión.
    Route::get('/binance/link', [BinanceConnectionController::class, 'showLink'])->name('binance.link');
    Route::post('/binance/link', [BinanceConnectionController::class, 'storeLink'])->name('binance.store')->middleware('throttle:6,1');
    Route::post('/binance/disconnect', [BinanceConnectionController::class, 'disconnect'])->name('binance.disconnect');

    // Vinculación de Hyperliquid (canal de ejecución on-chain alternativo).
    // Mismo throttle que Binance para no martillear la verificación de credenciales.
    Route::get('/hyperliquid/link', [HyperliquidConnectionController::class, 'showLink'])->name('hyperliquid.link');
    Route::post('/hyperliquid/link', [HyperliquidConnectionController::class, 'storeLink'])->name('hyperliquid.store')->middleware('throttle:6,1');
    Route::post('/hyperliquid/disconnect', [HyperliquidConnectionController::class, 'disconnect'])->name('hyperliquid.disconnect');

    // Rampa fiat ↔ USDC (widget de MoonPay). Páginas GET que solo construyen
    // la URL firmada del widget; el pago y el KYC ocurren dentro de MoonPay.
    Route::get('/fondos/anadir', [MoonPayController::class, 'buy'])->name('moonpay.buy');
    Route::get('/fondos/retirar', [MoonPayController::class, 'sell'])->name('moonpay.sell');

    // Eliminación definitiva de la cuenta (GDPR)
    Route::delete('/account', [AuthController::class, 'deleteAccount'])->name('account.delete');

    // Administración de usuarios (pestaña "Usuarios" del dashboard). Solo
    // accesible para el administrador definido en config('app.admin_email').
    Route::middleware('can:admin')->group(function () {
        Route::get('/admin/users', [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
        Route::post('/admin/invitations', [AdminUserController::class, 'storeInvitation'])->name('admin.invitations.store');
    });

    // Herramientas de simulación / QA. MUTAN datos y credenciales reales del
    // usuario (p. ej. la simulación de alerta sobrescribe la API Key cifrada),
    // así que solo se registran cuando DEMO_TOOLS_ENABLED está activo. En
    // producción permanecen inexistentes (404) salvo activación explícita.
    if (config('app.demo_tools_enabled')) {
        Route::post('/binance/simulate-balance-sync', [BalanceController::class, 'simulateSync'])->name('binance.simulate-balance-sync');
        Route::post('/bot/simulate-activity', [DashboardController::class, 'simulateActivity'])->name('bot.simulate-activity');
        Route::post('/binance/simulate-alert', [DashboardController::class, 'triggerWithdrawalSimulation'])->name('binance.simulate-alert');
    }
});
