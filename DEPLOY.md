# Desplegar Taguara Sync en AWS — instalación manual (sin Docker)

Guía paso a paso para instalar el servidor cloud directamente sobre Ubuntu
en una instancia EC2: Nginx + PHP-FPM + PostgreSQL nativos, el repo servido
desde `/var/www/taguara-sync-app`.

> Si en algún momento quieres la variante con Docker (más fácil de mover
> entre servidores, más fácil de actualizar sin tocar el sistema operativo),
> ya está lista y probada en `docker/cloud/DEPLOY.md`. Esta guía es la
> instalación tradicional, sin dependencias nuevas que aprender hoy.

**Qué vas a instalar:** Nginx, PHP 8.3-FPM con sus extensiones, PostgreSQL
16, Composer, Node.js 20 (solo para compilar el frontend una vez), y dos
procesos en segundo plano: un worker de colas (systemd) y el scheduler
(cron real).

---

## 0. Prerrequisitos

- Cuenta de AWS con el free tier de 180 días activo.
- Una llave SSH (`.pem`) para conectarte a la instancia.
- El código accesible en un repositorio Git (GitHub, etc.).
- Credenciales reales de SMTP y de Nextpyme (facturación electrónica) para
  cuando el piloto pase de pruebas a uso real.

---

## 1. Crear la instancia EC2

1. Consola de AWS → **EC2** → **Launch instance**.
2. **AMI:** Ubuntu Server 24.04 LTS.
3. **Tipo de instancia:** al menos 4 GB de RAM (`t3.medium` o equivalente
   dentro de tu plan gratuito).
4. **Par de llaves:** crea uno nuevo o usa uno existente.
5. **Security Group:**
   - SSH (22) — idealmente restringido a tu IP.
   - HTTP (80) — abierto (`0.0.0.0/0`).
   - HTTPS (443) — abierto, aunque lo actives después.
6. **Almacenamiento:** 20 GB o más.
7. **Elastic IP (recomendado):** asígnala a la instancia para que la IP no
   cambie si la reinicias — EC2 → **Elastic IPs** → **Allocate** →
   **Associate**.

---

## 2. Conectarte y actualizar el sistema

```bash
chmod 400 tu-llave.pem
ssh -i tu-llave.pem ubuntu@<IP-DE-TU-INSTANCIA>

sudo apt update && sudo apt upgrade -y
```

**Swap (recomendado):** compilar los assets de Vite puede picar de memoria.

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

---

## 3. Instalar PHP 8.3 y sus extensiones

Ubuntu 24.04 ya trae PHP 8.3 en sus repositorios oficiales — no hace falta
ningún PPA de terceros.

```bash
sudo apt install -y php8.3-fpm php8.3-cli php8.3-pgsql php8.3-gd \
  php8.3-bcmath php8.3-intl php8.3-mbstring php8.3-xml php8.3-zip \
  php8.3-curl php8.3-exif

php -v
# PHP 8.3.x
```

---

## 4. Instalar Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

---

## 5. Instalar Node.js 20 (solo para compilar el frontend)

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v && npm -v
```

---

## 6. Instalar y configurar PostgreSQL

```bash
sudo apt install -y postgresql postgresql-contrib
sudo systemctl enable --now postgresql

