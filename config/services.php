<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'binance' => [
        'mock' => env('BINANCE_MOCK', true),
        // Spot/SAPI (restricciones de API y balance consolidado de cartera).
        'api_url' => env('BINANCE_API_URL', 'https://api.binance.com'),
        // USDⓈ-M Futures (apertura/cierre de posiciones con apalancamiento). Host distinto al de spot.
        'futures_url' => env('BINANCE_FUTURES_URL', 'https://fapi.binance.com'),
        // Modo de ejecución del trading real (US06):
        //   'margin'  → Cross Margin (largo y corto vía /sapi/v1/margin/*). Único viable en EEE/España si futuros está restringido.
        //   'futures' → USDⓈ-M Futures (/fapi/*). Apalancamiento alto, restringido para retail en EEE.
        'trade_mode' => env('BINANCE_TRADE_MODE', 'margin'),
        // Par operado, su activo cotizado/margen y apalancamiento objetivo (US06).
        // En Cross Margin sin apalancamiento usa BINANCE_LEVERAGE=1.
        'symbol' => env('BINANCE_SYMBOL', 'BTCUSDT'),
        'margin_asset' => env('BINANCE_MARGIN_ASSET', 'USDT'),
        'leverage' => (int) env('BINANCE_LEVERAGE', 10),
        // Decimales de la cantidad (LOT_SIZE) del par. Spot/Margin de BTC usa 5 (0.00001);
        // Futuros USDⓈ-M de BTC usa 3 (0.001). Ajusta según el modo/par.
        'quantity_precision' => (int) env('BINANCE_QTY_PRECISION', 5),
    ],

    /*
    | Configuración de Firebase. Sólo se necesita el project_id para verificar
    | en el backend los ID tokens de Google. Las claves públicas que usa el SDK
    | de JavaScript se exponen al navegador mediante las variables VITE_FIREBASE_*.
    */
    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
    ],

];
