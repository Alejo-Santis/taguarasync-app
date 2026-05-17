# FarmaSystem Colombia — Especificaciones para Claude Code

> **Propósito de este documento:** Guía completa para construir el proyecto desde un Laravel limpio.
> Leer en su totalidad antes de generar cualquier archivo.

---

## 1. Contexto del negocio

**FarmaSystem** es un sistema SaaS de gestión comercial para **farmacias independientes** pequeñas y medianas en Colombia.

### Alcance del software (importante)

- ✅ **Venta comercial de medicamentos** al público general
- ✅ **Control de inventario** con trazabilidad de lotes y vencimientos
- ✅ **Compras a proveedores** farmacéuticos autorizados
- ✅ **Facturación electrónica DIAN** vía API NextPyme Colombia SAS
- ✅ **Cumplimiento normativo** de comercialización (INVIMA, precios, registro sanitario)
- ✅ **Operación híbrida offline/online** para zonas con conectividad intermitente
- ❌ **NO es un dispensario** — no gestiona fórmulas médicas, no pertenece a ninguna EPS/IPS
- ❌ **NO gestiona pacientes** ni historiales clínicos
- ❌ **NO dispensa medicamentos de control especial** (Decreto 0780) — esos van a dispensarios autorizados
- ❌ **NO se integra con entidades de salud** (EPS, IPS, SGSSS)

### Usuario objetivo

Propietarios o administradores de droguerías/farmacias comerciales independientes en Colombia, con o sin regente farmacéutico.

---

## 2. Stack tecnológico

| Capa | Tecnología | Versión |
|---|---|---|
| Backend | Laravel | 12.x |
| Frontend routing | Inertia.js | 2.x |
| Frontend UI | Svelte | 5.x (con Runes) |
| CSS | Tailwind CSS | 4.x |
| Base de datos | PostgreSQL | 16 |
| Cache / Queue | Redis | 7 |
| Multi-tenancy | stancl/tenancy | 3.x |
| Roles y permisos | spatie/laravel-permission | 6.x |
| Queue worker | Laravel Horizon | 5.x |
| Búsqueda | Laravel Scout + TNTSearch | — |
| Almacenamiento | Laravel Filesystem (local/S3) | — |
| Testing | PestPHP | 3.x |

### Convenciones del proyecto

- **PHP**: estricto (`declare(strict_types=1)` en todos los archivos)
- **Arquitectura**: Action classes para lógica de negocio (no en Controllers)
- **API interna**: solo Inertia — no REST público en esta fase
- **Idioma del código**: inglés (variables, métodos, clases)
- **Idioma del usuario**: español (vistas, mensajes, validaciones)
- **Formato de fechas**: `Y-m-d H:i:s` en BD, `d/m/Y` en UI
- **Moneda**: COP (pesos colombianos), sin decimales en precios de venta

---

## 3. Arquitectura del proyecto

### 3.1 Multi-tenancy

Usar **stancl/tenancy** en modo **single-database con tenant scope** (no multi-schema en esta fase — simplifica el offline sync).

- Cada farmacia es un `Tenant`
- Todas las tablas de negocio tienen `tenant_id` (UUID)
- Global scope automático por tenant en todos los modelos de negocio
- El subdominio identifica al tenant: `farmaciaX.farmasystem.co`

### 3.2 Arquitectura offline-first (CRÍTICO)

El sistema debe operar con un **servidor local Linux** en cada farmacia que replica la BD y sirve la aplicación cuando no hay internet.

**Principio fundamental — Event Sourcing para inventario:**

El stock NUNCA se almacena como número directo. Siempre se calcula como la suma de los `movimientos_inventario`. Esto elimina conflictos de sincronización.

```
stock_actual = SUM(entradas) - SUM(salidas) WHERE producto_id AND lote_id AND bodega_id
```

**Estructura de sincronización:**

- Cada evento de negocio (venta, compra, ajuste) genera un registro en `sync_events` con UUID propio
- Un campo `server_id` identifica si el evento nació en `cloud` o `local`
- Un campo `synced_at` (nullable) indica si fue sincronizado
- Al reconectar, el agente sube eventos locales y baja eventos remotos — puro append
- Los conflictos en datos maestros se resuelven con Last-Write-Wins por timestamp

**Estados de conectividad:**

```
online       → escribe local + sync inmediato al cloud
degraded     → escribe local + encola para sync
offline      → escribe solo local, operaciones críticas bloqueadas
reconnecting → flush de cola pendiente en orden cronológico
```

### 3.3 Estructura de directorios

```
app/
├── Actions/               # Lógica de negocio (un archivo por operación)
│   ├── Ventas/
│   │   ├── CrearVenta.php
│   │   ├── AnularVenta.php
│   │   └── AplicarDescuento.php
│   ├── Inventario/
│   │   ├── RegistrarEntrada.php
│   │   ├── RegistrarSalida.php
│   │   └── AjustarStock.php
│   ├── Compras/
│   │   └── RecibirOrdenCompra.php
│   └── Facturacion/
│       ├── EmitirFacturaElectronica.php
│       └── EmitirNotaCredito.php
│
├── Http/Controllers/      # Solo reciben request, llaman Actions, retornan Inertia
│   ├── Ventas/
│   ├── Inventario/
│   ├── Compras/
│   ├── Facturacion/
│   └── Configuracion/
│
├── Models/                # Eloquent models con tenant scope global
│
├── Services/
│   ├── Connectivity/
│   │   └── ConnectivityService.php
│   ├── DIAN/
│   │   ├── NextPymeService.php
│   │   └── FacturaBuilder.php
│   └── Sync/
│       ├── SyncAgent.php
│       └── ConflictResolver.php
│
├── Jobs/
│   ├── EnviarFacturaDIAN.php
│   ├── SyncToCloud.php
│   └── FlushDIANQueue.php
│
└── Policies/              # Autorización por rol

resources/
└── js/
    ├── Pages/             # Páginas Svelte (Inertia)
    │   ├── Ventas/
    │   ├── Inventario/
    │   ├── Compras/
    │   ├── Facturacion/
    │   └── Configuracion/
    ├── Components/        # Componentes reutilizables
    │   ├── POS/
    │   ├── Inventario/
    │   └── UI/
    ├── Stores/            # Estado global Svelte 5
    │   ├── connectivity.svelte.js
    │   ├── cart.svelte.js
    │   └── session.svelte.js
    └── Layouts/
        ├── AppLayout.svelte
        └── POSLayout.svelte
```

