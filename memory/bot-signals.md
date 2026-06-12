---
created: 2026-06-10
updated: 2026-06-12
---

# Origen de las Señales del Bot

El bot de trading utilizado en **ViBo Invest** para ejecutar operaciones en Binance obtiene la información de las señales desde una **API externa al proyecto**, mediante **polling en tiempo casi real** (decisión de 2026-06-11, reemplaza al modelo anterior de webhook + HMAC).

### Modelo de Integración (Polling)
* El scheduler consulta la API externa cada ~5 segundos (intervalo configurable; Laravel 11 soporta scheduling sub-minuto) pasando como parámetro el **nivel de riesgo** (Conservador, Balanceado, Agresivo).
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
  * `http` (`App\Infrastructure\Signals\HttpSignalProvider`): Consulta `GET /api/v1/signal` y `GET /api/v1/signals/history` utilizando el Bearer token y configuraciones de timeout y reintentos de `config/signals.php`.

### Sondeo Sub-Minuto
* Programado en `routes/console.php` para ejecutarse cada minuto (`Schedule::command('signals:poll')->everyMinute()`).
* Para cumplir el requerimiento de sondeo cada 5 segundos sin dependencias cron sub-minuto externas, la clase `App\Console\Commands\PollSignals` ejecuta un bucle interno durante 55 segundos con pausas `usleep(5)`.
* Admite una opción `--once` (y se activa automáticamente en tests `runningUnitTests()`) para ejecutar un único ciclo y no bloquear.

### Trabajo de Ajuste y Gatekeeper de Riesgo
* Si la señal del proveedor difiere de la última conocida (guardada en cache `signal:last_known_position:{$riskLevel}`), se actualiza el cache y se encola `App\Jobs\AdjustPositionJob` para cada usuario activo con ese nivel.
* **Gatekeeper de Riesgo**: En la ejecución del job, se validan los siguientes límites locales:
  * **Stop Loss diario**: Si el saldo del usuario decrece un 5% o más respecto al primer balance del día (desde las 00:00:00), detiene el bot (`bot_active = false`), ejecuta el cierre preventivo en Binance y registra `stop_loss_trigger`.
  * **Capital Protegido**: Si el balance decrece por debajo del 80% del `estimated_capital` inicial, detiene el bot, cierra posiciones y genera una alerta de riesgo.
* **Modo Real vs Simulación**:
  * **Real**: Llama al método `adjustPosition` de `BinanceBrokerInterface` (que realiza el cierre/cancelación preventiva y coloca la orden de mercado), recupera el balance real de Binance y registra el log feed en lenguaje natural.
  * **Simulación**: Registra logs simulados, calcula el nuevo saldo con el profit del trade, crea un snapshot y notifica al dashboard en tiempo real (`BalanceUpdated`).

### Historial de Actividad en Lenguaje Humano (US05)
* **Registro de Acciones**: Cada acción del bot (compra por caída temporal, venta por beneficio/pérdida, o activación de protección de riesgo) se guarda en la tabla `bot_activities`.
* **Traducción Dinámica**: Un formateador puro (`BotActivityFormatter`) traduce automáticamente los códigos y rendimientos a un lenguaje natural en español (e.g., "Se realizó una compra para aprovechar una caída temporal de precio" o "Protección de pérdida activada para asegurar tu capital").
* **Alertas de Riesgo**: Las alertas de riesgo (como el stop-loss diario) se guardan con `risk_alert = true` y se destacan en la interfaz mediante un diseño de alerta diferenciado en la parte superior.
* **Herramienta de Simulación**: Un endpoint `/bot/simulate-activity` permite sembrar eventos simulados directamente desde la interfaz para facilitar las pruebas manuales y los criterios de aceptación.

