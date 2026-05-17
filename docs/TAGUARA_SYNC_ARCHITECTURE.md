# Taguara Sync App - Arquitectura y Fases

## Vision

Taguara Sync App es un sistema web hibrido para farmacias pequenas y medianas de la region Caribe. El producto debe funcionar como SaaS multi-tenant en la nube, pero quedar preparado desde el inicio para operar con un servidor local Linux por farmacia cuando internet o energia sean inestables.

El sistema no sera un dispensario clinico. No gestionara pacientes, formulas medicas, EPS, IPS ni dispensacion de medicamentos de control especial. Los productos marcados como controlados se bloquearan en POS y solo serviran para advertencia operativa.

## Stack Base

- Backend: Laravel 13, PHP 8.3, PostgreSQL.
- Frontend: Inertia.js con Svelte 5.
- UI: Bootstrap 5 con tema claro, moderno y operativo.
- Autenticacion: Laravel Fortify con vistas Svelte.
- Permisos: Spatie Laravel Permission.
- Testing: Pest 4.
- Facturacion electronica: integracion por proveedor tecnologico, iniciando con estructura y jobs.
- Offline: arquitectura preparada desde fase temprana; sincronizacion completa en fase posterior.

## Principios De Codigo

- Controllers delgados: reciben request, autorizan si aplica, llaman Actions/Services y retornan Inertia o redirect.
- Validacion con Form Requests; no validacion inline.
- Lógica de negocio en Actions de un solo proposito.
- Services para integraciones y procesos compartidos.
- Enums para estados, tipos y valores de dominio.
- UUID/ULID publico en modelos expuestos o sincronizables.
- IDs internos numericos permitidos para relaciones locales y rendimiento, pero no se exponen en rutas publicas cuando pueda evitarse.
- Transacciones de base de datos en ventas, compras, recepciones, ajustes de inventario y facturacion.
- Jobs y eventos que dependan de datos recien creados deben ejecutarse despues del commit.
- Idioma del codigo en ingles; textos de interfaz, validaciones y mensajes en español.

## Multi-Tenancy

La aplicacion sera multi-tenant desde el inicio. Cada farmacia es un tenant y todas las tablas de negocio deben incluir `tenant_id`.

Estrategia inicial recomendada:

- Single database con `tenant_id` en tablas de negocio.
- Scope global o trait local para aislar datos por tenant.
- Usuarios asociados al tenant activo, permitiendo un super-admin global.
- Configuracion fiscal, cajas, bodegas, permisos, productos, inventario y facturacion por tenant.

Esta estrategia simplifica el futuro offline porque el servidor local de una farmacia replica solo los datos de su tenant.

## Productos Y Presentaciones

El producto representa el medicamento o articulo comercial base. La presentacion representa como se compra o vende.

Ejemplos:

- Acetaminofen 500mg tableta.
  - Unidad: 1 unidad minima.
  - Blister x 10: 10 unidades minimas.
  - Caja x 100: 100 unidades minimas.
- Ibuprofeno suspension 100mg/5ml.
  - Frasco 60ml: 1 unidad minima comercial.
  - Frasco 120ml: 1 unidad minima comercial.

Modelo conceptual:

- `products`: datos farmaceuticos y comerciales base.
- `product_presentations`: presentaciones de compra/venta, factor de conversion, codigo de barras opcional, precio de venta opcional.
- `product_units`: catalogo controlado para unidad, tableta, capsula, frasco, tubo, ampolla, sobre, blister, caja.

Regla importante: el inventario se maneja en unidad minima. Las compras y ventas pueden capturarse por presentacion, pero se convierten a unidades minimas al generar movimientos.

## Inventario

El stock no debe tratarse como un numero editable. El saldo se deriva de movimientos de inventario.

Movimientos principales:

- Entrada por compra.
- Entrada por ajuste.
- Entrada por devolucion.
- Salida por venta.
- Salida por ajuste.
- Salida por vencimiento.
- Traslado entrada/salida entre bodegas.

Cada movimiento debe ser inmutable para facilitar auditoria y sincronizacion offline. Si se necesita corregir, se crea un movimiento compensatorio.

La tabla de inventario puede existir como cache o vista materializada para consultas rapidas, pero su fuente de verdad son los movimientos.

## Compras, Remisiones Y Facturas De Proveedor

Las farmacias no siempre crean ordenes de compra formales. El sistema debe permitir recepcion directa.

Flujos soportados:

- Recepcion directa sin orden de compra.
- Orden de compra y recepcion total.
- Orden de compra y recepcion parcial.
- Recepcion con adjunto PDF, imagen o archivo escaneado de factura/remision.

Modelo conceptual:

