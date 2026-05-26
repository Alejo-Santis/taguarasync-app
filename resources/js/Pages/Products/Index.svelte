<script>
    import { Link, router } from '@inertiajs/svelte';
    import { fly, fade } from 'svelte/transition';
    import {
        Boxes,
        Eye,
        Filter,
        Package,
        PackageCheck,
        Pencil,
        Plus,
        Printer,
        RotateCcw,
        Search,
        ShieldAlert,
        Tags,
        Upload,
        X,
    } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';

    let { auth, products, filters, stats, statuses } = $props();

    let form = $state({
        q: '',
        status: '',
        controlled: '',
        low_stock: '',
    });

    let selectedProduct = $state(null);

    const money = new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    });

    const hasProducts = $derived(products.data.length > 0);

    $effect(() => {
        form.q = filters.q ?? '';
        form.status = filters.status ?? '';
        form.controlled = filters.controlled ?? '';
        form.low_stock = filters.low_stock ?? '';
    });

    $effect(() => {
        document.body.style.overflow = selectedProduct ? 'hidden' : '';
        return () => { document.body.style.overflow = ''; };
    });

    const submit = (event) => {
        event.preventDefault();
        router.get('/products', form, { preserveState: true, replace: true });
    };

    const resetFilters = () => {
        form = { q: '', status: '', controlled: '', low_stock: '' };
        router.get('/products', {}, { preserveState: true, replace: true });
    };

    const openDetail = (product) => { selectedProduct = product; };
    const closeDetail = () => { selectedProduct = null; };

    const handleKeydown = (event) => {
        if (event.key === 'Escape' && selectedProduct) {
            closeDetail();
        }
    };

    const statusClass = (status) => {
        if (status === 'active') return 'text-bg-success';
        if (status === 'inactive') return 'text-bg-secondary';
        return 'text-bg-warning';
    };

    const formatMoney = (value) => {
        if (value === null || value === undefined) return '—';
        return money.format(value);
    };

    // Code 39 barcode: [barW, spaceW, barW, spaceW, barW, spaceW, barW, spaceW, barW], 1=narrow, 3=wide
    const C39 = {
        '0':[1,1,1,3,3,1,1,1,3],'1':[3,1,1,1,1,3,1,1,3],'2':[1,1,3,1,1,3,1,1,3],
        '3':[3,1,3,1,1,1,1,1,3],'4':[1,1,1,1,3,3,1,1,3],'5':[3,1,1,1,3,1,1,1,3],
        '6':[1,1,3,1,3,1,1,1,3],'7':[1,1,1,1,1,3,3,1,3],'8':[3,1,1,1,1,1,3,1,3],
        '9':[1,1,3,1,1,1,3,1,3],'A':[3,1,1,3,1,1,1,1,3],'B':[1,1,3,3,1,1,1,1,3],
        'C':[3,1,3,3,1,1,1,1,1],'D':[1,1,1,3,1,1,3,1,3],'E':[3,1,1,3,1,1,3,1,1],
        'F':[1,1,3,3,1,1,3,1,1],'G':[1,1,1,3,1,1,1,3,3],'H':[3,1,1,3,1,1,1,3,1],
        'I':[1,1,3,3,1,1,1,3,1],'J':[1,1,1,3,1,1,3,3,1],'K':[3,3,1,1,1,1,1,1,3],
        'L':[1,3,3,1,1,1,1,1,3],'M':[3,3,1,1,1,1,3,1,1],'N':[1,3,1,1,1,1,3,1,3],
        'O':[3,3,1,1,1,1,1,3,1],'P':[1,3,3,1,1,1,3,1,1],'Q':[1,3,1,1,3,1,1,1,3],
        'R':[3,3,1,1,3,1,1,1,1],'S':[1,3,3,1,3,1,1,1,1],'T':[1,3,1,1,1,1,1,3,3],
        'U':[3,1,1,1,1,1,3,3,1],'V':[1,1,3,1,1,1,3,3,1],'W':[3,1,3,1,1,1,3,1,1],
        'X':[1,1,1,1,3,1,3,3,1],'Y':[3,1,1,1,3,1,3,1,1],'Z':[1,1,3,1,3,1,3,1,1],
        '-':[1,1,1,1,3,1,1,3,3],'.':[3,1,1,1,3,1,1,3,1],' ':[3,1,1,3,3,1,1,1,1],
        '$':[1,3,1,3,1,3,1,1,1],'/':[1,3,1,3,1,1,1,3,1],'+':[1,3,1,1,1,3,1,3,1],
        '%':[1,1,1,3,1,3,1,3,1],'*':[1,3,1,1,3,1,1,1,3],
    };

    function buildCode39Svg(text, narrow = 2, barHeight = 60) {
        const src = ('*' + text.toUpperCase() + '*').split('');
        const gap = narrow;
        let rects = [], x = 0;
        for (let ci = 0; ci < src.length; ci++) {
            const pat = C39[src[ci]];
            if (!pat) continue;
            if (ci > 0) x += gap;
            for (let i = 0; i < pat.length; i++) {
                const w = pat[i] === 1 ? narrow : narrow * 3;
                if (i % 2 === 0) rects.push([x, w]);
                x += w;
            }
        }
        const svgRects = rects.map(([rx, rw]) => `<rect x="${rx}" y="0" width="${rw}" height="${barHeight}" fill="black"/>`).join('');
        return `<svg xmlns="http://www.w3.org/2000/svg" width="${x}" height="${barHeight}" viewBox="0 0 ${x} ${barHeight}">${svgRects}</svg>`;
    }

    function isCode39Compatible(text) {
        return text && text.toUpperCase().split('').every((c) => C39[c] !== undefined);
    }

    function printLabel(product) {
        const tenantName = auth?.tenant?.name ?? '';
        const barcode = product.barcode ?? product.internal_code ?? '';
        const hasBars = isCode39Compatible(barcode);
        const barcodeSvg = hasBars ? buildCode39Svg(barcode) : '';
        const price = product.sale_price != null ? money.format(product.sale_price) : '';
        const subtitle = [product.pharmaceutical_form, product.concentration].filter(Boolean).join(' · ');

        const html = `<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>Etiqueta</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, sans-serif; background: white; }
  .label { width: 230px; padding: 8px 10px; border: 1px solid #ccc; display: inline-block; }
  .tenant { font-size: 9px; color: #666; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }
  .name { font-size: 12px; font-weight: bold; line-height: 1.3; margin-bottom: 2px; }
  .sub { font-size: 9px; color: #555; margin-bottom: 6px; }
  .barcode-wrap { text-align: center; margin-bottom: 4px; }
  .barcode-num { font-size: 10px; font-family: monospace; text-align: center; letter-spacing: 1px; margin-bottom: 4px; }
  .price { font-size: 14px; font-weight: bold; text-align: right; }
  @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style></head><body>
<div class="label">
  ${tenantName ? `<div class="tenant">${tenantName}</div>` : ''}
  <div class="name">${product.commercial_name}</div>
  ${subtitle ? `<div class="sub">${subtitle}</div>` : ''}
  ${barcodeSvg ? `<div class="barcode-wrap">${barcodeSvg}</div>` : ''}
  ${barcode ? `<div class="barcode-num">${barcode}</div>` : ''}
  ${price ? `<div class="price">${price}</div>` : ''}
</div>
<script>window.onload=function(){window.print();}<\/script>
</body></html>`;

        const win = window.open('', '_blank', 'width=400,height=320');
        if (win) { win.document.write(html); win.document.close(); }
    }
