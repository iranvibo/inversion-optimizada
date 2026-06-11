---
created: 2026-06-10
updated: 2026-06-11
---

# Decisiones de Arquitectura de ViBo Invest

Este archivo detalla las decisiones fundamentales de arquitectura tomadas para **ViBo Invest**, sus justificaciones y configuraciones técnicas clave.

## Stack Tecnológico Elegido y Justificación

1. **Laravel 11**:
   * *Decisión*: Usar Laravel 11 como el framework backend.
   * *Justificación*: Ofrece una estructura ligera, soporte de colas robusto y optimizaciones de rendimiento nativas. Incluye soporte nativo para servidores de WebSockets mediante Laravel Reverb, lo que evita depender de servicios externos de terceros (como Pusher) que incrementan costes y complejidad.
2. **Laravel Reverb + Redis**:
   * *Decisión*: Servidor de WebSockets autohospedado con Redis como broker Pub/Sub.
   * *Justificación*: Permite transmitir actualizaciones en vivo del saldo de Binance y estado del bot (Activo, Pausado, Simulación) directamente al navegador de forma reactiva y escalable. Redis gestiona la mensajería rápida entre los workers de cola y el servidor WebSocket.
3. **Redis para Gestión de Colas (Queues)**:
   * *Decisión*: Utilizar Redis para ejecutar trabajos en segundo plano (auditoría de llaves API y órdenes de trading).
   * *Justificación*: El procesamiento de órdenes de criptomonedas debe ser inmediato y asíncrono para evitar bloquear el servidor web (HTTP request blocking) y garantizar que la plataforma sea altamente responsiva.
4. **Integración de Señales por Polling (reemplaza al Webhook + HMAC, 2026-06-11)**:
   * *Decisión*: El scheduler sondea la API externa de señales en tiempo casi real (~5s, sub-minuto de Laravel 11) pasando el nivel de riesgo; la API responde la posición objetivo (`LONG`/`SHORT`/`CLOSE`). Autenticación saliente con token Bearer.
   * *Justificación*: La respuesta depende del nivel de riesgo (request/response natural), el contrato basado en estado nunca "pierde" señales, no se exponen endpoints públicos entrantes (elimina HMAC y su superficie de ataque) y simplifica el mockeo. Ver detalles en [bot-signals.md](bot-signals.md).
   * *Mock*: Contrato `SignalProvider` con drivers `mock` (por defecto, para dev/tests) y `http` (API real), vía `SIGNALS_PROVIDER`.
5. **Cifrado de API Keys de Binance (AES-256-GCM)**:
   * *Decisión*: Las API Keys y Secret Keys de los usuarios se almacenan en MySQL cifradas simétricamente a través del backend de Laravel.
   * *Justificación*: Cumple con el aislamiento estricto de credenciales; las claves nunca se transmiten al proveedor de señales externo ni a ninguna entidad fuera de los servidores de ViBo Invest.
6. **Estructura Docker Multietapa (Producción)**:
   * *Decisión*: Separar la compilación de assets de frontend (Node/Vite) del runtime del backend en PHP-FPM, resultando en imágenes de producción ultra-ligeras y seguras.
   * *Justificación*: Minimiza la superficie de ataque al no incluir dependencias de desarrollo (npm, node_modules) en el contenedor final.

## Conexiones e Integración de Red en Docker

El sistema se ejecuta en una red interna puente (`vibo-network`) donde los contenedores se comunican de forma aislada:
* El contenedor `web` (Nginx) y `websocket` (Reverb) son los únicos expuestos al tráfico de red pública.
* La base de datos `db` (MySQL) y el broker de colas `redis` permanecen ocultos y protegidos dentro de la red Docker, accesibles solo por los contenedores PHP.
