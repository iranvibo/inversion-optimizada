---
created: 2026-06-11
updated: 2026-06-18
---

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

