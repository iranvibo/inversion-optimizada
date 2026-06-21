---
created: 2026-06-10
updated: 2026-06-19
---

# Origen de las Señales del Bot

El bot de trading utilizado en **ViBo Invest** para ejecutar operaciones en Binance obtiene la información de las señales desde una **API externa al proyecto**, mediante **polling en tiempo casi real** (decisión de 2026-06-11, reemplaza al modelo anterior de webhook + HMAC).

### Modelo de Integración (Polling)
* El scheduler consulta la API externa cada ~5 segundos (intervalo configurable; Laravel 11 soporta scheduling sub-minuto) pasando como parámetro el **nivel de riesgo** (Conservador, Balanceado, Agresivo):
  * **Conservador**: Caída temporal máxima (*drawdown*) de hasta un **15%-20%**.
  * **Balanceado**: Caída temporal máxima (*drawdown*) de hasta un **30%-50%**.
  * **Agresivo**: Caída temporal máxima (*drawdown*) de hasta un **50%-90%**.
* La API responde con la **posición objetivo actual**: `LONG`, `SHORT` o `CLOSE`.
* Se sondea **una vez por nivel de riesgo activo** (máx. 3 consultas/ciclo), no por usuario; el resultado se propaga a todos los usuarios de ese nivel.
* Solo se actúa si la posición difiere de la última conocida (idempotencia). Si la API falla, se mantiene la última posición conocida sin generar órdenes.
* Autenticación saliente: token Bearer sobre HTTPS.

### Por qué polling y no webhook (razón de la decisión)
* La respuesta depende del parámetro `risk_level` → encaja con request/response.
* Contrato basado en estado objetivo: un sondeo nunca "pierde" una señal; un webhook caído desincroniza.
* No expone endpoints públicos entrantes (elimina la necesidad de firma HMAC y su superficie de ataque).
* Hace trivial el mockeo del proveedor.

### Contrato de Señales y Drivers
* **Interfaz**: `App\Core\Contracts\SignalProviderInterface` con dos métodos:
  * `getCurrentSignal(string $riskLevel): array` → `['position' => string, 'issued_at' => string, 'signal_id' => string|int]`
  * `getSignalHistory(string $riskLevel): array` → `array<int, array{date: string, time: string, position: string, profit: float}>`
* **Drivers**:
  * `mock` (`App\Infrastructure\Signals\MockSignalProvider`): Driver por defecto. Carga retornos deterministas históricos diferenciados por perfil. Permite simular cambios de señal en tests sobrescribiendo el cache mediante `Cache::put("mock_signal:{$riskLevel}", 'CLOSE')`.
  * `http` (`App\Infrastructure\Signals\HttpSignalProvider`): Consulta `GET /api/v1/signal` y `GET /api/v1/signals/history` utilizando el Bearer token (por defecto `'mi_token_secreto'` si no se define en `.env`) y configuraciones de timeout y reintentos de `config/signals.php`. La URL base real apunta a `https://trading.vibo-solutions.com`.

### Sondeo Sub-Minuto
* Programado en `routes/console.php` para ejecutarse cada minuto (`Schedule::command('signals:poll')->everyMinute()`).
* Para cumplir el requerimiento de sondeo cada 5 segundos sin dependencias cron sub-minuto externas, la clase `App\Console\Commands\PollSignals` ejecuta un bucle interno durante 55 segundos con pausas `usleep(5)`.
* Admite una opción `--once` (y se activa automáticamente en tests `runningUnitTests()`) para ejecutar un único ciclo y no bloquear.