---

## 4. Base de datos — Migraciones completas

Crear todas las migraciones en el orden indicado. Todas las tablas de negocio incluyen `tenant_id` (UUID, NOT NULL, indexed).

### 4.1 Tablas globales (no tienen tenant_id)

```sql
-- tenants (manejado por stancl/tenancy)
-- plans
-- users (tenant_id nullable para super-admin)
```

### 4.2 Tablas de catálogo maestro

**`laboratorios`**
```
id (bigint PK)
tenant_id (uuid, FK tenants)
nombre (string 200, NOT NULL)
nit (string 20, nullable)
pais (string 2, default 'CO')
contacto_nombre (string 150, nullable)
contacto_email (string 150, nullable)
contacto_telefono (string 20, nullable)
activo (boolean, default true)
timestamps
softDeletes
```

**`categorias_producto`**
```
id (bigint PK)
tenant_id (uuid, FK tenants)
nombre (string 100, NOT NULL)
descripcion (text, nullable)
activo (boolean, default true)
timestamps
```

**`principios_activos`**
```
id (bigint PK)
nombre_dci (string 200, NOT NULL)          -- Denominación Común Internacional
grupo_farmacologico (string 200, nullable)
clasificacion_atc (string 20, nullable)    -- Clasificación ATC
timestamps
```

**`productos`**
```
id (bigint PK)
tenant_id (uuid, FK tenants)
codigo_interno (string 50, unique por tenant)
codigo_barras (string 100, nullable, indexed)
nombre_comercial (string 250, NOT NULL)
nombre_generico (string 250, nullable)
cum (string 50, nullable, indexed)         -- Código Único de Medicamento INVIMA
registro_sanitario (string 100, nullable)  -- INVIMA
laboratorio_id (bigint FK)
principio_activo_id (bigint FK, nullable)
categoria_id (bigint FK)
forma_farmaceutica (string 100, nullable)  -- tableta, cápsula, jarabe, etc.
concentracion (string 100, nullable)       -- "500mg", "250mg/5ml"
presentacion (string 150, nullable)        -- "Caja x 10 tabletas"
unidad_minima_venta (string 50, nullable)  -- "unidad", "caja", "blíster"
precio_compra (bigint, default 0)          -- COP sin decimales
precio_venta (bigint, NOT NULL)            -- COP sin decimales
precio_regulado (bigint, nullable)         -- precio máximo MSPS si aplica
iva_porcentaje (decimal 5,2, default 0)   -- 0, 5, 19
requiere_registro_invima (boolean, default true)
controlado (boolean, default false)        -- Decreto 0780 — solo para bloqueo de venta
nivel_control (tinyint, nullable)          -- 1,2,3,4 — informativo
activo (boolean, default true)
imagen_path (string, nullable)
notas (text, nullable)
timestamps
softDeletes

INDEX: (tenant_id, codigo_barras)
INDEX: (tenant_id, cum)
INDEX: (tenant_id, nombre_comercial)
```

> **Nota sobre `controlado`:** El campo existe para mostrar alerta informativa al vendedor ("Este medicamento requiere formulación médica — venta solo en dispensarios autorizados") y NO procesar su venta en este sistema. El campo NO habilita un módulo de dispensación.

### 4.3 Proveedores

**`proveedores`**
```
id (bigint PK)
tenant_id (uuid, FK tenants)
razon_social (string 250, NOT NULL)
nit (string 30, NOT NULL)
digito_verificacion (tinyint, nullable)
tipo (enum: distribuidor, laboratorio, importador, otro)
representante_nombre (string 150, nullable)
email (string 150, nullable)
telefono (string 30, nullable)
ciudad (string 100, nullable)
departamento (string 100, nullable)
direccion (text, nullable)
condicion_pago_dias (tinyint, default 0)   -- días de crédito
activo (boolean, default true)
notas (text, nullable)
timestamps
softDeletes
```

### 4.4 Bodegas e Inventario

**`bodegas`**
```
id (bigint PK)
tenant_id (uuid, FK tenants)
nombre (string 150, NOT NULL)
tipo (enum: principal, secundaria, punto_venta)
descripcion (string 250, nullable)
activa (boolean, default true)
timestamps
```

