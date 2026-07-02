# syntax=docker/dockerfile:1

# =============================================================================
# ViBo Invest — Dockerfile multietapa
#
# Etapas (ver docs/architecture.md §5):
#   1. assets      → Node + Vite: compila Tailwind 4 / JS de producción
#   2. vendor      → Composer: dependencias PHP optimizadas (--no-dev)
#   3. production  → Imagen final PHP-FPM limpia (sin Node ni Composer)
#   4. web         → Nginx con los archivos estáticos ya compilados
#
# Target adicional:
#   dev            → PHP-FPM + Composer para desarrollo (código bind-mounted)
# =============================================================================

# ---------------------------------------------------------------------------
# Base PHP compartida: extensiones requeridas por Laravel 12 + MySQL + colas
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS base

COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions pdo_mysql bcmath pcntl opcache zip intl gmp \
    && rm -rf /tmp/* /var/cache/apk/*

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# Etapa 1: compilación de assets de frontend (Vite + Tailwind)
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json vite.config.js ./
RUN npm ci --no-audit --no-fund

COPY resources/ resources/
COPY public/ public/

# Variables VITE_* embebidas en el bundle del cliente (no son secretos)
ARG VITE_APP_NAME="ViBo Invest"
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST=localhost
ARG VITE_REVERB_PORT=8080
ARG VITE_REVERB_SCHEME=http
ARG VITE_FIREBASE_API_KEY
ARG VITE_FIREBASE_AUTH_DOMAIN
ARG VITE_FIREBASE_PROJECT_ID
ARG VITE_FIREBASE_STORAGE_BUCKET
ARG VITE_FIREBASE_MESSAGING_SENDER_ID
ARG VITE_FIREBASE_APP_ID

RUN npm run build

# ---------------------------------------------------------------------------
# Etapa 2: dependencias PHP de producción
# ---------------------------------------------------------------------------
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# ---------------------------------------------------------------------------
# Etapa 3: imagen final de producción (PHP-FPM)
# ---------------------------------------------------------------------------
FROM base AS production

ENV APP_ENV=production \
    APP_DEBUG=false

# Código + vendor optimizado, sin herramientas de build
COPY --from=vendor --chown=www-data:www-data /var/www/html /var/www/html
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build

# Configuración opcache recomendada para producción
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=192'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/opcache-prod.ini \
    # Permisos mínimos: solo storage y bootstrap/cache son escribibles
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

USER www-data

EXPOSE 9000
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# Etapa 4: Nginx de producción con assets estáticos incluidos
# ---------------------------------------------------------------------------
FROM nginx:alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=production /var/www/html/public /var/www/html/public

EXPOSE 80

# ---------------------------------------------------------------------------
# Target de desarrollo: Composer incluido, el código se monta como volumen
# ---------------------------------------------------------------------------
FROM base AS dev

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

EXPOSE 9000
CMD ["php-fpm"]
