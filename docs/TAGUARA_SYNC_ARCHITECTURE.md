# Taguara Sync — Arquitectura y Estado del Proyecto

**Última actualización:** Mayo 2026  
**Estado:** Fases 0–6 completadas. Fase 7 (offline/sync) en roadmap.

---

## Visión

Taguara Sync es un sistema web híbrido para farmacias pequeñas y medianas de la región Caribe colombiana. Opera como SaaS multi-tenant en la nube y puede funcionar con un servidor Linux local por farmacia cuando internet o energía sean inestables.

El sistema no gestiona pacientes, fórmulas médicas, EPS, IPS ni dispensación clínica. Los medicamentos controlados se marcan en el catálogo como advertencia operativa pero no bloquean la venta — el cajero puede venderlos con registro del número de receta.

---

## Stack

- **Backend:** Laravel 13, PHP 8.3
- **Frontend:** Inertia v3 + Svelte 5 (runes: `$state`, `$derived`, `$props`, `$effect`)
- **UI:** Bootstrap 5 + sistema de diseño propio (clases `.taguara-*`)
- **Autenticación:** Laravel Fortify v1 (headless, vistas Svelte)
- **Permisos:** Spatie Laravel Permission
- **Testing:** Pest v4 — 128 tests, 856 assertions
- **Impresión:** QZ Tray (WebSocket local, ESC/POS)
- **Facturación electrónica:** Nextpyme como intermediario DIAN

---

## Principios de código

- **Controllers delgados:** validan, autorizan y delegan a Actions. No contienen lógica de negocio.
- **Actions de un solo propósito:** `ProcessSale`, `ReceivePurchaseReceipt`, `ConfirmInventoryTransfer`, etc.
- **Form Requests** para toda validación. Las reglas de IDs de tenant siempre incluyen `->where('tenant_id', $tenantId)`.
- **Enums** para estados y tipos de dominio (`InventoryMovementType`, `InventoryTransferStatus`, etc.).
- **UUID público** en todos los modelos expuestos en rutas o sincronizables.
- **Transacciones** en ventas, compras, ajustes, traslados y facturación.
- **Append-only** en `inventory_movements` — nunca se editan, se compensan con movimientos inversos.
- **Idioma:** código en inglés, interfaz y mensajes en español.

---

## Multi-tenancy

Estrategia: **single database** con `tenant_id` en todas las tablas de negocio.

- Trait `BelongsToTenant` aplica un global scope automático por tenant en todos los modelos de negocio.
- Los 4 modelos sin el trait (`User`, `SyncCheckpoint`, `SyncConflictLog`, `TenantFeConfig`) tienen justificación explícita.
- El super_admin puede ver todos los tenants desde `/admin/tenants`.

---

## Modelo de datos principal

```
Tenant
  ├── Branch (sucursal)
  │     ├── CashRegister → CashSession → Sale → SaleItem, SalePayment
  │     └── InventoryLot → InventoryMovement
  ├── Supplier → PurchaseReceipt (branch_id) → PurchaseReceiptItem → InventoryLot
  │           → PurchaseOrder → PurchaseOrderItem
  │           → SupplierReturn → SupplierReturnItem
  │           → SupplierPayment
  ├── Product → ProductPresentation
  ├── PriceList → PriceListItem
  ├── Customer (price_list_id FK nullable)
  ├── BankAccount → BankAccountMovement
  ├── InventoryTransfer (from/to branch) → InventoryTransferItem → InventoryLot
  └── User (role via Spatie Permission)
```

---

## Inventario — diseño clave

El stock **no es un número editable**. Se deriva de movimientos append-only.

`inventory_lots` almacena el saldo actual (`current_quantity`) como cache. La fuente de verdad son los `inventory_movements`.

**Tipos de movimiento** (`InventoryMovementType`):

| Tipo | Descripción |
|------|-------------|
| `opening` | Carga inicial de inventario |
| `purchase` | Entrada por recepción de compra |
| `sale` | Salida por venta POS |
| `sale_return` | Entrada por devolución al cliente |
| `purchase_return` | Salida por devolución a proveedor |
| `adjustment_in` | Ajuste manual de entrada |
| `adjustment_out` | Ajuste manual de salida |
| `transfer_in` | Entrada por traslado entre sucursales |
| `transfer_out` | Salida por traslado entre sucursales |

**FEFO** (First Expired, First Out): el POS selecciona automáticamente el lote con vencimiento más próximo. Con multi-sucursal, el FEFO se aplica al stock de la sucursal de la caja activa.

**Unique constraint en `inventory_lots`:** `[tenant_id, branch_id, product_id, lot_number]` — un lote puede existir en múltiples sucursales simultáneamente (después de un traslado).

---

## Multi-sucursal

Decisión de diseño: **1 sucursal = 1 bodega**. La tabla `branches` actúa como ambas.

- Cada tenant tiene una sucursal "Principal" creada automáticamente al registrarse.
- Los lotes (`inventory_lots`) y movimientos (`inventory_movements`) tienen `branch_id`.
- Las cajas registradoras (`cash_registers`) tienen `branch_id`.
- Las recepciones de compra (`purchase_receipts`) tienen `branch_id`.
- Los traslados (`inventory_transfers`) mueven stock entre sucursales con pares `transfer_out`/`transfer_in`.

