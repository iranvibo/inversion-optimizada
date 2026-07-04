---
created: 2026-07-02
updated: 2026-07-04
---

# Canal de ejecución Hyperliquid (DEX de perpetuos on-chain)

Canal alternativo a Binance para ejecutar las señales (LONG/SHORT/CLOSE) en real
sin las restricciones de derivados del EEE. Ver [[binance-integration]] para el
canal original.

## Decisiones clave y razones

1. **Por qué Hyperliquid (y no dHedge + Synthetix del plan original)**:
   - El plan inicial (PWA + Privy + MoonPay + pool dHedge + Synthetix Perps en
     Optimism) se aparcó: Synthetix Perps v2 en Optimism está deprecado (v3 fue
     a Base y el protocolo se consolidó en Ethereum), y el SDK de dHedge es solo
     JS (exigiría un microservicio Node junto al backend PHP).
   - Hyperliquid ofrece **API wallets (agentes)**: un par credencial casi 1:1
     con el modelo API key/secret de Binance → encaja directo en la arquitectura
     por usuario existente. API REST simple (`/info` y `/exchange`), alta
     liquidez en perps de BTC, sin KYC ni restricción EEE de derivados.
   - Decisión del usuario (2026-07-02): canal ahora, plataforma PWA después;
     wallet por usuario (no pool); validación directa en mainnet con capital pequeño.

2. **Abstracción de canal (sin tocar el path de Binance)**:
   - `App\Core\Contracts\BrokerInterface` = contrato genérico (los 6 métodos del
     broker). `BinanceBrokerInterface` y `HyperliquidBrokerInterface` lo extienden;
     cada una se bindea a su driver en `AppServiceProvider` (los tests siguen
     mockeando cada canal por separado en el contenedor).
   - `App\Core\Trading\BrokerResolver::forUser($user)` elige el broker según
     `users.trading_channel` ('binance' default | 'hyperliquid'). `isMock($channel)`
     centraliza la regla "no persistir snapshots reales en mock" por canal.
   - Credenciales genéricas: `User::brokerApiKey()/brokerSecretKey()`. En
     Hyperliquid: apiKey = dirección de la wallet principal (0x..., para `/info`),
     secretKey = clave privada de la API wallet/agente (firma `/exchange`).
     Cifradas con cast `encrypted` como las de Binance. `isBrokerLinked()` es el
     gate de activación del bot/modo real (regla 14, ahora por canal activo).
   - Excepciones: `BrokerException` (base) y el **marcador**
     `InvalidBrokerCredentialsInterface` (interfaz vacía) permiten un único
     `catch` agnóstico sin romper la jerarquía Binance existente (PHP no tiene
     herencia múltiple; `BinanceInvalidCredentialsException` conserva su padre).

3. **Firma L1 de Hyperliquid en PHP puro (sin sidecar Node)**:
   - `HyperliquidSigner`: `connectionId = keccak256(msgpack(action) + nonce_8B_BE + 0x00)`
     → phantom agent `{source:'a'|'b', connectionId}` → firma EIP-712 con dominio
     `{name:'Exchange', version:'1', chainId:1337, verifyingContract:0x0}`.
   - Deps composer: `kornrunner/keccak`, `simplito/elliptic-php` (secp256k1,
     k determinista RFC6979), `rybakit/msgpack`.
   - **El orden de las claves msgpack ES la firma**: orders wire = a,b,p,s,r,t;
     action = type,orders,grouping; updateLeverage = type,asset,isCross,leverage.
   - r/s en hex mínimo (sin ceros a la izquierda), v = 27+recid.
   - Validada BYTE A BYTE contra los vectores oficiales del SDK Python
     (tests/signing_test.py) en `tests/Unit/HyperliquidSignerTest.php`. Si esos
     tests fallan, NO usar el canal real.

4. **Mecánica de órdenes** (`HyperliquidBroker`):
   - Estado: `POST /info clearinghouseState` → posición = signo de `szi`
     (BTC), equity = `marginSummary.accountValue`, capital libre = `withdrawable`.
   - "Órdenes de mercado" = límite IoC con precio agresivo mid±slippage (5%),
     como el SDK oficial. Cierre = orden opuesta `reduceOnly` por |szi| exacto.
   - Precio: máx 5 cifras significativas y máx (6−szDecimals) decimales
     (enteros siempre válidos). Tamaño: truncado a szDecimals (BTC=5).
     Metadatos por `/info meta` cacheados 1h con fallback a config.
   - Nocional mínimo de orden: **10 USDC** (si el capital×fracción no llega,
     el exchange rechaza con error legible que se propaga como excepción).
   - Apalancamiento cross vía acción `updateLeverage` antes de abrir (fallo no
     bloqueante, igual que Binance). Nonce = ms monotónico por proceso.
   - Reglas de posición idénticas a Binance (idempotencia, flip cierra-y-abre,
     dimensionamiento capital×fracción×leverage) — duplicadas adrede para no
     tocar el BinanceBroker que ya opera dinero real.

