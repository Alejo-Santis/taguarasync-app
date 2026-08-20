# Desplegar Taguara Sync en AWS (piloto)

Guía paso a paso para poner el servidor cloud en una instancia EC2, usando el
paquete de `docker/cloud/` que ya está construido y probado en este repo.

**Qué vas a levantar:** `app` (Laravel) + `nginx` + `db` (PostgreSQL) +
`queue-worker` (facturación electrónica, notas crédito) + `scheduler`
(alertas, reintentos). Sin Redis ni Horizon — no hacen falta para este
volumen de tráfico.

---

## 0. Prerrequisitos

- Cuenta de AWS con el free tier de 180 días activo.
- Una llave SSH (`.pem`) para conectarte a la instancia.
- El código de este repositorio accesible (GitHub, o copiarlo por `scp`).
- Credenciales reales de: SMTP (envío de correo) y Nextpyme (facturación
  electrónica). El sandbox de Mailtrap y `FE_ENABLED=false` sirven para
  probar, pero no para el piloto con el cliente.

---

## 1. Crear la instancia EC2

1. Consola de AWS → **EC2** → **Launch instance**.
2. **AMI:** Ubuntu Server 24.04 LTS (misma familia que usa el servidor local,
   para mantener todo consistente).
3. **Tipo de instancia:** una con al menos 4 GB de RAM (`t3.medium` o
   equivalente dentro de tu plan gratuito).
4. **Par de llaves:** crea uno nuevo o usa uno existente — lo necesitas para
   entrar por SSH.
5. **Configuración de red (Security Group):** por ahora permite:
   - SSH (22) — solo desde tu IP, no `0.0.0.0/0`, si la consola te lo permite.
   - HTTP (80) — desde cualquier lugar (`0.0.0.0/0`).
   - HTTPS (443) — desde cualquier lugar, aunque lo actives después.
6. **Almacenamiento:** el valor por defecto (mínimo 20 GB) es suficiente
   para el piloto.
7. **IP elástica (recomendado):** asigna una Elastic IP a la instancia para
   que la dirección no cambie si la reinicias. Consola EC2 → **Elastic IPs**
   → **Allocate** → **Associate** con tu instancia.
8. Lanza la instancia y espera a que quede en estado `running`.

---

## 2. Conectarte y preparar el servidor

```bash
chmod 400 tu-llave.pem
ssh -i tu-llave.pem ubuntu@<IP-DE-TU-INSTANCIA>
```

Instalar Docker y Docker Compose:

```bash
sudo apt update && sudo apt upgrade -y
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER
# Cierra la sesión SSH y vuelve a entrar para que el grupo "docker" tome efecto
exit
ssh -i tu-llave.pem ubuntu@<IP-DE-TU-INSTANCIA>
docker --version
docker compose version
```

**Swap (recomendado incluso con 4 GB):** el build de la imagen compila
dependencias PHP y compila los assets de Vite, lo que puede picos de
memoria. Un swapfile de 2 GB da margen sin costo:

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

---

## 3. Traer el código a la instancia

```bash
sudo mkdir -p /opt/taguara
sudo chown $USER:$USER /opt/taguara
git clone <URL-DE-TU-REPOSITORIO> /opt/taguara
cd /opt/taguara/docker/cloud
```

Si el repositorio es privado, usa un [token de acceso personal de GitHub]
como contraseña al clonar, o configura una llave de deploy.

---

## 4. Configurar el archivo `.env`

```bash
cp .env.cloud.example .env
nano .env
```

Completa como mínimo:

| Variable | Cómo obtenerla |
|---|---|
| `APP_KEY` | Se genera en el paso 5 (déjala vacía por ahora) |
| `APP_URL` | `http://<tu-Elastic-IP>` o `https://tu-dominio.com` si ya tienes uno |
| `DB_PASSWORD` | Genera una con `openssl rand -hex 16` |
| `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD` | Tu proveedor SMTP real (no Mailtrap sandbox) |
| `FE_API_TOKEN` | Token de producción de Nextpyme |
| `FE_ENABLED` | `true` solo cuando tengas el token real listo |