### Trabajo de Ajuste y Gatekeeper de Riesgo
* Si la señal del proveedor difiere de la última conocida (guardada en cache `signal:last_known_position:{$riskLevel}`), se actualiza el cache y se encola `App\Jobs\AdjustPositionJob` para cada usuario activo con ese nivel.
* **Reconciliación de Deriva por Usuario y en Simulación (Decisión 2026-06-18 y 2026-06-19)**: La idempotencia del sondeo es **global por nivel de riesgo** (`signal:last_known_position:{riskLevel}`), pero la posición ejecutada en modo real es **por usuario** (`user:{id}:real_position`). Si un `AdjustPositionJob` falla al contactar Binance (la excepción se traga en el `catch` y **no** se actualiza `real_position`) o no llega a procesarse, el usuario queda "atascado" en la posición vieja: como la señal global ya coincide con la API, `PollSignals` nunca volvía a encolar el ajuste hasta que la señal cambiara de nuevo (síntoma: la API devuelve LONG pero el dashboard sigue mostrando SHORT). **Solución**: `PollSignals` ahora, además de actuar ante cambios de señal, reconcilia por usuario: para cada usuario activo en modo real cuya `real_position` (si existe en caché) difiera del objetivo, encola un `AdjustPositionJob` aunque la señal global no haya cambiado. `adjustPosition` ya es idempotente (lee el estado real del exchange), por lo que reintentos son seguros. Además, `toggleMode` reconcilia con la señal vigente al pasar a modo real con el bot activo.
* **Reconciliación en Simulación e Integridad del Caché (Decisión 2026-06-19)**: Al pasar a modo simulación, el cambio es no-destructivo/solo de vista: no se cierran las posiciones reales en Binance, no se altera `real_position` en caché ni se pausa el bot. Además, para evitar que el bot quede atascado en una posición simulada antigua (`SHORT` o `CLOSE` en la UI) al activar el bot en simulación, cambiar de modo, o modificar el nivel de riesgo, se ejecuta una reconciliación inmediata con la señal vigente de la API externa (vía `reconcilePositionWithCurrentSignal`). Este proceso actualiza de inmediato el caché global `signal:last_known_position:{$riskLevel}` con la señal obtenida y encola `AdjustPositionJob` para el usuario. De esta forma, el accesor `current_position` en el modelo `User` (que lee del caché) responde al instante con el valor correcto en la respuesta HTTP del endpoint de control, sincronizando el badge de la UI y previniendo que el comando de fondo `PollSignals` detecte una señal desactualizada redundante en su siguiente ciclo.
* **Actualización Asíncrona en Simulación**: al abrir posiciones en modo simulación (`LONG`/`SHORT`), `AdjustPositionJob` ahora emite el evento `BalanceUpdated` con el capital actual para que el frontend actualice el badge de posición y la actividad en tiempo real, evitando que quede desincronizado (atascado en la posición previa).
* **Manejo de Credenciales Inválidas en Jobs**: si `AdjustPositionJob` captura una excepción `BinanceInvalidCredentialsException` al intentar operar en real, pausa inmediatamente el bot (`bot_active = false`), pone las posiciones en caché a `CLOSE` y despacha el evento `BotStatusUpdated` para que el dashboard refleje el cambio de inmediato, deteniendo el bucle infinito de reintentos fallidos en el scheduler.
* **Gatekeeper de Riesgo**: En la ejecución del job, se validan los límites locales parametrizables desde `config/signals.php` o mediante variables de entorno en el `.env`:
  * **Stop Loss diario** (`DAILY_STOP_LOSS_LIMIT`): Umbral de drawdown diario máximo (por defecto `0.05` / 5% del saldo inicial del día). Si se supera, detiene el bot (`bot_active = false`), ejecuta el cierre preventivo en Binance y registra `stop_loss_trigger`. Se puede desactivar estableciéndolo a `1.00` (100%).
  * **Capital Protegido** (`PROTECTED_CAPITAL_LIMIT`): Umbral mínimo de capital respecto al capital estimado inicial (por defecto `0.80` / 80%). Si el balance cae por debajo de este valor, detiene el bot, cierra posiciones y genera una alerta. Se puede desactivar estableciéndolo a `0.00` (0%).
