---
created: 2026-06-10
updated: 2026-06-20
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

El sistema se ejecuta en una red interna puente (`vibo-network`):
* **Producción (Coexistencia)**: Dado que el host VPS comparte puertos con otros sitios web, los contenedores de producción exponen sus puertos mapeados únicamente a localhost (`127.0.0.1`), protegiendo los servicios:
  - `web` (Nginx Docker) mapeado a `127.0.0.1:8091`.
  - `websocket` (Laravel Reverb) mapeado a `127.0.0.1:8092`.
  - El Nginx nativo del host VPS actúa como el Proxy Inverso principal, escuchando en el puerto público `80/443` y redirigiendo el tráfico del dominio a estos puertos.
* La base de datos `db` (MySQL) y el broker de colas `redis` permanecen ocultos y protegidos dentro de la red Docker, accesibles solo por los contenedores PHP.

## Infraestructura Implementada — detalles no obvios

* **Archivos**: `docker/php/Dockerfile` (targets: `base`, `assets`, `vendor`, `production`, `web`, `dev`), `docker/nginx/default.conf`, `docker-compose.yml` (dev, código bind-mounted), `docker-compose.prod.yml` (imágenes inmutables), `config/signals.php`.
* **Cliente Redis = `predis`** (no phpredis/pecl): evita compilar extensiones en las imágenes; configurado vía `REDIS_CLIENT=predis`.
* **Reverb hosts duales**: en dev el backend publica hacia `REVERB_HOST=websocket` (DNS interno de Docker) y el navegador usa `VITE_REVERB_HOST=localhost:8080`. En prod, el backend publica hacia `REVERB_HOST=websocket`, pero el navegador se conecta al dominio público de producción sobre HTTPS (puerto `443`), el cual es redirigido por el Nginx del host hacia el puerto local `8092`.
* **MySQL dev expuesto en `127.0.0.1:33060`** (no 3306): el 3306 del host lo ocupa otro contenedor de otro proyecto.
* **Nginx cachea la IP del upstream `app`**: tras `docker compose build && up` que recrea `app`, el `web` devuelve 502 hasta hacer `docker compose restart web`.
* **`php artisan install:broadcasting` falla sin TTY** (en la CI): completar a mano con variables `REVERB_*` en `.env` y dependencias en frontend.
* **Assets en dev**: Vite corre en el host (`npm run dev`), no en contenedor, para evitar conflictos de binarios nativos (esbuild) en `node_modules` bind-mounted entre macOS y Alpine.
* Verificado el 2026-06-11: HTTP 200 vía Nginx, handshake WS 101 en Reverb (`pusher:connection_established`), cache y colas Redis funcionando (worker consumió job de prueba), migraciones aplicadas en MySQL 8.0.44.

## Despliegue y CI/CD (IONOS VPS)

* **Pipeline**: Configurado en `.github/workflows/deploy.yml`. Se ejecuta automáticamente al hacer push a las ramas `master` y `feature-entrega2-IVB`, o de manera manual desde la pestaña Actions de GitHub (`workflow_dispatch`).
* **Proceso de Compilación**:
  * Se delega completamente a la construcción interna del Dockerfile multietapa en el VPS. El runner de GitHub Actions ya no pre-compila los assets ni instala dependencias de Composer, acelerando el flujo de CI/CD.
* **Transferencia (rsync)**: Copia el código fuente del proyecto al directorio `/var/www/vibo-invest/` del VPS. Ahora **se incluyen** la carpeta `docker/` y el archivo `docker-compose.prod.yml`. Se excluyen carpetas de desarrollo (`node_modules`, `.git`, `tests`, `docs`, `memory`, etc.), carpetas de persistencia del host (`storage/app/*`, `public/storage`), y la carpeta `vendor/` (ya que se compila de forma inmutable dentro del contenedor).
* **Post-Despliegue**: Se conecta vía SSH para iniciar y actualizar el stack de contenedores Docker:
  * Reconstruye y levanta los servicios: `docker compose -f docker-compose.prod.yml up -d --build`.
  * Ejecuta migraciones en el contenedor de la aplicación: `docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force`.
  * Optimiza la configuración y caché en el contenedor (`config:cache`, `route:cache`, `view:cache`).
  * Crea el enlace simbólico para storage dentro del contenedor.

### Topología real de servido en producción y gotchas de despliegue (Decisión/Incidencia 2026-06-21)
* **El dominio público lo sirve un nginx del HOST (Ubuntu), no el contenedor**: `https://invest.vibo-solutions.com` la termina un `nginx/1.18.0 (Ubuntu)` del VPS (vhost gestionado por Certbot en `/etc/nginx/sites-enabled/invest.vibo-solutions.com`) que hace `proxy_pass` a `http://127.0.0.1:8091` (contenedor `web`, nginx alpine) y `:8092` (Reverb/WebSocket). Los contenedores publican **solo en localhost** (`127.0.0.1:8091->80`, `127.0.0.1:8092->8080`). El `Server: nginx/1.18.0 (Ubuntu)` en las cabeceras es siempre el host (no implica bypass). El sitio `default` (`root /var/www/html/public` + php8.2-fpm del host) y `learning`/`trading` son otros vhosts; no afectan a invest.
* **Gotcha env_file: cambios en el `.env` requieren RECREAR, no `restart`**: el `.env` de producción se gestiona **a mano en el VPS** (`/var/www/vibo-invest/.env`) y está **excluido del rsync**. Se inyecta vía `env_file: .env`, que solo se relee al **recrear** el contenedor (`docker compose -f docker-compose.prod.yml up -d --force-recreate ...`), NO con `restart`. Tras cambiar el `.env`: recrear contenedores + `php artisan config:cache`. Como prod usa `opcache.validate_timestamps=0`, los cambios de código/config solo se ven al recrear/reiniciar php-fpm.
* **Gotcha 502 tras recrear solo `app`**: si recreas `app` (nueva IP en la red Docker) pero no `web`, el nginx del contenedor `web` mantiene la IP vieja cacheada en `fastcgi_pass app:9000` → 502. Solución: `docker compose restart web` (o recrear todo junto con `up -d`).
* **Variables críticas del `.env` de prod a verificar tras cada cambio**: `SIGNALS_PROVIDER=http` (por defecto `mock` → la simulación mostraría solo las 8 señales mock en vez de las ~153 de la API externa; ver [[bot-signals]]), `SIGNALS_HTTP_BASE_URL`, `SIGNALS_HTTP_TOKEN`, y `BINANCE_MOCK=false` (por defecto `true` → el balance "real" sería ficticio; ver [[binance-integration]]). Verificación dentro del contenedor: `php artisan tinker --execute="echo config('signals.provider'); echo config('services.binance.mock');"`.