- `suppliers`: proveedores.
- `purchase_orders`: ordenes opcionales.
- `purchase_order_items`: items solicitados.
- `supplier_documents`: factura, remision, PDF, imagen o soporte recibido.
- `purchase_receipts`: recepcion real de mercancia.
- `purchase_receipt_items`: lineas recibidas con lote, vencimiento, cantidad, costo y presentacion.
- `supplier_product_aliases`: nombres/codigos del proveedor mapeados al producto interno.

La confirmacion de una recepcion debe crear lotes cuando aplique y movimientos de inventario tipo entrada por compra.

Para el MVP, la digitalizacion sera manual con archivo adjunto. OCR o lectura asistida de PDFs queda para una fase posterior.

## Facturacion Electronica

La facturacion electronica se incluye desde la primera fase como estructura tecnica, pero las pruebas en caliente quedan para una fase posterior.

Primera fase:

- Configuracion por tenant del proveedor tecnologico.
- Modelo de factura electronica y estados.
- Payload enviado y respuesta recibida.
- Jobs de envio y reintento.
- Pantalla de consulta de estado.

Segunda fase:

- Pruebas reales con proveedor tecnologico.
- Manejo fino de errores.
- Contingencia offline.
- Reenvios manuales.
- Validacion de resoluciones, prefijos y consecutivos.

## Offline Hibrido

El offline es una ventaja comercial central, pero se implementara por capas.

Desde el inicio se preparan las bases:

- UUID/ULID en entidades sincronizables.
- `server_id` para identificar origen cloud/local.
- Movimientos de inventario append-only.
- Ventas, compras y ajustes como operaciones auditables.
- Estados de sincronizacion.
- Jobs despues de commit.

Arquitectura objetivo:

- Cloud SaaS multi-tenant.
- Mini server Linux local por farmacia.
- PostgreSQL local con datos del tenant.
- Misma aplicacion Laravel/Inertia/Svelte corriendo local.
- Agente de sincronizacion bidireccional.
- Operacion POS por LAN cuando no haya internet.
- Reconciliacion cuando vuelva la conectividad.

La fase inicial no debe bloquearse por construir el sync completo. Se construira el core de negocio de forma compatible con el futuro servidor local.

## Fases De Implementacion

### Fase 0 - Base Arquitectonica

- Confirmar dependencias compatibles con Laravel 13.
- Instalar Inertia, Svelte 5 y Bootstrap 5.
- Instalar Fortify y Spatie Permission.
- Definir convenciones de carpetas para Actions, Services, Enums, Requests y Policies.
- Preparar layout base de aplicacion.

### Fase 1 - Tenancy Y Seguridad

- Crear estructura multi-tenant.
- Asociar usuarios a tenant.
- Configurar roles y permisos base.
- Login/logout con Fortify e Inertia/Svelte.
- Middleware de tenant activo y permisos.

### Fase 2 - Catalogo Farmaceutico

- Laboratorios.
- Categorias.
- Principios activos.
- Productos.
- Presentaciones.
- Unidades.
- Bloqueo informativo para productos controlados.

### Fase 3 - Proveedores Y Compras

- Proveedores.
- Documentos de proveedor.
- Recepcion directa con adjunto.
- Ordenes de compra opcionales.
- Recepciones parciales.
- Alias de productos por proveedor.

### Fase 4 - Inventario Y Lotes

- Bodegas.
- Lotes con vencimiento.
- Movimientos de inventario.
- Stock calculado/cacheado.
- Ajustes de inventario.
- Alertas de vencimiento y bajo stock.

### Fase 5 - POS Y Caja

- Apertura/cierre de caja.
- Busqueda rapida de productos.
- Venta por presentacion.
- Conversion a unidad minima.
- FEFO para seleccionar lote.
- Bloqueo de vencidos y controlados.
- Pagos y anulaciones.

### Fase 6 - Facturacion Electronica

- Configuracion del proveedor tecnologico.
- Generacion de payload.
- Jobs de envio.
- Estados y reintentos.
- Consulta de facturas.
- Preparacion para contingencia.

### Fase 7 - Offline Y Sync

- Modo cloud/local.
- Servidor local Linux.
- Cola local.
- Sync events.
- Agente de sincronizacion.
- Resolucion de conflictos.
- Envio diferido de facturas.

### Fase 8 - Reportes Y Operacion

- Ventas por periodo.
- Compras por proveedor.
- Inventario valorizado.
- Kardex por producto/lote.
- Facturas pendientes.
- Auditoria operativa.

## Primera Decisión Operativa

La siguiente tarea tecnica debe ser preparar la Fase 0: instalar y configurar el stack frontend y paquetes base. Antes de hacerlo se debe aprobar el cambio de dependencias del proyecto.
