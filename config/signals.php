<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver del proveedor de señales
    |--------------------------------------------------------------------------
    |
    | Contrato SignalProvider (ver docs/architecture.md §1 y §3.1):
    |   "mock" → driver interno por defecto; usado en desarrollo y tests.
    |   "http" → API externa real de señales (polling con Bearer token).
    |
    */

    'provider' => env('SIGNALS_PROVIDER', 'mock'),

    /*
    |--------------------------------------------------------------------------
    | Intervalo de sondeo (segundos)
    |--------------------------------------------------------------------------
    |
    | Frecuencia del polling de la posición objetivo por nivel de riesgo.
    | Laravel soporta scheduling sub-minuto (everyFiveSeconds, etc.).
    |
    */

    'poll_seconds' => (int) env('SIGNALS_POLL_SECONDS', 5),

    /*
    |--------------------------------------------------------------------------
    | Driver http — API externa
    |--------------------------------------------------------------------------
    */

    'http' => [
        'base_url' => env('SIGNALS_HTTP_BASE_URL'),
        'token' => env('SIGNALS_HTTP_TOKEN', 'mi_token_secreto'),
        'timeout' => (int) env('SIGNALS_HTTP_TIMEOUT', 5),
        'retries' => (int) env('SIGNALS_HTTP_RETRIES', 2),
    ],

];
