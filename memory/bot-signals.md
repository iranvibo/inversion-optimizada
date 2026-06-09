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