* **Modo Real vs Simulación**:
  * **Real**: Llama al método `adjustPosition` de `BinanceBrokerInterface` (que realiza el cierre/cancelación preventiva y coloca la orden de mercado), recupera el balance real de Binance y registra el log feed en lenguaje natural.
  * **Simulación**: Registra logs simulados, calcula el nuevo saldo con el profit del trade, crea un snapshot y notifica al dashboard en tiempo real (`BalanceUpdated`).
  * **Seguimiento de Posición Actual y Aislamiento de Historial** (Decisión 2026-06-19):
    * En **Simulación**, se lee la posición de la API externa (o mock) asociada al nivel de riesgo (`signal:last_known_position:{$riskLevel}`).
    * En **Real**, se lee la posición efectivamente ejecutada en el exchange (`user:{$userId}:real_position` en caché), asegurando que fallos de red/API o retrasos en Binance no distorsionen la información de la cartera real del usuario.
    * Si el bot se pausa o se activa una regla de seguridad/riesgo, ambas posiciones se fuerzan inmediatamente a `CLOSE`. Al pausar el bot manualmente, si existía una posición abierta (`LONG` o `SHORT`), se ejecuta el cierre preventivo correspondiente y se registra el evento en `bot_activities` calculando el profit real (modo real) o simulando el de +1.5% (modo simulación), además de sincronizar el balance y los snapshots.
    * **Aislamiento de actividades**: La tabla `bot_activities` cuenta con una columna `bot_mode` que clasifica cada registro como `'real'` o `'simulation'`. Al alternar de modo, la vista "Actividad" filtra las transacciones para mostrar exclusivamente las del modo activo, previniendo que los logs reales se mezclen con el historial dinámico del simulador.
    * **Origen del Feed de Actividad por Modo (Decisión 2026-06-21)**: En `DashboardController::getActivitiesForUser`, el feed se construye de forma distinta según el modo y **ya no hay precedencia de las filas simuladas persistidas**:
      * **Real**: muestra **solo** las filas de `bot_activities` con `bot_mode = 'real'` (operaciones realmente ejecutadas en el exchange).
      * **Simulación**: muestra **siempre** el historial derivado de `SignalProviderInterface::getSignalHistory` (API externa en prod, mock en dev). Las filas con `bot_mode = 'simulation'` que el bot pueda persistir se **ignoran** para la presentación.
      * **Motivo / síntoma corregido**: antes, un `if ($dbActivities->isNotEmpty())` cortocircuitaba y devolvía solo las pocas filas simuladas en BD, ocultando las ~150 entradas que la API externa (`trading.vibo-solutions.com/api/v1/signals/history`) sí devuelve. Síntoma reportado: "en prod solo se ven 4 entradas en la simulación". La curva del gráfico (`BalanceController::history`) nunca tuvo esa precedencia: siempre usa la API y recorta por rango (default "Mes").
      * **Tests**: los tests que validan render/orden/alerta de actividades persistidas se fijaron a `bot_mode = 'real'`; los de simulación mockean `getSignalHistory`.

### Historial de Actividad en Lenguaje Humano (US05)
* **Registro de Acciones**: Cada acción del bot (compra por caída temporal, venta por beneficio/pérdida, o activación de protección de riesgo) se guarda en la tabla `bot_activities`.
* **Clasificación y Tipos de Actividades** (Decisión 2026-06-15):
  * **Inversión al Alza (`LONG` / tipo `'long'` o `'buy'`):** Se muestra con el texto *"Se inició una inversión al alza (LONG) esperando una subida del precio."* e icono verde de tendencia hacia arriba.
  * **Inversión a la Baja (`SHORT` / tipo `'short'`):** Se muestra con el texto *"Se inició una inversión a la baja (SHORT) esperando una caída del precio."* e icono rojo/rosa de tendencia hacia abajo.
  * **Cierre de Operación (`CLOSE` / tipo `'close'` o `'sell'`):** Se muestra con el texto *"Inversión finalizada: posición cerrada con [beneficio/pérdida]."* e icono gris de círculo de verificación (checkmark circle).
  * **Protección de Riesgo (`risk_protection`):** Alertas de drawdown diario/seguridad e icono de escudo rojo.
