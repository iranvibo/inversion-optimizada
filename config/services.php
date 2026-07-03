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
    | Canal de ejecución Hyperliquid (DEX de futuros perpetuos on-chain).
    | Alternativa a Binance sin las restricciones de derivados del EEE: el
    | usuario delega una API wallet (agente) que puede operar pero no retirar.
    */
    'hyperliquid' => [
        'mock' => env('HYPERLIQUID_MOCK', true),
        // API pública. Mainnet: https://api.hyperliquid.xyz
        //              Testnet: https://api.hyperliquid-testnet.xyz
        'api_url' => env('HYPERLIQUID_API_URL', 'https://api.hyperliquid.xyz'),
        // Red usada al firmar (phantom agent 'a' mainnet / 'b' testnet).
        // Debe ser coherente con api_url o el exchange rechazará la firma.
        'is_mainnet' => filter_var(env('HYPERLIQUID_MAINNET', true), FILTER_VALIDATE_BOOL),
        // Activo de perpetuos operado (universe de Hyperliquid).
        'coin' => env('HYPERLIQUID_COIN', 'BTC'),
        // Apalancamiento objetivo (cross). BTC admite hasta 40x en Hyperliquid;
        // por prudencia el valor por defecto es 1 (sin apalancamiento).
        'leverage' => (int) env('HYPERLIQUID_LEVERAGE', 1),
        // Tolerancia de deslizamiento de las "órdenes de mercado" (límite IoC
        // con precio agresivo), como en el SDK oficial (5%).
        'slippage' => (float) env('HYPERLIQUID_SLIPPAGE', 0.05),
        // Fallbacks del metadato del activo si /info meta no responde.
        // BTC: índice 0 del universe, tamaño con 5 decimales (szDecimals).
        'asset_index' => (int) env('HYPERLIQUID_ASSET_INDEX', 0),
        'sz_decimals' => (int) env('HYPERLIQUID_SZ_DECIMALS', 5),
    ],

    /*
    | Rampa fiat ↔ cripto (MoonPay). El widget alojado por MoonPay procesa el
    | pago con tarjeta/banco y el KYC del usuario; ViBo Invest nunca ve datos
    | bancarios. El backend solo construye la URL del widget y la firma con la
    | secret key para que la dirección de destino no pueda manipularse.
    */
    'moonpay' => [
        // Clave publicable (pk_live_*/pk_test_*): viaja en la URL del widget.
        'api_key' => env('MOONPAY_API_KEY'),
        // Clave secreta (sk_live_*/sk_test_*): NUNCA sale del backend, solo
        // firma las URLs (HMAC-SHA256). Sin ambas claves la rampa se desactiva.
        'secret_key' => env('MOONPAY_SECRET_KEY'),
        // Widgets alojados. Sandbox: https://buy-sandbox.moonpay.com y
        // https://sell-sandbox.moonpay.com (con claves pk_test_/sk_test_).
        'buy_url' => env('MOONPAY_BUY_URL', 'https://buy.moonpay.com'),
        'sell_url' => env('MOONPAY_SELL_URL', 'https://sell.moonpay.com'),
        // USDC en Arbitrum: la red por la que se deposita en Hyperliquid.
        // (El plan antiguo con Optimism quedó descartado junto a Synthetix.)
        'currency_code' => env('MOONPAY_CURRENCY_CODE', 'usdc_arbitrum'),
        // Divisa fiat por defecto del widget (el usuario paga/recibe en esta).
        'base_currency_code' => env('MOONPAY_BASE_CURRENCY_CODE', 'eur'),
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
