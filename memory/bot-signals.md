---
created: 2026-06-10
updated: 2026-06-10
---

# Origen de las Señales del Bot

El bot de trading utilizado en **ViBo Invest** para ejecutar operaciones en Binance obtiene la información y los datos de las señales desde una **API externa al proyecto**. 

### Detalles Clave
* El backend de ViBo Invest no calcula ni genera las señales internamente.
* Actúa como el motor de ejecución, validación de riesgos (Stop Loss, Capital Protegido) y sincronización con el exchange.
* La API externa suministra de forma independiente las señales de compra y venta.

### Flujo de Ejecución y Seguridad
* **Custodia Local Cifrada**: Las API Keys de Binance del usuario se almacenan cifradas (AES-256) únicamente en la base de datos de ViBo Invest. **Nunca** se comparten ni se envían al proveedor de señales externo.
* **Modelo de Ejecución (Gateway / Webhook)**: El proveedor externo publica o notifica las señales de trading al backend de ViBo Invest.
* **Filtro de Riesgo (Gatekeeper)**: Cuando ViBo Invest recibe una señal, el backend realiza las validaciones de riesgo locales (Stop Loss diario, Capital Protegido, estado del bot activo/pausado) antes de proceder con la transacción.
* **Ejecución Directa**: Si las validaciones son exitosas, el backend de ViBo Invest firma y envía la orden a Binance. Esto garantiza que la lógica de mitigación de pérdidas se ejecute de manera ineludible y centralizada.

