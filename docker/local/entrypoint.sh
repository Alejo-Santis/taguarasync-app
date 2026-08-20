#!/bin/sh
set -e

# storage/ y bootstrap/cache/ viven en el volumen nombrado "app_storage",
# que solo se precarga desde la imagen la PRIMERA vez que se crea. Si ese
# volumen quedó con dueño distinto (por ejemplo de una imagen anterior a
# este fix), php-fpm (que corre como www-data) no puede escribir ahí y
# Laravel falla al arrancar sin dejar rastro en su propio log. Se corrige
# en cada arranque — es barato e idempotente.
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# El volumen "app_public" se comparte con el contenedor nginx (que necesita
# los archivos reales de public/build en su propio disco para servirlos como
# estáticos). Un volumen nombrado solo se precarga con el contenido de la
# imagen la PRIMERA vez que se crea, así que en cada arranque lo sincronizamos
# a mano para que una imagen nueva (tras `docker compose build`) no deje
# assets viejos sirviéndose desde el volumen.
if [ -d /opt/app-public-src ]; then
    rsync -a --delete /opt/app-public-src/ /var/www/html/public/
fi

exec "$@"
