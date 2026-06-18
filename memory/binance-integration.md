---
created: 2026-06-11
updated: 2026-06-18
---

> **12. Profit real al cerrar posición (2026-06-18)**: `AdjustPositionJob` (modo real) ya **no** registra el cierre con un beneficio hardcodeado (+1,5%/+15€). Ahora: al abrir LONG/SHORT guarda el equity real (`getTotalBalance`) en `Cache user:{id}:open_capital`; al cerrar (señal CLOSE) hace `Cache::pull` de ese capital (fallback al último snapshot) y calcula `profit = equity_cierre − capital_apertura` y `% = profit/apertura×100` en el helper `recordCloseActivity()`, eligiendo `close_profit`/`close_loss` por el signo. El formateador `BotActivityFormatter` para `close_loss` ahora muestra `% y -valor€` con texto neutro ("posición cerrada con una pérdida del X% (-Y€)") en vez del antiguo "protección de pérdida activada". **Limitación**: un flip directo LONG↔SHORT en un mismo job solo registra la apertura nueva, no el cierre del anterior (adjustPosition no expone qué cerró). Simulación sigue con su +1,5% sintético. `getTotalBalance` se sigue llamando 1 sola vez por job.

# Integración y Seguridad de Binance en ViBo Invest

Este documento detalla las decisiones técnicas y de diseño adoptadas para la integración de la API de Binance, las validaciones de seguridad de permisos de retiro y las claves de prueba (mocks) del sistema.

## Decisiones Clave y Razones

1. **Aislamiento de Llaves y Cifrado (AES-256-GCM)**:
   - *Decisión*: Las API Keys y Secret Keys de Binance de los usuarios se almacenan en la tabla `users` mediante el casting nativo `encrypted` de Laravel.
   - *Justificación*: Impide la exposición accidental de credenciales. Las llaves se cifran automáticamente al guardarse en la base de datos MySQL y solo se descifran en memoria del servidor cuando es estrictamente necesario para firmar transacciones salientes.

2. **Validación de Permisos de Retiro (No-Withdrawals Check)**:
   - *Decisión*: Al vincular la cuenta o en la auditoría periódica, se consulta `/sapi/v1/account/apiRestrictions` y se rechaza la vinculación si `enableWithdrawals` is `true`.
   - *Justificación*: Es la promesa básica de seguridad al usuario minorista. Mitiga el riesgo de que la plataforma pueda transferir o retirar fondos, limitándose únicamente a operar en Spot/Margen si se requiere.

3. **Manejo de Respuestas de Binance en Mocks**:
   - *Decisión*: Cuando `BINANCE_MOCK=true` (entorno local y pruebas), el `BinanceBroker` emula el comportamiento de Binance basándose en los caracteres de las claves ingresadas:
     - **Caso Inválido**: Claves que contienen la palabra `invalid` lanzan una excepción `BinanceInvalidCredentialsException`.
     - **Caso Con Retiros**: Claves que contienen la palabra `withdraw` devuelven `enableWithdrawals => true`, disparando el flujo de rechazo / alerta.
     - **Fallo de Cierre**: Claves que contienen la palabra `fail_close` lanzan una excepción `BinanceException` al intentar cerrar posiciones preventivamente.
     - **Caso Exitoso**: Cualquier otra clave completa la vinculación sin retiros y simula un cierre preventivo exitoso.
   - *Justificación*: Facilita pruebas unitarias e interactivas en el navegador en entornos de desarrollo sin requerir credenciales reales.

4. **Auditoría Periódica de Seguridad en Segundo Plano**:
   - *Decisión*: Se ha programado la tarea Artisan `binance:verify-permissions` en `routes/console.php` para ejecutarse cada hora.
   - *Justificación*: Si el usuario activa manualmente los permisos de retiro en su panel de Binance después de haber vinculado su cuenta con éxito, el bot detectará este cambio en la siguiente ejecución del scheduler, pausando inmediatamente la estrategia del bot en modo real (`bot_active = false`) y guardando un estado de alerta (`binance_withdrawal_alert = true`) para mostrar un banner de advertencia ineludible en el dashboard.

5. **Regla de Mitigación y Cierre Preventivo al Pausar (US04)**:
   - *Decisión*: Al pausar el bot de forma manual o tras una alerta, el sistema solicita a Binance cancelar todas las órdenes abiertas (`DELETE /api/v3/openOrders`) para el par de referencia `BTCEUR` antes de guardar el estado `bot_active = false`.
   - *Justificación*: Evita operaciones no deseadas o huérfanas en el exchange si el bot deja de ser supervisado.
   - *Fail-Safe local*: Si la llamada a la API de Binance falla (ej: problemas de red, claves suspendidas), el error se registra de manera crítica (`Log::critical`) y se le muestra una advertencia al usuario, pero **se continúa con la desactivación local** del bot por seguridad para evitar que el motor de ejecución genere nuevas órdenes.

