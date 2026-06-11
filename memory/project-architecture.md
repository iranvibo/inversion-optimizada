---
created: 2026-06-10
updated: 2026-06-11
---

# Decisiones de Arquitectura de ViBo Invest

Este archivo detalla las decisiones fundamentales de arquitectura tomadas para **ViBo Invest**, sus justificaciones y configuraciones técnicas clave.

## Stack Tecnológico Elegido y Justificación

1. **Laravel 12 (PHP 8.4) — reemplaza a Laravel 11 (2026-06-11)**:
   * *Decisión*: Usar Laravel 12 como el framework backend, con contenedores `php:8.4-fpm-alpine`.
   * *Justificación*: Laravel 11 alcanzó su EOL de seguridad en marzo de 2026: composer **bloquea** la instalación de todo `laravel/framework` 11.x por avisos de seguridad sin parche. Laravel 12 mantiene la misma estructura minimalista, Reverb nativo y scheduling sub-minuto. PHP 8.4 es obligatorio: el `composer.lock` (resuelto con PHP 8.5 del host) exige `>= 8.4.1`, por lo que `php:8.3-fpm` rompe con `platform_check.php`.
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

## Infraestructura Implementada (2026-06-11) — detalles no obvios

* **Archivos**: `docker/php/Dockerfile` (targets: `base`, `assets`, `vendor`, `production`, `web`, `dev`), `docker/nginx/default.conf`, `docker-compose.yml` (dev, código bind-mounted), `docker-compose.prod.yml` (imágenes inmutables), `config/signals.php` (wiring de `SIGNALS_PROVIDER`).
* **Cliente Redis = `predis`** (no phpredis/pecl): evita compilar extensiones en las imágenes; configurado vía `REDIS_CLIENT=predis`.
* **Reverb hosts duales**: el backend publica eventos hacia `REVERB_HOST=websocket` (DNS interno de Docker); el navegador usa `VITE_REVERB_HOST=localhost:8080`. No unificar — son rutas de red distintas.
* **MySQL dev expuesto en `127.0.0.1:33060`** (no 3306): el 3306 del host lo ocupa otro contenedor de otro proyecto.
* **Nginx cachea la IP del upstream `app`**: tras `docker compose build && up` que recrea `app`, el `web` devuelve 502 hasta hacer `docker compose restart web`.
* **`php artisan install:broadcasting` falla sin TTY** (en shells no interactivas): completar a mano con `vendor:publish --tag=reverb-config`, variables `REVERB_*` en `.env` y `npm i -D laravel-echo pusher-js`.
* **Assets en dev**: Vite corre en el host (`npm run dev` / `npm run build`), no en contenedor, para evitar conflictos de binarios nativos (esbuild/rollup) en `node_modules` bind-mounted entre macOS y Alpine.
* Verificado el 2026-06-11: HTTP 200 vía Nginx, handshake WS 101 en Reverb (`pusher:connection_established`), cache y colas Redis funcionando (worker consumió job de prueba), migraciones aplicadas en MySQL 8.0.44.
