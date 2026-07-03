---
created: 2026-07-03
updated: 2026-07-03
---

# Rampa fiat ↔ USDC con MoonPay (on/off ramp)

Permite comprar USDC con EUR (tarjeta/banco) y venderlos de vuelta a un IBAN
usando el **widget alojado de MoonPay**. ViBo nunca toca datos bancarios ni
custodia fondos: el backend solo construye y firma la URL del widget.

## Decisiones clave

1. **Red: Arbitrum, no Optimism.** La especificación original pedía "USDC en
   Optimism + Smart Wallet" (plan dHedge/Synthetix, descartado el 2026-07-02,
   ver [[hyperliquid-channel]]). Se implementó coherente con la arquitectura
   real: el destino es la **wallet propia del usuario vinculada a Hyperliquid**
   (`users.hyperliquid_wallet_address`) y la red por defecto es
   `usdc_arbitrum` (los depósitos a Hyperliquid entran por Arbitrum). La red
   es configurable vía `MOONPAY_CURRENCY_CODE` por si cambia.
2. **Integración sin backend de pagos**: no hay webhooks ni API server-side de
   MoonPay en el MVP. Solo dos páginas GET (`/fondos/anadir` y
   `/fondos/retirar`, rutas `moonpay.buy`/`moonpay.sell`) que incrustan el
   widget en un `<iframe allow="...; payment">`. El flujo completo (importe,
   pago, KYC, envío) ocurre dentro de MoonPay.
3. **Firma de URL obligatoria**: al pasar `walletAddress`/`refundWalletAddress`
   MoonPay exige firmar la URL. `App\Services\MoonPayService` firma con
   `base64(HMAC-SHA256(secret, query string CON la '?' inicial))` y añade
   `&signature=<rawurlencode>` al final. Esto impide que se manipule la
   dirección de destino. La secret key nunca sale del backend.
4. **Guardas**: requiere sesión + `isHyperliquidLinked()` (sin wallet no hay
   destino; redirige a `/hyperliquid/link` con flash `error`, que ahora esa
   vista sí renderiza). Sin claves configuradas (`MOONPAY_API_KEY` +
   `MOONPAY_SECRET_KEY`) las páginas degradan a un aviso "no disponible"
   (no hay modo mock: el sandbox de MoonPay ES el entorno de pruebas).
5. **Off-ramp con pasos manuales**: para retirar, el usuario primero hace
   Withdraw en app.hyperliquid.xyz hacia su wallet (acción user-signed, la
   API wallet no puede) y después envía los USDC a la dirección que le indica
   el widget de venta. La UI lo explica en 3 pasos.
6. **UI**: botones "Añadir fondos"/"Retirar" en la tarjeta Canal de Ejecución
   del dashboard, solo visibles con canal Hyperliquid activo y vinculado.
   Vista única `resources/views/moonpay/ramp.blade.php` parametrizada por
   `$direction` ('buy'|'sell').

## Configuración (.env)

`MOONPAY_API_KEY` (pk_*), `MOONPAY_SECRET_KEY` (sk_*), `MOONPAY_BUY_URL` /
`MOONPAY_SELL_URL` (defaults producción; sandbox:
`https://buy-sandbox.moonpay.com` / `https://sell-sandbox.moonpay.com` con
claves `pk_test_`/`sk_test_`), `MOONPAY_CURRENCY_CODE` (usdc_arbitrum),
`MOONPAY_BASE_CURRENCY_CODE` (eur). Config en `services.moonpay`.

## Tests

`tests/Unit/MoonPayServiceTest` (firma verificada recomputando el HMAC,
parámetros de buy/sell) y `tests/Feature/MoonPayRampTest` (guardas, iframe,
degradación sin claves, visibilidad de accesos por canal).