6. **Cierre Preventivo al Cambiar de Modo Real a Simulación (US07)**:
   - *Decisión*: Al pasar del modo de Dinero Real al modo de Simulación con el bot encendido (`bot_active = true`), el sistema ejecuta un cierre preventivo de posiciones y cancela órdenes abiertas en Binance antes de continuar operando únicamente en simulación.
   - *Justificación*: Previene dejar posiciones reales abiertas y huérfanas en Binance que queden fuera del control del bot una vez que este empiece a operar únicamente en modo simulación.
   - *Fail-Safe local*: Sigue la misma regla de US04; si el broker falla, el error se registra como crítico y se muestra una advertencia, pero se permite que el cambio a simulación prosiga localmente.

7. **Optimización de Balance y Amortiguación de Ruido (US03)**:
   - *Decisión*: Se modificó `handleRealBalance` para solicitar el balance consolidado de Binance usando `quoteAsset => 'EUR'`. Además, se implementó un rango mínimo de escala (5% del balance promedio) en la visualización del gráfico en el frontend.
   - *Justificación*: Al omitir `quoteAsset`, Binance convertía por defecto los saldos a BTC y el backend los volvía a convertir a EUR, introduciendo fluctuaciones artificiales por spreads y desfases. Al forzar `quoteAsset=EUR`, las cuentas con balance estable devuelven un valor estático. El rango mínimo del 5% en el frontend evita que micro-desviaciones menores al 1% (por variaciones normales de cartera) se muestren como picos de sierra agresivos, visualizándose correctamente como una línea estable horizontal.

8. **Apertura de Posiciones en Modo Real: Dimensionamiento y Gestión de Posición (US06, 2026-06-18)**:
   - *Decisión*: `BinanceBrokerInterface::adjustPosition($apiKey, $secretKey, $position, $riskLevel='balanceado')` ahora encapsula toda la gestión de posición consultando el **estado real del exchange** (no la caché de la app) antes de actuar:
     - Se añadió `getOpenPosition($apiKey, $secretKey): string` → `'LONG'|'SHORT'|'CLOSE'` (real: `GET /fapi/v2/positionRisk`, `positionAmt` >0/<0/=0).
     - Reglas: si ya hay posición abierta en la misma dirección **no se reabre**; `CLOSE` solo cierra si hay algo abierto; una señal contraria **cierra y abre** la nueva; cada apertura **reconsulta el capital disponible más actualizado** (`getTotalBalance`).
     - `adjustPosition` devuelve `bool` = *si ejecutó un cambio de estado*. `AdjustPositionJob` usa ese retorno para **no registrar actividad ni snapshot ni evento** cuando la operación es idempotente (no-op), aunque siempre actualiza `user:{id}:real_position` al estado objetivo.
   - *Dimensionamiento (fracción de capital por perfil)*: regla de negocio centralizada en `RiskProfile::capitalFraction()` — **Conservador 20%, Balanceado 50%, Agresivo 90%**. Nocional de la orden = `capital_disponible × fracción × apalancamiento`. `RiskProfile::fromString()` normaliza el `risk_level` (insensible a mayúsculas, fallback Balanceado).
   - *Apalancamiento*: 10x por defecto, configurable en `services.binance.leverage` (`BINANCE_LEVERAGE`). Par configurable en `services.binance.symbol` (`BINANCE_SYMBOL`, def. `BTCEUR`). En real se fija con `POST /fapi/v1/leverage` (fallo no bloqueante: se registra y continúa) y la orden se coloca como market en `POST /fapi/v1/order` con cantidad = `nocional / precio`.
   - *Mock*: el broker mantiene la "posición abierta" en caché (`BinanceBroker::mockPositionCacheKey($apiKey)`) y el contexto de la última orden de apertura (perfil, fracción, apalancamiento, capital, nocional) en `BinanceBroker::mockLastOrderCacheKey($apiKey)` con precio determinista (50000), lo que permite verificar reglas y dimensionamiento en tests sin tocar Binance. `closeOpenPositions` en mock también aplana la posición (la deja en CLOSE).
   - *Justificación*: La fuente de verdad de "¿hay posición abierta?" y "¿cuánto capital hay?" debe ser el exchange en el instante de actuar, no estado cacheado que puede quedar desincronizado por fallos de red o latencia. El retorno idempotente evita duplicar órdenes y actividades.