**`lotes`**
```
id (bigint PK)
tenant_id (uuid, FK tenants)
producto_id (bigint FK)
proveedor_id (bigint FK, nullable)
numero_lote (string 100, NOT NULL)
fecha_fabricacion (date, nullable)
fecha_vencimiento (date, NOT NULL, indexed)
fecha_ingreso (date, NOT NULL)
cantidad_inicial (integer, NOT NULL)
registro_sanitario_snapshot (string 100, nullable)  -- snapshot al momento de ingreso
activo (boolean, default true)
notas (text, nullable)
timestamps

INDEX: (tenant_id, producto_id, fecha_vencimiento)
INDEX: (tenant_id, fecha_vencimiento)         -- para alertas de vencimiento
UNIQUE: (tenant_id, producto_id, numero_lote)
```

**`inventario`** (vista materializada — stock calculado, se actualiza con triggers o jobs)
```
id (bigint PK)
tenant_id (uuid, FK tenants)
producto_id (bigint FK)
bodega_id (bigint FK)
lote_id (bigint FK)
cantidad_disponible (integer, NOT NULL, default 0)
costo_promedio (bigint, default 0)
updated_at (timestamp)

UNIQUE: (tenant_id, producto_id, bodega_id, lote_id)
```

> **Implementación:** Esta tabla se recalcula a partir de `movimientos_inventario`. Crear un job `RecalcularStock` que se dispara después de cada movimiento. En modo offline, el stock local puede divergir — se reconcilia al sincronizar.

**`movimientos_inventario`** (EVENT STORE — nunca se borra, nunca se edita)
```
id (bigint PK)
uuid (uuid, NOT NULL, UNIQUE)              -- para deduplicación en sync
tenant_id (uuid, FK tenants)
tipo (enum: entrada_compra, entrada_ajuste, entrada_devolucion,
           salida_venta, salida_ajuste, salida_vencimiento,
           traslado_entrada, traslado_salida)
producto_id (bigint FK)
lote_id (bigint FK)
bodega_origen_id (bigint FK, nullable)
bodega_destino_id (bigint FK, nullable)
cantidad (integer, NOT NULL)               -- siempre positivo
costo_unitario (bigint, default 0)
documento_tipo (string 50, nullable)       -- 'venta', 'orden_compra', 'ajuste'
documento_id (bigint, nullable)            -- FK polimórfica
motivo (string 255, nullable)
user_id (bigint FK)
server_id (enum: cloud, local, default: cloud)  -- nodo de origen
synced_at (timestamp, nullable)            -- null = pendiente de sync
created_at (timestamp, NOT NULL, indexed)

INDEX: (tenant_id, producto_id, lote_id)
INDEX: (tenant_id, synced_at)              -- para sync agent
INDEX: (tenant_id, created_at)
```

### 4.5 Compras

**`ordenes_compra`**
```
id (bigint PK)
tenant_id (uuid, FK tenants)
numero (string 20, NOT NULL)               -- OC-2024-0001
proveedor_id (bigint FK)
user_id (bigint FK)                        -- quien creó
estado (enum: borrador, enviada, parcialmente_recibida, recibida, cancelada)
fecha_orden (date, NOT NULL)
fecha_entrega_esperada (date, nullable)
subtotal (bigint, default 0)
iva_total (bigint, default 0)
total (bigint, default 0)
notas (text, nullable)
recibida_at (timestamp, nullable)
timestamps
softDeletes

INDEX: (tenant_id, estado)
INDEX: (tenant_id, proveedor_id)
```

**`ordenes_compra_items`**
```
id (bigint PK)
orden_compra_id (bigint FK)
producto_id (bigint FK)
cantidad_pedida (integer, NOT NULL)
cantidad_recibida (integer, default 0)
precio_unitario (bigint, NOT NULL)
iva_porcentaje (decimal 5,2, default 0)
subtotal (bigint, NOT NULL)
notas (string 255, nullable)
timestamps
```

**`recepciones_compra`**
```
id (bigint PK)
tenant_id (uuid, FK tenants)
orden_compra_id (bigint FK, nullable)
proveedor_id (bigint FK)
user_id (bigint FK)
numero_factura_proveedor (string 100, nullable)
fecha_recepcion (date, NOT NULL)
estado (enum: completa, parcial, rechazada)
subtotal (bigint, default 0)
iva_total (bigint, default 0)
total (bigint, default 0)
notas (text, nullable)
timestamps
```

**`recepciones_compra_items`**
```
id (bigint PK)
recepcion_id (bigint FK)
producto_id (bigint FK)
lote_id (bigint FK)                        -- lote creado/usado en esta recepción
cantidad_recibida (integer, NOT NULL)
precio_unitario (bigint, NOT NULL)
iva_porcentaje (decimal 5,2, default 0)
subtotal (bigint, NOT NULL)
estado_calidad (enum: aceptado, rechazado)
notas_calidad (string 255, nullable)
timestamps
```

### 4.6 Ventas (POS)

**`cajas`**
```
id (bigint PK)
tenant_id (uuid, FK tenants)
nombre (string 100, NOT NULL)
bodega_id (bigint FK)
activa (boolean, default true)
impresora_nombre (string 100, nullable)
timestamps
```

**`sesiones_caja`**
```
id (bigint PK)
tenant_id (uuid, FK tenants)
caja_id (bigint FK)
user_id (bigint FK)
estado (enum: abierta, cerrada)
saldo_inicial (bigint, NOT NULL, default 0)
saldo_final_declarado (bigint, nullable)
saldo_sistema (bigint, nullable)           -- calculado al cerrar
diferencia (bigint, nullable)
abierta_at (timestamp, NOT NULL)
cerrada_at (timestamp, nullable)
notas_cierre (text, nullable)
timestamps
```