sudo -u postgres psql <<'EOF'
CREATE ROLE taguara WITH LOGIN PASSWORD 'CAMBIA_ESTA_CLAVE';
CREATE DATABASE taguara_cloud OWNER taguara;
EOF
```

Genera una contraseña real en vez del placeholder:

```bash
openssl rand -hex 16
```

---

## 7. Instalar Nginx

```bash
sudo apt install -y nginx
sudo systemctl enable --now nginx
```

---

## 8. Traer el código

```bash
sudo mkdir -p /var/www/taguara-sync-app
sudo chown $USER:$USER /var/www/taguara-sync-app
git clone <URL-DE-TU-REPOSITORIO> /var/www/taguara-sync-app
cd /var/www/taguara-sync-app
```

Si el repositorio es privado, usa un token de acceso personal de GitHub
como contraseña al clonar, o configura una llave de deploy.

---

## 9. Configurar `.env`

```bash
cp .env.example .env
nano .env
```

Completa como mínimo:

| Variable | Valor |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `http://<tu-Elastic-IP>` (o `https://tu-dominio.com` una vez lo tengas) |
| `APP_MODE` | `cloud` |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` | `127.0.0.1` |
| `DB_DATABASE` | `taguara_cloud` |
| `DB_USERNAME` | `taguara` |
| `DB_PASSWORD` | la que generaste en el paso 6 |
| `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` | `database` (ya vienen así por defecto — no necesitas Redis) |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD` | tu proveedor SMTP real |
| `FE_API_TOKEN` | token de producción de Nextpyme |
| `FE_ENABLED` | `true` cuando tengas el token real |

---

## 10. Instalar dependencias y compilar el frontend

```bash
composer install --no-dev --optimize-autoloader --prefer-dist
npm ci
npm run build
```

---

## 11. Permisos

PHP-FPM en Ubuntu corre como el usuario `www-data` — `storage/` y
`bootstrap/cache/` deben ser escribibles por ese usuario:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

---

## 12. Clave de la aplicación, migraciones y seeds

```bash
php artisan key:generate --show
# copia el "base64:..." dentro de APP_KEY= en .env

php artisan migrate --force
php artisan db:seed --force
```

El seed crea roles, permisos, catálogos DIAN, catálogos farmacéuticos
globales y el super admin:

```
Email:    superadmin@taguara.local
Password: SuperAdmin2026#**
```

**Cambia esa contraseña de inmediato** desde **Perfil** una vez inicies
sesión.

Cachea configuración y rutas para producción:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 13. Configurar Nginx

```bash
sudo nano /etc/nginx/sites-available/taguara-sync
```

```nginx
server {
    listen 80;
    server_name <tu-IP-o-dominio>;
    root /var/www/taguara-sync-app/public;
    index index.php;

    client_max_body_size 64M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_read_timeout 300;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires max;
        log_not_found off;
        access_log off;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/taguara-sync /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

---

## 14. Worker de colas (systemd)

Procesa la emisión de facturas electrónicas y notas crédito en segundo
plano. Un servicio systemd lo mantiene vivo y lo reinicia si se cae.

```bash
sudo nano /etc/systemd/system/taguara-queue.service
```

```ini
[Unit]
Description=Taguara Sync - Queue Worker
After=network.target postgresql.service

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
ExecStart=/usr/bin/php /var/www/taguara-sync-app/artisan queue:work --tries=3 --max-time=3600
WorkingDirectory=/var/www/taguara-sync-app

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now taguara-queue
sudo systemctl status taguara-queue
```

---

## 15. Scheduler (cron real)

A diferencia de la variante Docker, aquí no hace falta simular un loop —
usas el cron de verdad:

```bash
sudo crontab -u www-data -e
```

Agrega esta línea:

```
* * * * * cd /var/www/taguara-sync-app && php artisan schedule:run >> /dev/null 2>&1
```

Esto corre las alertas de inventario/FE, el reintento de contingencia y el
chequeo de facturación definidos en `routes/console.php`.

---

## 16. Crear la farmacia del piloto

```bash
sudo -u www-data php artisan taguara:setup-tenant
```

Es un asistente interactivo: pide nombre comercial, NIT, ciudad y datos del
propietario, y de paso configura la sucursal principal, laboratorios,
categorías, medios de pago y el cliente "Consumidor final" — todo lo que
la farmacia necesita para operar desde el primer día.

(Alternativa: crear el tenant desde `/admin/tenants` con el super admin,
pero ahí tendrías que configurar sucursal y catálogos a mano después.)

---

## 17. Verificar

```bash
curl http://localhost/up
# {"status":"up",...}
```

Desde el navegador: `http://<tu-IP-o-dominio>/login`. Recorrido mínimo antes
de invitar al cliente: abrir turno de caja → venta de prueba → cerrar
caja → si `FE_ENABLED=true`, confirmar que la factura se emite.