9. **Path real coherente con Futuros USDⓈ-M (2026-06-18, MVP futuros)**:
   - *Decisión*: La apertura/cierre con apalancamiento es intrínsecamente de **futuros**, no spot. El path real del broker usa endpoints `/fapi/*` en un **host distinto** (`fapi.binance.com`, config `services.binance.futures_url` / `BINANCE_FUTURES_URL`), separado de `api_url` (spot/SAPI, usado por `checkApiRestrictions` y `getTotalBalance` del dashboard):
     - Posición: `GET /fapi/v2/positionRisk` (`fetchRealPositionAmt` → signo de `positionAmt`).
     - Apalancamiento: `POST /fapi/v1/leverage` (fallo no bloqueante).
     - Precio: `GET /fapi/v1/ticker/price`.
     - Órdenes: `POST /fapi/v1/order` (market) vía `sendFuturesMarketOrder($side, $qty, $reduceOnly)`.
     - **Cierre real coherente**: `closeOpenPositions` ahora cancela órdenes (`DELETE /fapi/v1/allOpenOrders`) **y aplana la posición** con una market `reduceOnly` opuesta a `positionAmt`. Esto sirve tanto a la señal CLOSE como al cierre preventivo de pausa/riesgo (US04/US07) en futuros.
   - *Capital para dimensionar*: se añadió `getAvailableBalance()` (`GET /fapi/v2/balance`, `availableBalance` del activo de margen configurado), distinto de `getTotalBalance()` (cartera consolidada en EUR para el gráfico US03). `buildOrderContext` usa `getAvailableBalance`. Mock determinista por clave (`1000 + crc32 % 9000`, sin oscilación).
   - *Símbolo y activo de margen configurables*: `services.binance.symbol` (`BINANCE_SYMBOL`, def. **`BTCUSDT`**) y `services.binance.margin_asset` (`BINANCE_MARGIN_ASSET`, def. `USDT`). Para BTC, `BTCUSDT` es el contrato de futuros más líquido. Si el usuario quiere conservar USDC: `BINANCE_SYMBOL=BTCUSDC` + `BINANCE_MARGIN_ASSET=USDC` (menos liquidez).
   - *Justificación*: con `BTCEUR` y el host de spot, los `/fapi/*` fallaban silenciosamente (excepción tragada en `AdjustPositionJob`) y nunca se abría posición. El path real queda íntegramente sobre futuros; los tests automatizados siguen cubriendo el comportamiento vía mock (el driver real no se ejercita en CI).
   - *Infra local (no es bug de código)*: en Docker, el servicio `queue-worker` (`php artisan queue:work redis`) debe estar levantado o los jobs encolados en Redis no se procesan. `redis` solo resuelve dentro de la red Docker; ejecutar artisan en el host falla con `getaddrinfo for redis failed`.