**`ventas`**
```
id (bigint PK)
uuid (uuid, NOT NULL, UNIQUE)
tenant_id (uuid, FK tenants)
numero (string 30, NOT NULL)               -- V-2024-000001
sesion_caja_id (bigint FK, nullable)
caja_id (bigint FK, nullable)
user_id (bigint FK)
cliente_nombre (string 200, nullable)      -- nombre libre, no hay tabla clientes
cliente_documento (string 30, nullable)    -- para factura electrónica
cliente_tipo_doc (enum: CC, NIT, CE, PA, nullable)
tipo (enum: venta, devolucion)
estado (enum: completada, anulada)
subtotal (bigint, NOT NULL)
descuento_total (bigint, default 0)
iva_total (bigint, default 0)
total (bigint, NOT NULL)
notas (string 255, nullable)
server_id (enum: cloud, local, default: cloud)
synced_at (timestamp, nullable)
anulada_at (timestamp, nullable)
motivo_anulacion (string 255, nullable)
anulada_by (bigint FK users, nullable)
timestamps

INDEX: (tenant_id, numero)
INDEX: (tenant_id, created_at)
INDEX: (tenant_id, synced_at)
INDEX: (tenant_id, sesion_caja_id)
```

**`ventas_items`**
```
id (bigint PK)
venta_id (bigint FK)
producto_id (bigint FK)
lote_id (bigint FK)
cantidad (integer, NOT NULL)
precio_unitario (bigint, NOT NULL)
descuento_porcentaje (decimal 5,2, default 0)
descuento_valor (bigint, default 0)
iva_porcentaje (decimal 5,2, default 0)
iva_valor (bigint, default 0)
subtotal (bigint, NOT NULL)
-- snapshot de datos críticos al momento de venta
producto_nombre_snapshot (string 250)
producto_cum_snapshot (string 50, nullable)
lote_numero_snapshot (string 100)
lote_vencimiento_snapshot (date)
timestamps
```

**`pagos`**
```
id (bigint PK)
venta_id (bigint FK)
metodo (enum: efectivo, tarjeta_debito, tarjeta_credito,
              transferencia, nequi, daviplata, otro)
monto (bigint, NOT NULL)
referencia (string 100, nullable)
timestamps
```

### 4.7 Facturación Electrónica DIAN

**`configuracion_facturacion`**
```
id (bigint PK)
tenant_id (uuid, FK tenants, UNIQUE)
nextpyme_api_key (string 500, nullable)    -- encriptado en BD
nextpyme_empresa_id (string 100, nullable)
prefijo_factura (string 10, nullable)
resolucion_dian_numero (string 50, nullable)
resolucion_dian_fecha (date, nullable)
resolucion_rango_desde (integer, nullable)
resolucion_rango_hasta (integer, nullable)
resolucion_fecha_vencimiento (date, nullable)
consecutivo_actual (integer, default 0)
ambiente (enum: pruebas, produccion, default: pruebas)
activa (boolean, default false)
timestamps
```

**`facturas_electronicas`**
```
id (bigint PK)
tenant_id (uuid, FK tenants)
venta_id (bigint FK, UNIQUE)
numero_factura (string 50, nullable)       -- prefijo + consecutivo
cufe (string 200, nullable)                -- hash DIAN
estado_dian (enum: pendiente, contingencia, enviada, aceptada, rechazada, error)
intentos_envio (tinyint, default 0)
proximo_intento_at (timestamp, nullable)
json_enviado (json, nullable)
xml_respuesta (text, nullable)
pdf_path (string, nullable)
qr_data (text, nullable)
fecha_emision (timestamp, nullable)
fecha_aceptacion_dian (timestamp, nullable)
error_detalle (text, nullable)
es_contingencia (boolean, default false)
numero_contingencia (string 50, nullable)
timestamps

INDEX: (tenant_id, estado_dian)
INDEX: (tenant_id, proximo_intento_at)
```

**`notas_credito`**
```
id (bigint PK)
tenant_id (uuid, FK tenants)
factura_electronica_id (bigint FK)
venta_devolucion_id (bigint FK)
cude (string 200, nullable)
motivo_codigo (string 10)                  -- códigos DIAN: 1=devolucion, 2=anulacion...
motivo_descripcion (string 255)
valor_total (bigint, NOT NULL)
estado_dian (enum: pendiente, enviada, aceptada, rechazada)
json_enviado (json, nullable)
xml_respuesta (text, nullable)
timestamps
```

### 4.8 Sincronización Offline

**`sync_checkpoints`**
```
id (bigint PK)
tenant_id (uuid, FK tenants)
server_id (enum: cloud, local)
ultima_sync_at (timestamp, nullable)
ultima_sync_exitosa_at (timestamp, nullable)
estado (enum: idle, syncing, error)
error_detalle (text, nullable)
timestamps
```

**`sync_conflicts_log`**
```
id (bigint PK)
tenant_id (uuid, FK tenants)
tabla (string 100)
registro_uuid (uuid)
estrategia_aplicada (string 100)           -- 'lww', 'cloud_wins', 'append'
datos_local (json)
datos_cloud (json)
datos_resultado (json)
resuelto_at (timestamp)
timestamps
```

### 4.9 Configuración y Auditoría

**`configuracion_farmacia`**
```
id (bigint PK)
tenant_id (uuid, FK tenants, UNIQUE)
razon_social (string 250, NOT NULL)
nit (string 20, NOT NULL)
digito_verificacion (tinyint, nullable)
regimen_tributario (enum: simplificado, comun)
direccion (text, NOT NULL)
municipio (string 100, NOT NULL)
departamento (string 100, NOT NULL)
codigo_postal (string 10, nullable)
telefono (string 30, nullable)
email (string 150, nullable)
logo_path (string, nullable)
moneda (string 3, default 'COP')
timezone (string 50, default 'America/Bogota')
alerta_vencimiento_dias (tinyint, default 30)   -- días antes para alertar
alerta_stock_minimo (boolean, default true)
stock_minimo_default (tinyint, default 5)
timestamps
```

