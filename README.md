# Taguara Sync

Sistema de gestión para farmacias híbridas — SaaS multi-tenant con capacidad de operación local sin internet.

---

## ¿Qué es?

Taguara Sync es una plataforma web para farmacias pequeñas y medianas de la región Caribe colombiana. Cubre el ciclo completo de operación: compras, inventario por lotes FEFO, punto de venta, cartera, facturación electrónica DIAN y reportes.

Está diseñado para funcionar en **modo cloud** (SaaS) y en **modo local** (servidor Linux en la farmacia), sincronizando automáticamente cuando hay internet.

---

## Stack

| Capa | Tecnología |
|------|-----------|
| Backend | PHP 8.3 + Laravel 13 |
| Frontend | Inertia v3 + Svelte 5 (runes) |
| UI | Bootstrap 5 + sistema de diseño `taguara-*` |
| Auth | Laravel Fortify v1 |
| Permisos | Spatie Laravel Permission |
| Testing | Pest v4 (128 tests) |
| Impresión | QZ Tray (ESC/POS térmica) |

---

## Módulos implementados

| Módulo | Rutas | Estado |
|--------|-------|--------|
| Autenticación | `/login`, `/register` | ✅ Fortify |
| Catálogo farmacéutico | `/products` | ✅ CRUD + importación CSV |
| Inventario por lotes | `/inventory`, `/inventory/kardex` | ✅ FEFO, lotes, kardex |
| Compras | `/purchases` | ✅ Recepción, RADIAN DIAN |
| Órdenes de compra | `/purchases/orders` | ✅ Draft → Recibida |
| Devoluciones a proveedor | `/purchases/returns` | ✅ Revierte stock |
| Cuentas por pagar | `/purchases/payables` | ✅ Estado de cuenta, pagos |
| POS | `/pos` | ✅ FEFO, múltiples pagos, FE, medicamentos controlados |
| Ventas | `/sales` | ✅ Historial, anulación, nota crédito |
| Clientes y cartera | `/customers`, `/sales/receivables` | ✅ Crédito, cobros |
| Facturación electrónica | `/fe/submissions`, `/settings/fe` | ✅ Nextpyme/DIAN async |
| Sucursales y traslados | `/settings/branches`, `/inventory/transfers` | ✅ Multi-local |
| Impresoras térmicas | `/settings/printer` | ✅ QZ Tray por caja |
| Listas de precio | `/settings/price-lists` | ✅ Precios especiales + importación CSV |
| Configuración | `/settings/*` | ✅ Categorías, labs, unidades, bancos, etc. |
| Reportes | `/reports/*` | ✅ Ventas, compras, inventario, fiscal |
| Equipo | `/team` | ✅ Usuarios y roles por tenant |
| Auditorías | `/audit` | ✅ Log de operaciones |

---

## Instalación local (desarrollo)

```bash
# 1. Clonar y configurar
cp .env.example .env
composer install
php artisan key:generate

# 2. Base de datos
php artisan migrate --seed

# 3. Frontend
npm install
npm run dev

# 4. Servidor de desarrollo (en otra terminal)
php artisan serve

# 5. (Opcional) Generar certificado para QZ Tray
php artisan qz:keygen
```

---

## Configuración inicial de una farmacia nueva

Al entrar por primera vez, el Dashboard muestra un **checklist de puesta en marcha**:

1. **Crear caja registradora** → `/settings/registers`
   - Asigna la caja a la sucursal correspondiente (la sucursal "Principal" se crea automáticamente).
2. **Agregar proveedor** → `/settings/suppliers`
3. **Cargar catálogo de productos** → `/products` (manual o importación CSV)
4. **Cargar inventario inicial** → `/inventory/opening`
   - Selecciona la sucursal donde entra el stock.
5. **Realizar la primera venta** → `/pos`
6. **Configurar facturación electrónica** → `/settings/fe`
7. **Configurar impresora térmica** → `/settings/printer`

---

## Roles de usuario

| Rol | Acceso |
|-----|--------|
| `owner` | Todo |
| `admin` | Todo excepto configurar FE |
| `cashier` | POS, ventas, apertura/cierre de caja |
| `warehouse` | Inventario, compras, proveedores |
| `accountant` | Ventas, reportes, facturación (lectura) |
| `super_admin` | Panel de farmacias (multi-tenant) |

---

## Multi-sucursal

Cada farmacia puede operar con múltiples sucursales. El inventario es independiente por sucursal:

- Las **compras** se reciben en una sucursal específica (selector en el formulario).
- El **POS** vende únicamente del stock de la sucursal de la caja activa (FEFO por sucursal).
- Los **traslados** mueven lotes entre sucursales con trazabilidad completa.
- Las **cajas registradoras** se asignan a una sucursal.

Ver: [docs/MODULO_SUCURSALES.md](docs/MODULO_SUCURSALES.md)

---

## Impresión térmica (QZ Tray)

El sistema imprime recibos y cierres de caja directamente en impresoras térmicas usando QZ Tray:

1. Instalar QZ Tray en el PC cajero desde [qz.io/download](https://qz.io/download).
2. Generar certificado en el servidor: `php artisan qz:keygen`.
3. Configurar impresora en `/settings/printer` (una por caja).

---

## Facturación electrónica (DIAN)

Integración con Nextpyme como proveedor tecnológico:

- Endpoint: `https://api.nextpyme.co/ubl2.1` (incluir siempre el prefijo `/ubl2.1`).
- Configuración en `/settings/fe`.
- Envío asíncrono con reintentos automáticos.
- Soporte para notas crédito y validación RADIAN de facturas de compra.

---

## Tests

```bash
php artisan test --compact
```

128 tests, cobertura de todos los flujos críticos de negocio.

---

## Documentación adicional

| Documento | Descripción |
|-----------|-------------|
| [docs/TAGUARA_SYNC_ARCHITECTURE.md](docs/TAGUARA_SYNC_ARCHITECTURE.md) | Arquitectura, fases y decisiones técnicas |
| [docs/MODULO_SUCURSALES.md](docs/MODULO_SUCURSALES.md) | Guía completa del módulo de sucursales y traslados |
| [docs/GUIA_SERVIDOR_LOCAL.md](docs/GUIA_SERVIDOR_LOCAL.md) | Instalación del servidor local offline (Fase 7) |
| [docs/planes-y-precios.md](docs/planes-y-precios.md) | Planes comerciales |
| [docs/sla-acuerdo-nivel-servicio.md](docs/sla-acuerdo-nivel-servicio.md) | SLA |