`CLOUD_SYNC_SECRET` puedes dejarlo vacío — no lo necesitas hasta que exista
un servidor local que sincronizar (ver `docs/GUIA_SERVIDOR_LOCAL.md`).

---

## 5. Construir y levantar el stack

```bash
docker compose build
docker compose up -d
```

La primera vez, el servicio `migrate` corre las migraciones automáticamente
antes de que arranquen `app`, `queue-worker` y `scheduler` — no necesitas
ejecutar `php artisan migrate` a mano.

Verifica que todo esté arriba:

```bash
docker compose ps
# app, nginx, db, queue-worker, scheduler deben estar "Up"
# migrate debe aparecer como "Exited (0)" — es normal, corre una sola vez
```

Genera la `APP_KEY` (si la dejaste vacía) y reinicia:

```bash
docker compose exec app php artisan key:generate --show
# copia el valor "base64:..." dentro de .env en APP_KEY=
docker compose restart app queue-worker scheduler
```

---

## 6. Seeds iniciales (catálogos globales + super admin)

```bash
docker compose exec app php artisan db:seed --force
```

Esto crea roles y permisos, los catálogos DIAN (tipos de documento,
municipios, impuestos), los catálogos farmacéuticos globales (unidades,
principios activos) y el usuario super admin:

```
Email:    superadmin@taguara.local
Password: SuperAdmin2026#**
```

**Cambia esa contraseña inmediatamente** — inicia sesión y ve a
**Perfil** para actualizarla.

---

## 7. Crear la farmacia del piloto

Tienes dos caminos:

**Opción A — asistente interactivo (recomendado, deja todo listo):**

```bash
docker compose exec -it app php artisan taguara:setup-tenant
```

Te va a pedir nombre comercial, NIT, ciudad, datos del propietario, etc., y
además de crear el tenant configura la sucursal principal, laboratorios,
categorías, medios de pago y el cliente "Consumidor final" — todo lo que
la farmacia necesita para empezar a operar el mismo día.

**Opción B — desde el panel de administración:**

1. Entra a `http://<tu-IP-o-dominio>/login` con el super admin.
2. Ve a `/admin/tenants` → **Nueva farmacia**.
3. Esto crea el tenant y el usuario propietario, pero **sin** la sucursal,
   catálogos ni medios de pago base — tendrás que configurarlos a mano
   desde **Configuración** antes de operar.

---

## 8. Verificar que todo funciona

```bash
curl http://localhost/up
# {"status":"up",...}
```

Desde tu navegador: `http://<tu-IP-o-dominio>/login` — deberías ver la
pantalla de login con los estilos cargando correctamente (si se ve sin
estilos, revisa la sección de Troubleshooting).

Recorrido mínimo antes de invitar al cliente: abrir turno de caja → hacer
una venta de prueba → cerrar caja → si `FE_ENABLED=true`, confirmar que la
factura se emite.

---

## 9. Dominio y HTTPS (recomendado antes del piloto real)

Sin esto, el piloto funciona por `http://IP`, pero sin cifrado — aceptable
para pruebas internas, no para que un cliente meta contraseñas reales.

1. Apunta un registro DNS tipo `A` de tu dominio (o subdominio, ej.
   `piloto.tudominio.com`) hacia la Elastic IP de la instancia.
2. Instala Certbot en la instancia (fuera de Docker, más simple):
   ```bash
   sudo apt install -y certbot python3-certbot-nginx
   ```
3. Como nginx corre dentro de un contenedor, la forma más simple es parar el
   contenedor de nginx, obtener el certificado con el modo standalone de
   Certbot, y luego montar los certificados dentro del contenedor:
   ```bash
   docker compose stop nginx
   sudo certbot certonly --standalone -d piloto.tudominio.com
   docker compose start nginx
   ```
4. Edita `docker/cloud/nginx.conf` para agregar un bloque `server` en el
   puerto 443 con `ssl_certificate` y `ssl_certificate_key` apuntando a
   `/etc/letsencrypt/live/piloto.tudominio.com/`, monta ese directorio como
   volumen de solo lectura en el servicio `nginx` del `docker-compose.yml`,
   y actualiza `APP_URL=https://piloto.tudominio.com` en `.env`.