**`auditorias`**
```
id (bigint PK)
tenant_id (uuid, FK tenants)
user_id (bigint FK, nullable)
accion (string 100, NOT NULL)              -- 'created', 'updated', 'deleted', 'login'
tabla_afectada (string 100, nullable)
registro_id (bigint, nullable)
datos_anteriores (json, nullable)
datos_nuevos (json, nullable)
ip_address (string 45, nullable)
user_agent (string 255, nullable)
created_at (timestamp, NOT NULL)

INDEX: (tenant_id, tabla_afectada, registro_id)
INDEX: (tenant_id, created_at)
```

---

## 5. Módulos y pantallas

### 5.1 Dashboard

**Ruta:** `/dashboard`

KPIs en tiempo real:
- Ventas del día (total COP y número de transacciones)
- Productos con stock bajo (< stock_minimo)
- Lotes próximos a vencer (< alerta_vencimiento_dias)
- Facturas DIAN pendientes de envío
- Estado de sincronización (si APP_MODE=local)

Gráficas:
- Ventas últimos 7 días (bar chart)
- Top 5 productos más vendidos (del mes)

### 5.2 POS — Punto de Venta

**Ruta:** `/pos`

Layout especial (POSLayout) — pantalla completa sin sidebar.

Flujo:
1. Búsqueda de producto por nombre, código de barras o CUM (input con autocompletar)
2. Agregar al carrito con cantidad
3. Aplicar descuento por ítem o global (porcentaje o valor fijo)
4. Seleccionar método de pago (uno o múltiples)
5. Confirmar venta → genera `Venta` + `ventas_items` + `pagos` + `movimientos_inventario`
6. Imprimir/mostrar recibo + dispatch `EmitirFacturaElectronica` job

Validaciones obligatorias en POS:
- Si el producto tiene `controlado = true`: mostrar modal de alerta "Este medicamento solo puede ser adquirido en dispensarios autorizados. No puede ser vendido en esta farmacia." y NO agregar al carrito
- Si el lote está vencido: bloquear, mostrar alerta
- Si no hay stock disponible: bloquear, mostrar stock actual
- FEFO automático: al agregar al carrito, asignar el lote con fecha de vencimiento más próxima que tenga stock

Selección de lote FEFO:
```
SELECT lote_id
FROM inventario i
JOIN lotes l ON l.id = i.lote_id
WHERE i.producto_id = ?
  AND i.bodega_id = ? (bodega de la caja actual)
  AND i.cantidad_disponible > 0
  AND l.fecha_vencimiento > NOW()
ORDER BY l.fecha_vencimiento ASC
LIMIT 1
```

Estado del modo (Svelte store `connectivity`):
- Si `offline`: mostrar banner rojo "Modo sin conexión — Las facturas DIAN se enviarán al reconectar"
- Si `offline` y producto `controlado`: doble bloqueo

### 5.3 Inventario

**Rutas:**
- `GET /inventario` — listado con stock actual por producto/bodega
- `GET /inventario/{producto}` — detalle con todos los lotes
- `GET /inventario/lotes` — listado de lotes con filtros por vencimiento
- `POST /inventario/ajuste` — ajuste manual de stock (requiere permiso `inventario.ajustar`)
- `GET /inventario/movimientos` — historial de movimientos

Filtros en listado:
- Por bodega
- Stock bajo (checkbox)
- Por vencer en N días (input)
- Por categoría
- Por laboratorio
- Búsqueda por nombre/código

Acciones:
- Ver detalle de lote (cantidad inicial, ingresada, disponible, movimientos)
- Ajuste positivo/negativo con motivo obligatorio
- Traslado entre bodegas

### 5.4 Productos

**Rutas:**
- `GET /productos` — catálogo completo
- `GET /productos/create` — crear producto
- `GET /productos/{producto}/edit` — editar
- `DELETE /productos/{producto}` — soft delete

Campos del formulario de producto:
- Código de barras (con botón para escanear si está disponible)
- Nombre comercial (requerido)
- Nombre genérico / DCI
- CUM (con botón "Verificar en INVIMA" — hace fetch a API pública INVIMA)
- Registro sanitario INVIMA
- Laboratorio (select + crear rápido)
- Principio activo (select + crear rápido)
- Categoría
- Forma farmacéutica, concentración, presentación
- Precio compra, precio venta, precio regulado
- IVA (0%, 5%, 19%)
- Switch "Requiere registro INVIMA"
- Switch "Medicamento controlado" (con tooltip explicativo)
- Si `controlado = true`: mostrar warning "Atención: este producto NO podrá venderse en el POS"

### 5.5 Compras

**Rutas:**
- `GET /compras/ordenes` — listado de OC
- `GET /compras/ordenes/create` — nueva OC
- `GET /compras/ordenes/{orden}` — detalle
- `POST /compras/ordenes/{orden}/recibir` — registrar recepción
- `GET /compras/proveedores` — CRUD proveedores

Flujo de recepción:
1. Seleccionar OC (o recepción directa sin OC)
2. Por cada ítem: confirmar cantidad recibida, ingresar número de lote y fecha de vencimiento
3. Opcionalmente: aceptar/rechazar por calidad
4. Al confirmar: genera `RecepcionCompra` + `lotes` (si es lote nuevo) + `movimientos_inventario` tipo `entrada_compra`

