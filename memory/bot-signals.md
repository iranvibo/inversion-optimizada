---
created: 2026-06-10
updated: 2026-06-11
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

### Proveedor Mock (por defecto)
* Contrato Laravel `SignalProvider` con drivers `mock` y `http`, seleccionable vía `SIGNALS_PROVIDER` (por defecto `mock`).
* El mock replica el contrato completo (señal actual + histórico) y es la base de los tests automatizados y del desarrollo local.
* El contrato exacto de la API real está pendiente de confirmar con el proveedor; el documentado es el del mock.

### Capital Simulado (modelo híbrido)
* La API externa (o el mock) entrega el **histórico de señales** (fecha, hora, posición, profit) por nivel de riesgo.
* ViBo Invest calcula **localmente** la evolución del capital simulado a partir de esa lista para el gráfico de progreso del dashboard.

### Flujo de Ejecución y Seguridad
* **Custodia Local Cifrada**: Las API Keys de Binance del usuario se almacenan cifradas (AES-256) únicamente en la base de datos de ViBo Invest. **Nunca** se comparten ni se envían al proveedor de señales externo.
* **Filtro de Riesgo (Gatekeeper)**: Ante un cambio de señal, el backend valida las reglas de riesgo locales (Stop Loss diario, Capital Protegido, estado del bot activo/pausado, modo simulación/real) antes de proceder.
* **Ejecución Directa**: Si las validaciones son exitosas y el modo es real, el backend firma y envía la orden a Binance; en modo simulación registra la operación simulada.

### Controles disponibles desde la app (alcance cerrado)
Pausar/activar bot, cambiar nivel de riesgo, alternar simulación/real, y ver estado de datos y gráficas. Nada más.
