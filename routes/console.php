<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Programar la auditoría de seguridad de Binance cada hora
Schedule::command('binance:verify-permissions')->hourly();

// Sincronizar el balance consolidado de los usuarios vinculados (US03)
Schedule::command('binance:sync-balances')->everyFifteenMinutes();

// Sondeo en tiempo real de la API de señales (US06)
Schedule::command('signals:poll')->everyMinute();
