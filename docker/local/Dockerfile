# syntax=docker/dockerfile:1
#
# Imagen del servidor local de farmacia (APP_MODE=local).
# Construye los assets de Vite, instala dependencias PHP y arma un runtime
# php-fpm liviano. El mismo tag sirve para los servicios app/horizon/
# scheduler/sync-agent en docker-compose.yml — solo cambia el `command:`.
#
# Build: docker compose -f docker/local/docker-compose.yml build
# (contexto = raíz del repo, ver docker-compose.yml)

FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources resources
COPY public public
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
        --ignore-platform-reqs

FROM php:8.3-fpm-alpine AS app

RUN apk add --no-cache \
        rsync \
        postgresql-libs \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
        icu-libs \
        oniguruma \
        libwebp \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        postgresql-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        icu-dev \
        oniguruma-dev \
        libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        zip \
        gd \
        bcmath \
        intl \
        mbstring \
        pcntl \
        exif \
    && apk del .build-deps

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN chown -R www-data:www-data storage bootstrap/cache \
    # php-fpm (imagen oficial) corre sus workers como www-data por defecto —
    # se reutiliza ese usuario en vez de crear uno nuevo para evitar
    # desalinear los permisos con el pool de php-fpm.
    # bootstrap/cache/*.php no viaja en el build context (ver .dockerignore)
    # porque el del entorno de desarrollo referencia paquetes --dev (ej.
    # laravel/boost) que no existen en este vendor/ --no-dev. Se regenera
    # aquí contra el vendor/ real de la imagen.
    && php artisan package:discover --no-interaction \
    # Copia de referencia de public/ tal como quedó en la imagen: el
    # entrypoint la usa para refrescar el volumen compartido con nginx en
    # cada arranque (ver docker-compose.yml, volumen app_public).
    && cp -r public /opt/app-public-src

COPY docker/local/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