---

## 18. Dominio y HTTPS

Con Nginx nativo esto es más simple que con Docker:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d tu-dominio.com
```

Certbot edita el `server` block automáticamente para servir HTTPS y
programa su propia renovación (verifica con
`sudo systemctl status certbot.timer`). Actualiza `APP_URL=https://tu-dominio.com`
en `.env` y corre `php artisan config:cache` de nuevo.

---

## 19. Seguridad

- **Security Group:** restringe SSH (22) a tu IP una vez termines.
- **`.env`:** nunca lo subas a git, no lo compartas sin cifrar.
- **Firewall local (opcional, además del security group de AWS):**
  ```bash
  sudo ufw allow OpenSSH
  sudo ufw allow 'Nginx Full'
  sudo ufw enable
  ```
- **Actualizaciones del sistema:** `sudo apt update && sudo apt upgrade -y`
  periódicamente.

---

## 20. Backups

```bash
mkdir -p /home/ubuntu/backups
cat <<'EOF' | sudo tee /usr/local/bin/backup-taguara-db.sh
#!/bin/bash
source /var/www/taguara-sync-app/.env
export PGPASSWORD="$DB_PASSWORD"
pg_dump -h 127.0.0.1 -U "$DB_USERNAME" "$DB_DATABASE" | gzip > /home/ubuntu/backups/db-$(date +%Y%m%d-%H%M).sql.gz
find /home/ubuntu/backups -name "*.sql.gz" -mtime +14 -delete
EOF
sudo chmod +x /usr/local/bin/backup-taguara-db.sh
(crontab -l 2>/dev/null; echo "0 3 * * * /usr/local/bin/backup-taguara-db.sh") | crontab -
```

Guarda 14 días de backups locales — suficiente para el piloto. Antes de
producción real con más clientes, súbelos también a S3.

---

## Actualizar la aplicación (redeploy)

```bash
cd /var/www/taguara-sync-app
git pull

composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

sudo systemctl restart taguara-queue
sudo systemctl reload php8.3-fpm
```

---

## Troubleshooting

**Error 502 Bad Gateway:** PHP-FPM no está corriendo o el socket no
coincide con el de `nginx.conf`.
```bash
sudo systemctl status php8.3-fpm
ls /run/php/
```

**La página carga sin estilos ni JS:** el build de Vite no corrió o falló.
```bash
npm run build
```

**Permisos denegados escribiendo en `storage/`:**
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
```

**Ver logs de Laravel:**
```bash
tail -f /var/www/taguara-sync-app/storage/logs/laravel-*.log
```

**Ver logs de Nginx:**
```bash
sudo tail -f /var/log/nginx/error.log
```

**El worker de colas no procesa nada:**
```bash
sudo systemctl status taguara-queue
sudo journalctl -u taguara-queue -f
```

---

## Checklist final antes de invitar al cliente

- [ ] `APP_DEBUG=false` en `.env`
- [ ] `APP_KEY` generada
- [ ] SMTP real configurado y probado
- [ ] `FE_API_TOKEN` de producción, `FE_ENABLED=true`
- [ ] Dominio + HTTPS configurados (paso 18)
- [ ] Security group con SSH restringido a tu IP
- [ ] `taguara-queue.service` activo (`systemctl status taguara-queue`)
- [ ] Cron del scheduler agregado para `www-data`
- [ ] Backup diario funcionando
- [ ] Super admin con contraseña propia (no la del seeder)
- [ ] Farmacia del piloto creada con `taguara:setup-tenant`
- [ ] Recorrido manual completo probado: abrir turno → vender → cerrar caja → FE