### 5.6 Proveedores

**Ruta:** `/compras/proveedores`

CRUD estándar. Campos:
- Razón social, NIT + dígito verificación
- Tipo: distribuidor, laboratorio, importador
- Contacto: representante, email, teléfono
- Ciudad, departamento, dirección
- Condición de pago (días de crédito)

### 5.7 Facturación Electrónica

**Rutas:**
- `GET /facturacion` — listado de facturas con estados DIAN
- `GET /facturacion/{factura}` — detalle + XML + PDF
- `POST /facturacion/{factura}/reenviar` — reintento manual
- `GET /facturacion/configuracion` — configurar NextPyme

Configuración NextPyme (por tenant):
- API Key NextPyme (guardada encriptada con `encrypt()`)
- ID Empresa NextPyme
- Prefijo factura + número resolución DIAN
- Rango (desde/hasta) y fecha vencimiento resolución
- Ambiente: pruebas / producción
- Botón "Probar conexión"

Estados de factura en UI:
- `pendiente` — gris — en cola
- `contingencia` — amarillo — offline, esperando conexión (con contador de horas)
- `enviada` — azul — enviada, esperando respuesta DIAN
- `aceptada` — verde — OK
- `rechazada` — rojo — error DIAN (mostrar detalle)
- `error` — rojo — error de sistema (mostrar detalle, permitir reintento)

### 5.8 Reportes

**Ruta:** `/reportes`

Reportes disponibles:
- **Ventas por período**: total, por producto, por usuario, por método de pago
- **Inventario actual**: stock por bodega, lotes, valor del inventario
- **Productos por vencer**: listado con días restantes
- **Movimientos de inventario**: por producto, por fecha, por tipo
- **Compras por período**: por proveedor, por producto
- **Cierre de caja**: resumen de sesión con cuadre

Formatos de exportación: PDF (usando `barryvdh/laravel-dompdf`) y Excel (usando `maatwebsite/excel`).

### 5.9 Configuración

**Ruta:** `/configuracion`

Secciones:
- **Farmacia**: datos básicos (razón social, NIT, dirección, logo)
- **Usuarios y roles**: CRUD usuarios del tenant
- **Cajas y bodegas**: CRUD cajas y bodegas
- **Alertas**: umbral de stock mínimo, días para alerta de vencimiento
- **Facturación DIAN**: ver sección 5.7
- **Sincronización**: estado del servidor local, última sync, logs (solo si APP_MODE=local)

---

## 6. Roles y permisos

Usar `spatie/laravel-permission`. Crear los siguientes roles con sus permisos:

### Roles

| Rol | Descripción |
|---|---|
| `super_admin` | Acceso total al sistema (global, sin tenant) |
| `admin` | Administrador de la farmacia — acceso total al tenant |
| `vendedor` | Solo puede usar el POS y ver su historial de ventas |
| `almacenista` | Gestiona inventario y compras, no puede vender |
| `contador` | Solo reportes y facturación, sin operaciones |

### Permisos por módulo

```
# Ventas
ventas.ver          ventas.crear        ventas.anular

# Inventario
inventario.ver      inventario.ajustar  inventario.trasladar

# Productos
productos.ver       productos.crear     productos.editar    productos.eliminar

# Compras
compras.ver         compras.crear       compras.recibir

# Proveedores
proveedores.ver     proveedores.gestionar

# Facturación
facturacion.ver     facturacion.configurar    facturacion.reenviar

# Reportes
reportes.ver        reportes.exportar

# Configuración
configuracion.ver   configuracion.gestionar

# Usuarios
usuarios.ver        usuarios.gestionar

# Cajas
cajas.abrir         cajas.cerrar        cajas.ver_todas
```

### Asignación de permisos por rol

```php
// admin: todos los permisos del tenant
// vendedor
'ventas.ver', 'ventas.crear',
'productos.ver', 'inventario.ver', 'cajas.abrir', 'cajas.cerrar'

// almacenista
'inventario.ver', 'inventario.ajustar', 'inventario.trasladar',
'productos.ver', 'productos.crear', 'productos.editar',
'compras.ver', 'compras.crear', 'compras.recibir',
'proveedores.ver', 'proveedores.gestionar', 'reportes.ver'

// contador
'ventas.ver', 'facturacion.ver', 'facturacion.reenviar',
'reportes.ver', 'reportes.exportar'
```

---

## 7. Services críticos

### 7.1 ConnectivityService

```php
// app/Services/Connectivity/ConnectivityService.php

class ConnectivityService
{
    // Verifica conectividad real (no solo LAN) con timeout de 2s
    // Guarda resultado en Redis con TTL de 3s
    // Usado por: middleware, jobs, frontend via endpoint /api/connectivity
    public static function isOnline(): bool

    // Retorna: 'online' | 'offline' | 'degraded'
    public static function getStatus(): string

    // Endpoint que devuelve estado actual para el Svelte store
    // GET /api/connectivity → { status: 'online', pending_sync: 12, pending_dian: 2 }
    public static function getStatusPayload(string $tenantId): array
}
```

### 7.2 NextPymeService