* **Traducción Dinámica**: Un formateador puro (`BotActivityFormatter`) traduce automáticamente los códigos y rendimientos a un lenguaje natural en español de forma que sea simple y claro para usuarios inexpertos.
* **Ordenación de Eventos Simultáneos (Decisión 2026-06-16)**: Cuando se cierra una operación y se abre otra al mismo segundo (mismo timestamp, p.ej. `CLOSE` y `SHORT`), la apertura (`LONG`/`SHORT`) se ordena por encima del cierre (`CLOSE`) en la lista descendente (más reciente primero). Esto previene malentendidos donde parecía que el cierre correspondía a la nueva posición.
* **Cálculo de Capital sin Colisiones**: Para calcular el retorno en euros (`profit_value`) de cada operación en simulación sin que se sobreescriban los datos de eventos simultáneos, se indexa la serie usando una clave compuesta `{timestamp}_{position}` en lugar de usar solo el timestamp.
* **Herramienta de Simulación**: Un endpoint `/bot/simulate-activity` permite sembrar eventos simulados directamente desde la interfaz para facilitar las pruebas manuales y los criterios de aceptación.
* **Actualización Dinámica sin Recarga (Decisión 2026-06-16)**: Para evitar recargar la página tras alternar el modo (Simulación/Real) o recibir notificaciones de estado, el feed de actividades y las alertas de riesgo se recargan de forma asíncrona mediante AJAX (`/dashboard/activities`) al alternar a la pestaña "Actividad", ante cambios en la configuración del bot (cambio de modo, riesgo o activación) y al recibir eventos WebSockets (Laravel Echo).
* **Resolución de Alertas al Activar el Bot (Decisión 2026-06-19)**: Cuando el usuario reactiva manualmente el bot (cambiando `bot_active` a `true`), todas las alertas de riesgo activas del usuario se resuelven en la base de datos (`risk_alert = false`). Esto elimina el banner destacado de la parte superior de la pestaña "Actividad" (que informaba que el bot se había pausado) tan pronto como el bot vuelve a estar activo, aunque mantiene el registro del evento intacto en el historial cronológico.

### Notas de Integración y Despliegue (2026-06-14)
* **Aislamiento en Tests**: Se configuró `SIGNALS_PROVIDER=mock` de manera explícita en `phpunit.xml` para evitar que la suite de pruebas unitarias/feature realice peticiones de red reales al proveedor externo.
* **Incidencia de Despliegue de la API**: Durante la puesta en marcha, se detectó que el pipeline de GitHub Actions del proyecto externo `btc-signals` (`deploy-bot.yml`) omitía el directorio `api/` en la propiedad `source` de la acción de copia SSH (`scp-action`). Se corrigió añadiendo `api/**` al origen para asegurar que los scripts controladores del endpoint estén presentes en el servidor VPS `trading.vibo-solutions.com`.
* **Verificación de Producción**: Se probó con éxito la conexión y respuesta en vivo contra `https://trading.vibo-solutions.com/api/v1/signal` y `/api/v1/signals/history`, obteniendo las respuestas correctas en JSON para cada nivel de riesgo con el Bearer token provisto.

### Diferenciación de Curva de Capital (Junio 2026)
* **Incidencia de Equidades Idénticas**: Se detectó que los niveles agresivo y balanceado devolvían la misma curva de capital en modo simulación (con el proveedor HTTP). La causa raíz es que la API externa (`btc-signals`) devolvía el profit calculado sobre el margen (importe invertido en el trade) en lugar del capital total. Al usar las mismas señales de entrada/salida, los profits porcentuales sobre margen eran idénticos y anulaban el efecto del tamaño de posición (90% vs 50% vs 20%), provocando curvas duplicadas al calcular `$capital *= 1 + profit` en la app Laravel.
* **Solución**: Se modificó `BtcStrategy.php` en la API externa para calcular un nuevo campo `account_profit` (`$tradeNetPnl / $activePosition['capitalBeforeEntry']`), que refleja la variación porcentual real sobre la cuenta total. El endpoint `/api/v1/signals/history` mapea este `account_profit` al campo `profit` de la respuesta JSON para preservar la compatibilidad con la app Laravel y sus cálculos del gráfico sin requerir cambios en este repositorio.

### Evolución del Gráfico con Valor en Vivo (Junio 2026)
* **Visualización del Saldo en Vivo en la Gráfica**: Para evitar discrepancias entre el balance numérico de la cabecera (que fluctúa en vivo en modo real cada 5 segundos mediante sondeo de la API o eventos de WebSocket) y la curva de la gráfica de rendimiento, el valor en vivo se inyecta dinámicamente en el cliente como el último punto de la serie de datos (`isLive: true`).
* **Sondeo Dinámico Resiliente al Cambio de Modo**: Para asegurar que el sondeo comience automáticamente al pasar del modo Simulación al modo Real sin requerir una recarga completa de la página, la directiva de compilación de Blade se condiciona únicamente a la existencia de la cuenta vinculada de Binance (`@if($user->isBinanceLinked())`), delegando la inactividad durante el modo Simulación al backend (que responde `live: false`). Al alternar de modo, el estado de la serie se actualiza y el gráfico se adapta de inmediato.