Ver detalles en [MODULO_SUCURSALES.md](MODULO_SUCURSALES.md).

---

## Permisos

22 permisos definidos en `RoleAndPermissionSeeder`. Los siguientes están reservados para roadmap (rol `warehouse`):

- `inventory.transfer` — traslados entre sucursales (**ya implementado**)
- `suppliers.view` / `suppliers.manage` — gestión independiente de proveedores
- `purchases.receive` — flujo de recepción separado de creación

---

## Facturación electrónica

- Proveedor: **Nextpyme**
- Endpoint base: `https://api.nextpyme.co/ubl2.1` (el prefijo `/ubl2.1` es obligatorio)
- Flujo: POS genera venta → job async envía a Nextpyme → respuesta con CUFE se guarda
- El recibo impreso incluye CUFE y QR nativo ESC/POS cuando hay FE
- Notas crédito soportadas vía `/sales/{sale}/credit-notes`
- Validación RADIAN en facturas de compra

---

## Impresión térmica

- Librería: **QZ Tray** (app de escritorio que expone WebSocket en `localhost:8182`)
- Seguridad: certificado RSA generado con `php artisan qz:keygen`; cada request se firma con SHA-512
- Configuración: una impresora por caja en `/settings/printer`
- Formatos soportados: ESC/POS para recibos de venta y cierres de caja (Z-report)
- Anchos: 58mm y 80mm

---

## Fases de implementación

### ✅ Fase 0 — Base arquitectónica
Stack instalado: Laravel 13, Inertia v3, Svelte 5, Bootstrap 5, Fortify, Spatie Permission.

### ✅ Fase 1 — Tenancy y seguridad
Multi-tenant con `BelongsToTenant`. Roles y permisos. Login/logout Fortify + Svelte.

### ✅ Fase 2 — Catálogo farmacéutico
Laboratorios, categorías, principios activos, productos, presentaciones, unidades. Importación CSV (hasta 500 productos). Medicamentos controlados con modal de receta en POS.

### ✅ Fase 3 — Proveedores y compras
Proveedores, recepciones directas, órdenes de compra (Draft→Enviada→Recibida), recepciones parciales, devoluciones a proveedor, cuentas por pagar, pagos con movimiento bancario automático. Validación RADIAN.

### ✅ Fase 4 — Inventario y lotes
Lotes con número y vencimiento. Movimientos append-only. Ajustes manuales. Stock inicial (importación CSV). Kardex por producto. Alertas operativas de vencimiento y bajo stock.

**Adición — Multi-sucursal (Mayo 2026):**
- Tabla `branches` con `is_main` flag.
- `branch_id` en lotes, movimientos, cajas y recepciones.
- `inventory_transfers` para traslados entre sucursales.
- FEFO filtrado por sucursal en el POS.
- Selector de sucursal en compras y stock inicial.

### ✅ Fase 5 — POS y caja
Apertura/cierre de caja por sucursal. Búsqueda FEFO. Múltiples pagos. Listas de precio por cliente. Descuentos por línea. Facturación electrónica integrada. Anulaciones. Impresión térmica automática.

### ✅ Fase 6 — Facturación electrónica
Configuración por tenant. Envío async con reintentos. CUFE y QR en recibos. Notas crédito. Contingencia manual. Resoluciones DIAN configurables.

**Adiciones completadas (Mayo 2026):**
- Importación CSV en listas de precio (`codigo_interno`, `precio_especial`)
- Configuración de impresora por caja (QZ Tray)
- Panel de sucursales en configuración

### 🔲 Fase 7 — Offline y sincronización
Ver [GUIA_SERVIDOR_LOCAL.md](GUIA_SERVIDOR_LOCAL.md).

Bases ya preparadas:
- UUID en todos los modelos sincronizables
- `server_id` en `inventory_movements`
- `InventoryMovement` con scope `pendingSync()`
- Movimientos append-only (sin conflictos de sync)
- FE asíncrona con queue y retry

Pendiente de implementar:
- `SyncAgent` job (Horizon, cada 5s si online)
- `ConflictResolver` (append para ventas/movimientos, LWW para datos maestros)
- `ConnectivityService` (ping 8.8.8.8 cada 3s)
- Tablas `sync_checkpoints` y `sync_conflicts_log`
- `APP_MODE=cloud|local` + `SERVER_ID` en .env
- Docker Compose para el servidor local + Watchtower

### ✅ Fase 8 — Reportes y operación
Ventas, compras, inventario valorizado, kardex, fiscal, rentabilidad, caja, estado de cuenta por proveedor. Exportación CSV. Auditorías.

---

## Permisos faltantes en rutas (reservados para roadmap)

Los permisos `inventory.transfer` (implementado en Mayo 2026), `suppliers.view`, `suppliers.manage` y `purchases.receive` están seeded en el rol `warehouse` para cuando se separen los flujos de proveedores y recepción de compras del módulo general de compras.

---

## Colombia — especificidades

- Moneda: COP, montos en enteros (centavos no usados)
- Medicamentos controlados: modal de receta en POS (número de receta, cédula, nombre del paciente)
- Registro sanitario: campos INVIMA + CUM + `health_registration`
- Facturación: Nextpyme como intermediario DIAN, UBL 2.1
- Impuesto: `tax_rate` en % (0–100), campo en productos y en líneas
- RADIAN: validación de facturas de compra con Nextpyme