```php
// app/Services/DIAN/NextPymeService.php

class NextPymeService
{
    // Construye el JSON UBL 2.1 requerido por NextPyme/DIAN a partir de una Venta
    public function buildFacturaJson(Venta $venta): array

    // Envía la factura a NextPyme y actualiza FacturaElectronica
    // Lanza NextPymeException si falla
    public function emitir(FacturaElectronica $factura): void

    // Consulta el estado en DIAN via NextPyme
    public function consultarEstado(string $cufe): string

    // Genera nota crédito para devoluciones
    public function emitirNotaCredito(NotaCredito $nota): void

    // Verifica que las credenciales y la resolución sean válidas
    public function probarConexion(ConfiguracionFacturacion $config): bool
}
```

Campos obligatorios en el JSON DIAN que debe construir `FacturaBuilder`:
- NIT emisor + dígito de verificación
- Número y fecha resolución DIAN vigente
- Prefijo + consecutivo
- Fecha y hora de emisión (timezone Colombia)
- Datos del adquiriente (nombre + tipo doc + número doc)
- Municipio DIAN del emisor (código DANE)
- Líneas de detalle: descripción, cantidad, precio unitario, descuento, IVA
- Totales: subtotal, descuento, IVA (discriminado por tarifa), total
- Forma de pago (contado/crédito) y medio de pago
- CUFE (se calcula o lo retorna NextPyme)

### 7.3 SyncAgent (Job)

```php
// app/Jobs/SyncToCloud.php

// Frecuencia: cada 5 segundos via scheduler cuando hay internet
// Solo se ejecuta si APP_MODE=local

class SyncToCloud implements ShouldQueue
{
    // 1. Verifica conectividad — si no hay, retorna
    // 2. Obtiene movimientos_inventario sin synced_at (server_id='local') ordenados por created_at
    // 3. Los sube en batch al endpoint cloud POST /api/sync/ingest
    // 4. Marca synced_at = now() en los exitosos
    // 5. Baja delta del cloud (eventos desde última sync)
    // 6. Aplica ConflictResolver
    // 7. Actualiza sync_checkpoints
    // 8. Llama FlushDIANQueue si hay facturas pendientes
}
```

### 7.4 ConflictResolver

```php
// app/Services/Sync/ConflictResolver.php

class ConflictResolver
{
    // Aplica estrategia según tipo de entidad:
    // - movimientos_inventario → append (sin conflicto posible)
    // - ventas → append (sin conflicto posible)
    // - productos → Last-Write-Wins por updated_at, cloud gana en empate
    // - configuracion_farmacia → cloud siempre gana
    // Registra cada resolución en sync_conflicts_log
    public function applyRemoteEvents(array $events): void
}
```

---

## 8. Jobs y scheduling

```php
// app/Console/Kernel.php

// Cada 5 minutos — alertas de vencimiento próximo
$schedule->job(new AlertarVencimientos)->everyFiveMinutes();

// Cada hora — recalcular tabla inventario desde movimientos
$schedule->job(new RecalcularStock)->hourly();

// Cada minuto — reintentar facturas DIAN fallidas
$schedule->job(new ReintentarFacturasDIAN)->everyMinute();

// Solo en modo local — sync con cloud
if (config('app.mode') === 'local') {
    $schedule->job(new SyncToCloud)->everyFiveSeconds();
}
```

**Job `EnviarFacturaDIAN`:**
```php
public $tries = 5;
public $backoff = [60, 300, 900, 3600, 7200]; // 1m, 5m, 15m, 1h, 2h

// Límite DIAN: 48h para facturas de contingencia
// Si created_at > 47h: no reintenta, alerta al admin
```

---

## 9. Configuración de entornos

### Variables de entorno adicionales

```env
# Modo del servidor
APP_MODE=cloud                    # cloud | local

# Si APP_MODE=local
SERVER_ID=local                   # Identificador de este nodo en sync_log
CLOUD_SYNC_URL=https://api.farmasystem.co
CLOUD_SYNC_SECRET=               # HMAC secret para autenticar sync

# NextPyme (también se guarda en BD por tenant, esto es fallback)
NEXTPYME_BASE_URL=https://api.nextpyme.com.co

# Alertas
ALERT_VENCIMIENTO_DIAS_DEFAULT=30
ALERT_STOCK_MINIMO_DEFAULT=5

# DIAN contingencia límite
DIAN_CONTINGENCIA_HORAS_MAX=47
```

### Config `config/farmasystem.php`

Crear este archivo de configuración:
```php
return [
    'mode'          => env('APP_MODE', 'cloud'),  // cloud | local
    'server_id'     => env('SERVER_ID', 'cloud'),
    'cloud_sync_url'=> env('CLOUD_SYNC_URL'),
    'dian' => [
        'contingencia_horas_max' => env('DIAN_CONTINGENCIA_HORAS_MAX', 47),
        'reintentos_backoff'     => [60, 300, 900, 3600, 7200],
    ],
    'alertas' => [
        'vencimiento_dias'  => env('ALERT_VENCIMIENTO_DIAS_DEFAULT', 30),
        'stock_minimo'      => env('ALERT_STOCK_MINIMO_DEFAULT', 5),
    ],
];
```

---

## 10. Seeders y datos iniciales

Crear seeders para:

```php
// database/seeders/DatabaseSeeder.php

// 1. RolesAndPermissionsSeeder — crea roles y permisos según sección 6
// 2. TenantDemoSeeder — crea tenant 'demo' para desarrollo
// 3. ProductosDemoSeeder — 20 productos farmacéuticos de ejemplo (sin CUM real)
// 4. BodegaDefaultSeeder — una bodega principal y una caja por tenant demo
// 5. ConfiguracionDefaultSeeder — configuración base del tenant demo
```

---

## 11. Frontend Svelte 5 — convenciones