5. Programa la renovación automática (los certificados vencen cada 90 días):
   ```bash
   echo "0 3 * * * docker compose -f /opt/taguara/docker/cloud/docker-compose.yml stop nginx && certbot renew --standalone && docker compose -f /opt/taguara/docker/cloud/docker-compose.yml start nginx" | sudo tee -a /etc/crontab
   ```

Si prefieres que te deje ese bloque de nginx y el cron ya escritos en vez
de la guía, dímelo cuando tengas el dominio listo.

---

## 10. Seguridad de la instancia

- **Security Group:** deja SSH (22) restringido a tu IP una vez termines la
  configuración inicial — no lo dejes abierto a `0.0.0.0/0`.
- **`.env`:** nunca lo subas a git (ya está en `.gitignore`). No lo compartas
  por chat/correo sin cifrar.
- **Usuario SSH:** evita crear usuarios adicionales con acceso root salvo
  que los necesites.
- **Actualizaciones del sistema operativo:**
  ```bash
  sudo apt update && sudo apt upgrade -y
  ```
  Ejecútalo periódicamente (o activa actualizaciones automáticas de Ubuntu).

---

## 11. Backups

La base de datos vive en el volumen Docker `cloud_db_data` — sobrevive a
`docker compose down` (sin `-v`), pero no te protege de un fallo del disco
de la instancia. Backup diario simple:

```bash
mkdir -p /opt/taguara/backups
cat <<'EOF' | sudo tee /opt/taguara/backup-db.sh
#!/bin/bash
cd /opt/taguara/docker/cloud
source .env
docker compose exec -T db pg_dump -U "$DB_USERNAME" "$DB_DATABASE" | gzip > /opt/taguara/backups/db-$(date +%Y%m%d-%H%M).sql.gz
find /opt/taguara/backups -name "*.sql.gz" -mtime +14 -delete
EOF
sudo chmod +x /opt/taguara/backup-db.sh
(crontab -l 2>/dev/null; echo "0 3 * * * /opt/taguara/backup-db.sh") | crontab -
```

Esto guarda 14 días de backups locales. Para el piloto basta; antes de ir a
producción real con más clientes, súbelos también a S3.

---

## 12. Actualizar la aplicación (redeploy)

Cuando haya cambios de código nuevos:

```bash
cd /opt/taguara
git pull
cd docker/cloud
docker compose build
docker compose up -d
```

El servicio `migrate` vuelve a correr automáticamente (es idempotente — las
migraciones ya aplicadas se saltan) antes de reiniciar `app`,
`queue-worker` y `scheduler`.

---

## Troubleshooting

**`docker compose ps` muestra `queue-worker` o `scheduler` reiniciando en
loop:** revisa que `migrate` haya terminado con código 0
(`docker compose ps migrate`). Si falló, revisa `docker compose logs migrate`.

**La página carga sin estilos ni JS:** el volumen `app_public` compartido
con nginx quedó desincronizado. Reconstruye y recrea `app`:
```bash
docker compose up -d --force-recreate --build app
```

**Error de conexión a la base de datos:** confirma que `DB_PASSWORD` es
idéntico en `.env` y que `db` está `healthy`:
```bash
docker compose ps db
```

**Ver logs de un servicio:**
```bash
docker compose logs -f app
docker compose logs -f queue-worker
```

**Entrar a una shell dentro del contenedor:**
```bash
docker compose exec app sh
```

---

## Checklist final antes de invitar al cliente al piloto

- [ ] `APP_DEBUG=false` en `.env`
- [ ] `APP_KEY` generada
- [ ] SMTP real configurado y probado (envía un correo de prueba)
- [ ] `FE_API_TOKEN` de producción, `FE_ENABLED=true`
- [ ] Dominio + HTTPS configurados (paso 9)
- [ ] Security group con SSH restringido a tu IP
- [ ] Backup diario funcionando (revisa que `/opt/taguara/backups/` tenga archivos)
- [ ] Super admin con contraseña propia (no la de `SuperAdminSeeder`)
- [ ] Farmacia del piloto creada con `taguara:setup-tenant` y datos reales
- [ ] Recorrido manual completo probado: abrir turno → vender → cerrar caja → FE