</script>

<svelte:window onkeydown={handleKeydown} />

<AppLayout title="Productos" activeSection="products" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Catalogo farmaceutico</p>
                <h2 class="h3 mb-2">Productos y presentaciones</h2>
                <p class="text-secondary mb-0">Consulta por producto, codigo, barra o presentacion antes de conectar inventario y compras.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <Link class="btn btn-light border d-inline-flex align-items-center gap-2" href="/products/import">
                    <Upload size={18} />
                    Importar CSV
                </Link>
                <Link class="btn btn-taguara d-inline-flex align-items-center gap-2" href="/products/create">
                    <Plus size={18} />
                    Nuevo producto
                </Link>
            </div>
        </section>

        <section class="row g-3">
            <div class="col-12 col-md-6 col-xl-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-success"><PackageCheck size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Productos activos</p>
                        <p class="h3 mb-1">{stats.active}</p>
                        <p class="small text-secondary mb-0">Listos para vender</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-primary"><Boxes size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Total catalogo</p>
                        <p class="h3 mb-1">{stats.total}</p>
                        <p class="small text-secondary mb-0">En esta farmacia</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-warning"><ShieldAlert size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Controlados</p>
                        <p class="h3 mb-1">{stats.controlled}</p>
                        <p class="small text-secondary mb-0">Requieren seguimiento</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-info"><Tags size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Presentaciones</p>
                        <p class="h3 mb-1">{stats.presentations}</p>
                        <p class="small text-secondary mb-0">Unidad, blister, caja, frasco</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header align-items-start">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Busqueda</p>
                    <h3 class="h5 mb-0">Filtros del catalogo</h3>
                </div>
                <Filter class="text-secondary" size={22} />
            </div>

            <form class="taguara-filter-grid" onsubmit={submit}>
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Producto, codigo o barra</span>
                    <span class="taguara-filter-input">
                        <Search size={17} />
                        <input class="form-control" bind:value={form.q} type="search" placeholder="Acetaminofen, PRD-1001, 770..." />
                    </span>
                </label>

                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Estado</span>
                    <select class="form-select" bind:value={form.status}>
                        <option value="">Todos</option>
                        {#each statuses as status}
                            <option value={status.value}>{status.label}</option>
                        {/each}
                    </select>
                </label>

                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Control sanitario</span>
                    <select class="form-select" bind:value={form.controlled}>
                        <option value="">Todos</option>
                        <option value="yes">Controlados</option>
                        <option value="no">No controlados</option>
                    </select>
                </label>

                <label class="form-check d-flex align-items-end pb-1 mb-0 gap-2">
                    <input class="form-check-input mt-0" type="checkbox" bind:checked={form.low_stock} true-value="1" false-value="">
                    <span class="form-check-label small text-secondary">Solo bajo stock mínimo</span>
                </label>

                <div class="d-flex align-items-end gap-2">
                    <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="submit">
                        <Search size={17} />
                        Filtrar
                    </button>
                    <button class="btn btn-light border taguara-icon-button" type="button" aria-label="Limpiar filtros" onclick={resetFilters}>
                        <RotateCcw size={17} />
                    </button>
                </div>
            </form>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Resultados</p>
                    <h3 class="h5 mb-0">{products.total} productos encontrados</h3>
                </div>
                <span class="badge text-bg-light border text-secondary">
                    Pagina {products.current_page} de {products.last_page}
                </span>
            </div>

            {#if hasProducts}
                <div class="taguara-table-wrapper">
                    <table class="taguara-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Laboratorio</th>
                                <th>Forma · Concentracion</th>
                                <th>Estado</th>
                                <th class="text-end">Precio base</th>
                                <th class="text-center">Pres.</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each products.data as product}
                                <tr onclick={() => openDetail(product)}>
                                    <td>
                                        <div class="taguara-table-name">{product.commercial_name}</div>
                                        {#if product.generic_name}
                                            <div class="taguara-table-sub">{product.generic_name}</div>
                                        {/if}
                                    </td>
                                    <td class="text-secondary" style="font-size: 0.875rem">{product.laboratory ?? '—'}</td>
                                    <td class="text-secondary" style="font-size: 0.875rem">
                                        {[product.pharmaceutical_form, product.concentration].filter(Boolean).join(' · ') || '—'}
                                    </td>
                                    <td>
                                        <span class={`badge ${statusClass(product.status.value)}`}>{product.status.label}</span>
                                        {#if product.is_controlled}
                                            <span class="badge text-bg-warning ms-1">Ctrl.</span>
                                        {/if}
                                    </td>
                                    <td class="text-end fw-semibold" style="font-size: 0.875rem">{formatMoney(product.sale_price)}</td>
                                    <td class="text-center">
                                        <span class="badge text-bg-light border text-secondary">{product.presentations_count}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 justify-content-end">
                                            <button
                                                class="btn btn-sm btn-light border taguara-icon-button-sm"
                                                type="button"
                                                aria-label="Ver detalle"
                                                onclick={(e) => { e.stopPropagation(); openDetail(product); }}
                                            >
                                                <Eye size={15} />
                                            </button>
                                            <button
                                                class="btn btn-sm btn-light border taguara-icon-button-sm"
                                                type="button"
                                                aria-label="Imprimir etiqueta"
                                                onclick={(e) => { e.stopPropagation(); printLabel(product); }}
                                            >
                                                <Printer size={15} />
                                            </button>
                                            <Link
                                                class="btn btn-sm btn-light border taguara-icon-button-sm"
                                                href={`/products/${product.uuid}/edit`}
                                                aria-label="Editar producto"
                                                onclick={(e) => e.stopPropagation()}
                                            >
                                                <Pencil size={15} />
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>

                {#if products.links.length > 3}
                    <nav class="taguara-pagination" aria-label="Paginacion de productos">
                        {#each products.links as link}
                            {#if link.url}
                                <Link class={`btn btn-sm ${link.active ? 'btn-taguara' : 'btn-light border'}`} href={link.url}>
                                    {@html link.label}
                                </Link>
                            {:else}
                                <span class="btn btn-sm btn-light border disabled">{@html link.label}</span>
                            {/if}
                        {/each}
                    </nav>
                {/if}
            {:else}
                <div class="taguara-empty-state">
                    <Package size={34} />
                    <h4 class="h6 mb-1">No hay productos con estos filtros</h4>
                    <p class="text-secondary mb-0">Ajusta los filtros o agrega tu primer producto al catalogo.</p>
                </div>
            {/if}
        </section>
    </div>

    {#if selectedProduct}
        <div
            class="taguara-drawer-backdrop"
            transition:fade={{ duration: 150 }}
            onclick={closeDetail}
            role="presentation"
        ></div>

        <aside
            class="taguara-drawer"
            transition:fly={{ x: 480, duration: 220 }}
            aria-label="Detalle del producto"
        >
            <div class="taguara-drawer-header">
                <div class="d-flex align-items-start justify-content-between gap-2">
                    <div class="min-w-0">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <h2 class="h5 mb-0">{selectedProduct.commercial_name}</h2>
                            <span class={`badge ${statusClass(selectedProduct.status.value)}`}>{selectedProduct.status.label}</span>
                            {#if selectedProduct.is_controlled}
                                <span class="badge text-bg-warning">Controlado</span>
                            {/if}
                        </div>
                        {#if selectedProduct.generic_name}
                            <p class="text-secondary small mb-0">{selectedProduct.generic_name}</p>
                        {/if}
                    </div>
                    <button
                        class="btn btn-light border taguara-icon-button flex-shrink-0"
                        type="button"
                        onclick={closeDetail}
                        aria-label="Cerrar detalle"
                    >
                        <X size={17} />
                    </button>
                </div>
            </div>

            <div class="taguara-drawer-body">
                <div class="taguara-drawer-section">
                    <p class="text-uppercase small fw-semibold text-success mb-0">Identificacion</p>
                    <div class="taguara-drawer-grid">
                        <span class="taguara-drawer-label">Laboratorio</span>
                        <span>{selectedProduct.laboratory ?? '—'}</span>
                        <span class="taguara-drawer-label">Categoria</span>
                        <span>{selectedProduct.category ?? '—'}</span>
                        <span class="taguara-drawer-label">Principio activo</span>
                        <span>{selectedProduct.active_ingredient ?? '—'}</span>
                        <span class="taguara-drawer-label">Forma farmaceutica</span>
                        <span>{selectedProduct.pharmaceutical_form ?? '—'}</span>
                        <span class="taguara-drawer-label">Concentracion</span>
                        <span>{selectedProduct.concentration ?? '—'}</span>
                        <span class="taguara-drawer-label">Unidad minima</span>
                        <span>
                            {selectedProduct.minimum_unit
                                ? `${selectedProduct.minimum_unit.name} (${selectedProduct.minimum_unit.code})`
                                : '—'}
                        </span>
                        <span class="taguara-drawer-label">Codigo interno</span>
                        <span>{selectedProduct.internal_code ?? '—'}</span>
                        <span class="taguara-drawer-label">Codigo de barras</span>
                        <span>{selectedProduct.barcode ?? '—'}</span>
                    </div>
                </div>

                <div class="taguara-drawer-section">
                    <p class="text-uppercase small fw-semibold text-success mb-0">Regulatorio</p>
                    <div class="taguara-drawer-grid">
                        <span class="taguara-drawer-label">CUM</span>
                        <span>{selectedProduct.cum ?? '—'}</span>
                        <span class="taguara-drawer-label">Reg. sanitario</span>
                        <span>{selectedProduct.health_registration ?? '—'}</span>
                        <span class="taguara-drawer-label">INVIMA</span>
                        <span>{selectedProduct.requires_invima_registration ? 'Requerido' : 'No aplica'}</span>
                        {#if selectedProduct.is_controlled}
                            <span class="taguara-drawer-label">Nivel de control</span>
                            <span>{selectedProduct.control_level ?? '—'}</span>
                        {/if}
                    </div>
                </div>

                <div class="taguara-drawer-section">
                    <p class="text-uppercase small fw-semibold text-success mb-0">Precios</p>
                    <div class="taguara-drawer-grid">
                        <span class="taguara-drawer-label">Precio compra</span>
                        <span>{formatMoney(selectedProduct.purchase_price)}</span>
                        <span class="taguara-drawer-label">Precio venta base</span>
                        <span class="fw-semibold">{formatMoney(selectedProduct.sale_price)}</span>
                        <span class="taguara-drawer-label">Precio regulado</span>
                        <span>
                            {selectedProduct.regulated_price != null
                                ? formatMoney(selectedProduct.regulated_price)
                                : '—'}
                        </span>
                        <span class="taguara-drawer-label">IVA</span>
                        <span>{selectedProduct.tax_rate}%</span>
                    </div>
                </div>

                <div class="taguara-drawer-section">
                    <p class="text-uppercase small fw-semibold text-success mb-0">
                        Presentaciones ({selectedProduct.presentations_count})
                    </p>
                    <div class="taguara-presentation-drawer">
                        {#each selectedProduct.presentations as presentation}
                            <div class={`taguara-presentation-drawer-row${presentation.is_default ? ' default' : ''}${!presentation.is_active ? ' inactive' : ''}`}>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="fw-semibold small">{presentation.name}</span>
                                    {#if presentation.is_default}
                                        <span class="badge text-bg-success" style="font-size: 0.7rem">Principal</span>
                                    {/if}
                                    {#if !presentation.is_active}
                                        <span class="badge text-bg-secondary" style="font-size: 0.7rem">Inactiva</span>
                                    {/if}
                                </div>
                                <p class="text-secondary mb-0" style="font-size: 0.8125rem">
                                    {presentation.minimum_unit_quantity} {selectedProduct.minimum_unit?.code ?? 'und'}
                                    {#if presentation.unit} · {presentation.unit}{/if}
                                    {#if presentation.sale_price != null} · {formatMoney(presentation.sale_price)}{/if}
                                </p>
                            </div>
                        {/each}
                    </div>
                </div>

                {#if selectedProduct.notes}
                    <div class="taguara-drawer-section">
                        <p class="text-uppercase small fw-semibold text-success mb-0">Notas</p>
                        <p class="text-secondary small mb-0">{selectedProduct.notes}</p>
                    </div>
                {/if}
            </div>

            <div class="taguara-drawer-footer">
                <div class="d-flex gap-2">
                    <button
                        class="btn btn-light border d-inline-flex align-items-center gap-2"
                        type="button"
                        onclick={() => printLabel(selectedProduct)}
                    >
                        <Printer size={17} />
                        Etiqueta
                    </button>
                    <Link
                        class="btn btn-taguara flex-grow-1 d-inline-flex align-items-center justify-content-center gap-2"
                        href={`/products/${selectedProduct.uuid}/edit`}
                    >
                        <Pencil size={17} />
                        Editar producto
                    </Link>
                </div>
            </div>
        </aside>
    {/if}
</AppLayout>
