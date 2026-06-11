---
created: 2026-06-11
updated: 2026-06-11
---

# Integración y Seguridad de Binance en ViBo Invest

Este documento detalla las decisiones técnicas y de diseño adoptadas para la integración de la API de Binance, las validaciones de seguridad de permisos de retiro y las claves de prueba (mocks) del sistema.

## Decisiones Clave y Razones

1. **Aislamiento de Llaves y Cifrado (AES-256-GCM)**:
   - *Decisión*: Las API Keys y Secret Keys de Binance de los usuarios se almacenan en la tabla `users` mediante el casting nativo `encrypted` de Laravel.
   - *Justificación*: Impide la exposición accidental de credenciales. Las llaves se cifran automáticamente al guardarse en la base de datos MySQL y solo se descifran en memoria del servidor cuando es estrictamente necesario para firmar transacciones salientes.

2. **Validación de Permisos de Retiro (No-Withdrawals Check)**:
   - *Decisión*: Al vincular la cuenta o en la auditoría periódica, se consulta `/sapi/v1/account/apiRestrictions` y se rechaza la vinculación si `enableWithdrawals` es `true`.
   - *Justificación*: Es la promesa básica de seguridad al usuario minorista. Mitiga el riesgo de que la plataforma pueda transferir o retirar fondos, limitándose únicamente a operar en Spot/Margen si se requiere.

3. **Manejo de Respuestas de Binance en Mocks**:
   - *Decisión*: Cuando `BINANCE_MOCK=true` (entorno local y pruebas), el `BinanceBroker` emula el comportamiento de Binance basándose en los caracteres de las claves ingresadas:
     - **Caso Inválido**: Claves que contienen la palabra `invalid` lanzan una excepción `BinanceInvalidCredentialsException`.
     - **Caso Con Retiros**: Claves que contienen la palabra `withdraw` devuelven `enableWithdrawals => true`, disparando el flujo de rechazo / alerta.
     - **Caso Exitoso**: Cualquier otra clave completa la vinculación sin retiros.
   - *Justificación*: Facilita pruebas unitarias e interactivas en el navegador en entornos de desarrollo sin requerir credenciales reales.

4. **Auditoría Periódica de Seguridad en Segundo Plano**:
   - *Decisión*: Se ha programado la tarea Artisan `binance:verify-permissions` en `routes/console.php` para ejecutarse cada hora.
   - *Justificación*: Si el usuario activa manualmente los permisos de retiro en su panel de Binance después de haber vinculado su cuenta con éxito, el bot detectará este cambio en la siguiente ejecución del scheduler, pausando inmediatamente la estrategia del bot en modo real (`bot_active = false`) y guardando un estado de alerta (`binance_withdrawal_alert = true`) para mostrar un banner de advertencia ineludible en el dashboard.