### Stores globales (Runes)

```javascript
// resources/js/Stores/connectivity.svelte.js
let status = $state('online')           // 'online' | 'offline' | 'degraded'
let pendingSync = $state(0)             // eventos sin sincronizar
let pendingDian = $state(0)             // facturas DIAN pendientes
let lastSync = $state(null)             // timestamp última sync exitosa
// Polling cada 5s a /api/connectivity
export { status, pendingSync, pendingDian, lastSync }

// resources/js/Stores/cart.svelte.js
let items = $state([])                  // items del carrito POS
let descuento = $state(0)              // descuento global
let metodoPago = $state('efectivo')
// Derivados
let subtotal = $derived(...)
let total = $derived(...)
export { items, descuento, metodoPago, subtotal, total }
```

### Componente de estado de conexión

Siempre visible en el header del AppLayout:

```svelte
<!-- ConnectivityBadge.svelte -->
<!-- Online: punto verde + "En línea" -->
<!-- Offline: punto rojo + "Sin conexión · N trans. pendientes" -->
<!-- Degraded: punto amarillo + "Conexión inestable" -->
```

### Convenciones de componentes Svelte 5

- Usar `$props()` para recibir props
- Usar `$state()` para estado local reactivo
- Usar `$derived()` para valores calculados
- Usar `$effect()` para efectos secundarios
- Los componentes de página reciben los props de Inertia directamente
- Naming: PascalCase para componentes, kebab-case para archivos

---

## 12. Testing

Usar **PestPHP**. Estructura mínima de tests a crear:

```
tests/
├── Feature/
│   ├── Ventas/
│   │   ├── CrearVentaTest.php         — flujo completo de venta
│   │   ├── AnularVentaTest.php        — anulación con stock recovery
│   │   └── BloqueoProdControlado.php  — no puede venderse producto controlado
│   ├── Inventario/
│   │   ├── FEFOTest.php               — asignación correcta de lotes
│   │   ├── StockCalculadoTest.php     — stock = suma de movimientos
│   │   └── AlertaVencimientoTest.php
│   ├── Compras/
│   │   └── RecepcionMercanciaTest.php
│   ├── Facturacion/
│   │   └── EmitirFacturaTest.php      — mock NextPyme, verifica estados
│   └── Sync/
│       ├── SyncAgentTest.php          — eventos pendientes se sincronizan
│       └── ConflictResolverTest.php   — Last-Write-Wins funciona
└── Unit/
    ├── FacturaBuilderTest.php         — JSON DIAN correcto
    └── ConnectivityServiceTest.php
```

Test crítico — FEFO y bloqueo de controlados:
```php
it('no permite vender medicamentos controlados', function () {
    $producto = Producto::factory()->controlado()->create();
    $response = $this->post('/pos/venta', ['items' => [['producto_id' => $producto->id]]]);
    $response->assertSessionHasErrors('items.0.producto_id');
});

it('asigna lote más próximo a vencer (FEFO)', function () {
    $lote1 = Lote::factory()->venceEn(60)->create();  // vence en 60 días
    $lote2 = Lote::factory()->venceEn(30)->create();  // vence en 30 días — este debe ir primero
    // ...venta → verifica que ventas_items.lote_id = $lote2->id
});
```

---

## 13. Orden de implementación sugerido

Seguir este orden para tener funcionalidad demostrable en cada etapa:

1. **Setup base**: instalar paquetes, configurar tenancy, crear config, migrations todas
2. **Seeders**: roles, permisos, tenant demo, datos de prueba
3. **Modelos Eloquent**: todos los modelos con relaciones, scopes y casts
4. **Autenticación**: login/logout por tenant, middleware de rol
5. **Productos**: CRUD completo con validaciones
6. **Proveedores**: CRUD
7. **Inventario + Lotes**: listado, movimientos, FEFO query
8. **Compras**: OC + recepción (genera lotes y movimientos)
9. **POS**: pantalla de venta con carrito, FEFO, bloqueo de controlados, pagos
10. **Dashboard**: KPIs básicos
11. **Facturación DIAN**: integración NextPyme + job con reintentos
12. **Reportes**: ventas, inventario, exportación
13. **Sincronización offline**: ConnectivityService + SyncAgent + ConflictResolver
14. **Tests**: cubrir flujos críticos
15. **Configuración**: pantallas de ajuste por tenant

---

## 14. Notas finales para Claude Code

- **No crear** módulo de dispensación, fórmulas médicas, pacientes ni integración con EPS/IPS
- **Sí crear** el campo `controlado` en productos con su lógica de bloqueo en POS
- El campo `controlado` solo sirve para mostrar la alerta y bloquear la venta — no habilita ningún flujo alternativo
- Todos los precios en **COP enteros** (bigint en BD, sin decimales) — `precio_venta` se muestra formateado con punto de miles
- El IVA farmacéutico en Colombia es **0%** para la mayoría de medicamentos, **5%** para algunos OTC — siempre preguntar/configurar por producto
- Para la **búsqueda de productos** en el POS: implementar con Laravel Scout + TNTSearch (funciona offline, sin Elastic)
- **Encriptar** siempre el `nextpyme_api_key` con `encrypt()` / `decrypt()` de Laravel — nunca en texto plano
- El **log de auditoría** debe activarse automáticamente para todas las acciones de ventas, anulaciones y ajustes de inventario usando un Observer o un Trait `Auditable`
- Usar `money_format` o un helper `formatCOP(int $valor): string` para mostrar precios en UI (ej: `$45.000`)