10. **Selector de modo de trading real: Cross Margin vs Futuros (2026-06-18, España/EEE)**:
    - *Contexto/Decisión*: Binance **restringe los derivados (Futuros) para retail del EEE** (España incluida) por MiCA/CNMV. Para poder ir **largo y corto** sin futuros, el único producto viable es **Cross Margin** (margen de spot con préstamo). Se añadió `services.binance.trade_mode` (`BINANCE_TRADE_MODE`): `'margin'` (default) o `'futures'`. El path real del broker despacha por modo; el mock es agnóstico al modo (no cambia y sigue cubriendo las reglas de posición/dimensionamiento).
    - *Verdad sobre los cortos*: en spot, ponerse corto **exige pedir prestado** (eso es Margin). No existe corto sin préstamo; o Futuros o Margin. Spot puro es solo-largo.
    - *Mecánica Cross Margin* (`/sapi/v1/margin/*`, host spot `api_url`):
      - Posición inferida del **balance neto del activo base** (BTC) en `GET /sapi/v1/margin/account`: `borrowed>dust`→SHORT, `netAsset>dust`→LONG, ~0→CLOSE (umbral 1e-6). No hay objeto "posición" como en futuros.
      - Capital disponible = `free` del activo de margen (USDC/USDT) de la cuenta de margen.
      - Órdenes market vía `POST /sapi/v1/margin/order` con `sideEffectType`: **LONG** = `BUY` con `NO_SIDE_EFFECT` si leverage=1 (colateral propio) o `MARGIN_BUY` si >1 (préstamo del cotizado); **SHORT** = `SELL` con `MARGIN_BUY` (auto-préstamo de BTC); **CLOSE** = orden opuesta con `AUTO_REPAY` (recompra/devuelve lo prestado o vende el BTC mantenido). Cancela órdenes con `DELETE /sapi/v1/margin/openOrders`.
      - Precio: en margin/spot se usa el ticker **spot** (`/api/v3/ticker/price`); en futuros el de `/fapi/`.
    - *Apalancamiento*: Cross Margin BTC/USDC tope **5x** (no 10x). `BINANCE_LEVERAGE=1` = sin apalancamiento (no se pide prestado en LONG). El `.env` del usuario quedó en `trade_mode=margin`, `BTCUSDC`/`USDC`, `BINANCE_LEVERAGE=1`. Confirmado que su cuenta tiene Cross Margin habilitado (BTC/USDC, 5x).
    - *Tests*: el path real de margin se cubre con `Http::fake()` verificando endpoints y `sideEffectType` (LONG→NO_SIDE_EFFECT, SHORT→MARGIN_BUY, CLOSE corto→AUTO_REPAY) e inferencia de posición desde el balance. OJO: múltiples `Http::fake()` en un mismo test **acumulan** stubs (gana el primero que casa) — usar un test por escenario.
    - *Requisito operativo*: los fondos deben estar en el wallet **Cross Margin** (no Spot/Futures), y el nocional debe superar el mínimo de la orden de Binance.
    - *Permisos de la API key (verificado en real 2026-06-18)*: con la key en **solo lectura** (`enableReading=true`, `enableSpotAndMarginTrading=false`), las lecturas (`/sapi/v1/margin/account`) funcionan pero la orden falla con **"You are not authorized to execute this request."**. Para operar Cross Margin la key necesita **Enable Spot & Margin Trading** y, para cortos (auto-borrow `MARGIN_BUY`) y cierres (`AUTO_REPAY`), también **Enable Margin Loan, Repay & Transfer**. Mantener **Withdrawals OFF** (no afecta). La whitelist de IP debe ser la IP saliente del servidor que ejecuta el bot (en local, la IP pública doméstica, normalmente dinámica → en local usar "Unrestricted"; en VPS usar la IP estática).
    - *Validado end-to-end en real*: se abrió un SHORT real (`SELL 0.00017 BTCUSDC` FILLED) que se reflejó en la actividad. La cantidad se redondea HACIA ABAJO al LOT_SIZE (`services.binance.quantity_precision`, 5 decimales para BTC spot/margin; 3 para futuros) para no exceder el saldo ni mandar `0.000`.
    - *Carrera de polling (operativo)*: ejecutar `signals:poll --once` a mano mientras el contenedor `scheduler-worker` también sondea puede producir órdenes duplicadas/cierres inesperados por la caché compartida `signal:last_known_position`. En operación normal solo sondea el scheduler; no lanzar polls manuales en paralelo.
    - *Limitación conocida (no reconciliación)*: el bot actúa solo ante **cambios** de señal (idempotencia por `last_known_position`), no reconcilia "posición objetivo vs real". Si una posición se cierra fuera del bot, `last_known` queda desincronizado y no reabre hasta el siguiente cambio de señal.

11. **Balance Total = patrimonio neto (equity), no saldo libre (2026-06-18)**:
    - *Problema observado en real*: al abrir una posición, el gráfico "Balance Total" (US03) caía en picado (ej. de ~49 a ~39) aunque no hubiera pérdida real. Causa: `getTotalBalance` usaba el endpoint consolidado `/sapi/v1/asset/wallet/balance?quoteAsset=EUR` (`handleRealBalance`, ya eliminado), que no refleja de forma fiable el capital comprometido en una posición Cross Margin abierta; el dinero "desaparecía" del total al comprometerse.
    - *Decisión*: `getTotalBalance` ahora calcula el **equity neto** despachando por `trade_mode`, expresado en el activo de margen (USDC/USDT, que la UI muestra como €):
      - **Margin** (`handleRealEquityMargin`): `netAsset(margen) + netAsset(base) × precio_mercado` a partir de `GET /sapi/v1/margin/account` (`userAssets`) y `getMarketPrice` (ticker spot del símbolo). El `netAsset` del base (BTC) es +en LONG / −en SHORT; valorarlo a mercado incorpora el **P/L latente**, así abrir posición no hace caer el balance (solo lo mueve con la ganancia/pérdida). El precio solo se consulta si hay exposición al base (`baseNet !== 0`).
      - **Futuros** (`handleRealEquityFutures`): `totalMarginBalance` de `GET /fapi/v2/account` (= wallet + unrealized PnL = equity), que tampoco decae al bloquear margen.
    - *Implicación de unidades*: el equity queda en el activo de margen (USDC≈USD) sin conversión a EUR; el endpoint anterior sí convertía a EUR (`quoteAsset=EUR`), por lo que el nivel mostrado puede dar un pequeño escalón (~8%, USD vs EUR) una sola vez en la transición, pero ya no hay caída artificial al operar. Mock (`handleMockBalance`) sin cambios; tests `test_margin_total_balance_*` cubren el cálculo con `Http::fake`.

