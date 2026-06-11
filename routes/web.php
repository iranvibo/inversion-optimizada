<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BinanceConnectionController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Redirección inicial
Route::get('/', function () {
    return redirect()->route('login');
});

// Autenticación
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/login/auto', [AuthController::class, 'autoLogin'])->name('login.auto');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Rutas protegidas
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/bot/toggle', [DashboardController::class, 'toggleBot'])->name('bot.toggle');
    Route::post('/bot/toggle-mode', [DashboardController::class, 'toggleMode'])->name('bot.toggle-mode');
    
    // Vinculación de Binance
    Route::get('/binance/link', [BinanceConnectionController::class, 'showLink'])->name('binance.link');
    Route::post('/binance/link', [BinanceConnectionController::class, 'storeLink'])->name('binance.store');
    Route::post('/binance/disconnect', [BinanceConnectionController::class, 'disconnect'])->name('binance.disconnect');

    // Simulación de Alertas de Seguridad
    Route::post('/binance/simulate-alert', [DashboardController::class, 'triggerWithdrawalSimulation'])->name('binance.simulate-alert');
});