5. **Modelo de seguridad del canal** (promesa "no podemos retirar"):
   - Una API wallet de Hyperliquid **no puede retirar ni transferir** por diseño
     del protocolo (withdraw/transfer son acciones user-signed de la wallet maestra).
   - `checkApiRestrictions` deriva la dirección desde la clave privada pegada:
     si coincide con la wallet principal → `enableWithdrawals=true` → el flujo
     de vinculación la rechaza (el usuario pegó su clave maestra por error).
   - Verificación best-effort del agente con `/info extraAgents` (si el endpoint
     responde y el agente no está aprobado → credenciales inválidas; si no
     responde, no bloquea: una clave no autorizada fallará al firmar la 1ª orden).
   - Misma whitelist de correos que Binance para vincular (fase privada).
   - `binance:verify-permissions` quedó acotado a `trading_channel != 'hyperliquid'`
     para no pausar el bot de un usuario de HL por una clave Binance obsoleta.

6. **Cambio de canal (`POST /bot/switch-channel`)**:
   - Exige el canal destino vinculado. En modo real hace **cierre preventivo en
     el canal ANTERIOR** (fail-safe US04/US07: se loguea critical y se continúa
     si falla), resetea `real_position`/`open_capital`/`live_equity` y, con el
     bot activo, reconcilia contra la señal vigente.
   - Desvincular Hyperliquid siendo el canal activo → bot pausado, modo
     simulación y canal de vuelta a Binance (paridad con desvincular Binance).
   - `binance:sync-balances` ahora sincroniza el balance del canal ACTIVO de
     cada usuario (multicanal, skip por-usuario si su canal está en mock).

7. **Mock del canal** (`HYPERLIQUID_MOCK=true`, default): mismas convenciones
   que Binance — claves con 'invalid' → credenciales inválidas; 'master' o
   'withdraw' → clave con capacidad de retiro; 'fail_close' → fallo al cerrar.
   Posición y contexto de última orden en caché
   (`HyperliquidBroker::mockPositionCacheKey/mockLastOrderCacheKey`).

8. **Independencia de históricos y gráficos (2026-07-02)**:
   - Para evitar que los datos históricos de Binance e Hyperliquid se mezclaran en el mismo gráfico de evolución del balance, se añadió la columna `trading_channel` a la tabla `balance_snapshots` (default 'binance').
   - Tanto la consulta del saldo inicial (`latestSnapshot` en `DashboardController`) como el endpoint de carga del gráfico (`/dashboard/balance` en `BalanceController`) ahora filtran por el canal activo del usuario (`where('trading_channel', $user->tradingChannel())`).
   - Se modificó la directiva de la plantilla `dashboard.blade.php` para activar el script de sondeo en vivo (`pollLiveBalance()`) cuando el canal activo del usuario esté vinculado (`isBrokerLinked()`), permitiendo que el balance de Hyperliquid se actualice en tiempo real al igual que el de Binance.

9. **Soporte para saldo Spot de USDC y Corrección de Doble Contabilidad (2026-07-02 / 2026-07-04)**:
   - Al depositar fondos en Hyperliquid, el balance de USDC se aloja inicialmente en la billetera de Spot de la red L1 (`spotClearinghouseState`) y no en la cuenta de perpetuos (`clearinghouseState`), lo que hacía que `getTotalBalance` reportara `0.0`.
   - Se modificaron `getTotalBalance` y `getAvailableBalance` en `HyperliquidBroker` para consultar y sumar el saldo de USDC de la cuenta Spot junto con el colateral/patrimonio neto de la cuenta de derivados.
   - **Corrección de Doble Contabilidad (2026-07-04):** En las cuentas unificadas de Hyperliquid (*Unified Accounts*), la billetera de Spot reporta el total de USDC de la billetera L1 (el cual incluye el margen comprometido en futuros, puesto que la clave `hold` de Spot solo cuenta órdenes abiertas de spot y no de futuros). Para evitar duplicar esa garantía al sumar el `accountValue` de perpetuos, se resta explícitamente `$state['marginSummary']['totalMarginUsed']` (margen real en uso por futuros) de la suma total. Esto soluciona la discrepancia por la cual el balance "en vivo" flotaba inflado durante una operación y caía repentinamente al cerrar la posición y liberarse el margen.
   - **Requisito ext-gmp**: La librería de firmas elípticas utilizada para conectarse con Hyperliquid (`simplito/elliptic-php`) requiere de forma obligatoria la extensión GMP de PHP. Se actualizó `docker/php/Dockerfile` agregando `gmp` a la directiva `install-php-extensions` para permitir la correcta compilación y evitar el fallo del build de producción.

## Configuración (.env)

`HYPERLIQUID_MOCK`, `HYPERLIQUID_API_URL` (mainnet `https://api.hyperliquid.xyz`,
testnet `https://api.hyperliquid-testnet.xyz`), `HYPERLIQUID_MAINNET` (debe ser
coherente con la URL: decide el byte 'a'/'b' de la firma), `HYPERLIQUID_COIN`
(BTC), `HYPERLIQUID_LEVERAGE` (1 por defecto; BTC admite hasta 40x),
`HYPERLIQUID_SLIPPAGE` (0.05).

## Operativa para validar en real (pendiente tras el despliegue)

1. En app.hyperliquid.xyz: depositar USDC (llega por Arbitrum), More → API →
   Generate (nombrar, p.ej. "ViBo Bot"), aprobar y copiar la clave privada del
   agente (solo se muestra una vez). Ojo: los agentes caducan (máx ~180 días).
2. `.env`: `HYPERLIQUID_MOCK=false`, `HYPERLIQUID_LEVERAGE=1`.
3. Vincular en `/hyperliquid/link` (wallet 0x… + clave del agente), cambiar el
   canal en la tarjeta "Canal de Ejecución" y validar un ciclo LONG→CLOSE con
   capital pequeño (mínimo 10 USDC de nocional por orden).
