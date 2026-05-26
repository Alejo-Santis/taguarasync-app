<script>
    import { Link, router } from '@inertiajs/svelte';
    import { fade, fly } from 'svelte/transition';
    import {
        Boxes,
        CircleDollarSign,
        Eye,
        FileText,
        Filter,
        Plus,
        ReceiptText,
        RotateCcw,
        Search,
        ShieldAlert,
        ShieldCheck,
        Upload,
        X,
    } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';

    let { auth, receipts, filters, stats, statuses, radianStatuses } = $props();

    let form = $state({ q: '', status: '', radian_status: '' });
    let selectedReceipt = $state(null);

    const money = new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    });

    const hasReceipts = $derived(receipts.data.length > 0);

    $effect(() => {
        form.q = filters.q ?? '';
        form.status = filters.status ?? '';
        form.radian_status = filters.radian_status ?? '';
    });

    $effect(() => {
        document.body.style.overflow = selectedReceipt ? 'hidden' : '';
        return () => { document.body.style.overflow = ''; };
    });

    const submit = (event) => {
        event.preventDefault();
        router.get('/purchases', form, { preserveState: true, replace: true });
    };

    const resetFilters = () => {
        form = { q: '', status: '', radian_status: '' };
        router.get('/purchases', {}, { preserveState: true, replace: true });
    };

    const openDetail = (receipt) => { selectedReceipt = receipt; };
    const closeDetail = () => { selectedReceipt = null; };

    const handleKeydown = (event) => {
        if (event.key === 'Escape' && selectedReceipt) {
            closeDetail();
        }
    };

    const statusClass = (status) => {
        if (status === 'received') return 'text-bg-success';
        if (status === 'voided') return 'text-bg-danger';
        return 'text-bg-secondary';
    };

    const radianClass = (status) => {
        if (status === 'validated') return 'text-bg-success';
        if (status === 'rejected' || status === 'error') return 'text-bg-danger';
        return 'text-bg-warning text-dark';
    };

    const validateRadian = (receipt) => {
        router.post(`/purchases/${receipt.uuid}/validate-radian`, {}, { preserveScroll: true });
    };
</script>

<svelte:window onkeydown={handleKeydown} />

<AppLayout title="Compras" activeSection="purchases" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Recepciones</p>
                <h2 class="h3 mb-2">Compras recibidas</h2>
                <p class="text-secondary mb-0">Control de remisiones, facturas de compra y entradas automáticas a inventario.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <Link class="btn btn-light border d-inline-flex align-items-center gap-2" href="/purchases/orders">
                    <Boxes size={17} />
                    Órdenes
                </Link>
                <Link class="btn btn-light border d-inline-flex align-items-center gap-2" href="/purchases/returns">
                    <RotateCcw size={17} />
                    Devoluciones
                </Link>
                <Link class="btn btn-light border d-inline-flex align-items-center gap-2" href="/purchases/payables">
                    <CircleDollarSign size={17} />
                    Cuentas por pagar
                </Link>
                <Link class="btn btn-taguara d-inline-flex align-items-center gap-2" href="/purchases/create">
                    <Plus size={18} />
                    Nueva compra
                </Link>
            </div>
        </section>

        <section class="row g-3">
            <div class="col-12 col-md-6 col-xl-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-primary"><ReceiptText size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Recepciones</p>
                        <p class="h3 mb-1">{stats.receipts}</p>
                        <p class="small text-secondary mb-0">Documentos registrados</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-success"><CircleDollarSign size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Total recibido</p>
                        <p class="h3 mb-1">{money.format(stats.total)}</p>
                        <p class="small text-secondary mb-0">Costo de compra</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-info"><Boxes size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Lineas</p>
                        <p class="h3 mb-1">{stats.items}</p>
                        <p class="small text-secondary mb-0">Productos recibidos</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-warning"><ShieldAlert size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Pendientes RADIAN</p>
                        <p class="h3 mb-1">{stats.radian_pending}</p>
                        <p class="small text-secondary mb-0">Por validar con DIAN/NextPyme</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header align-items-start">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Busqueda</p>
                    <h3 class="h5 mb-0">Filtros de compras</h3>
                </div>
                <Filter class="text-secondary" size={22} />
            </div>

            <form class="taguara-filter-grid" onsubmit={submit}>
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Documento o proveedor</span>
                    <span class="taguara-filter-input">
                        <Search size={17} />
                        <input class="form-control" bind:value={form.q} type="search" placeholder="REM-1001, proveedor..." />
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
                    <span class="small fw-semibold text-secondary">RADIAN</span>
                    <select class="form-select" bind:value={form.radian_status}>
                        <option value="">Todos</option>
                        {#each radianStatuses as status}
                            <option value={status.value}>{status.label}</option>
                        {/each}
                    </select>
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
                    <h3 class="h5 mb-0">{receipts.total} documentos encontrados</h3>
                </div>
                <span class="badge text-bg-light border text-secondary">
                    Pagina {receipts.current_page} de {receipts.last_page}
                </span>
            </div>

            {#if hasReceipts}
                <div class="taguara-table-wrapper">
                    <table class="taguara-table">
                        <thead>
                            <tr>
                                <th>Documento</th>
                                <th>Proveedor</th>
                                <th>Recibido</th>
                                <th class="text-center">Lineas</th>
                                <th class="text-end">Total</th>
                                <th>Estado</th>
                                <th>RADIAN</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each receipts.data as receipt}
                                <tr onclick={() => openDetail(receipt)}>
                                    <td>
                                        <div class="taguara-table-name">{receipt.document_number}</div>
                                        <div class="taguara-table-sub">{receipt.document_date ?? 'Sin fecha documento'}</div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{receipt.supplier.name ?? '—'}</div>
                                        <div class="taguara-table-sub">{receipt.supplier.nit ?? 'Sin NIT'}</div>
                                    </td>
                                    <td class="text-secondary">{receipt.received_at ?? '—'}</td>
                                    <td class="text-center">
                                        <span class="badge text-bg-light border text-secondary">{receipt.items_count}</span>
                                    </td>
                                    <td class="text-end fw-semibold">{money.format(receipt.total)}</td>
                                    <td><span class={`badge ${statusClass(receipt.status.value)}`}>{receipt.status.label}</span></td>
                                    <td><span class={`badge ${radianClass(receipt.radian_status.value)}`}>{receipt.radian_status.label}</span></td>
                                    <td>
                                        <div class="d-flex justify-content-end">
                                            <button
                                                class="btn btn-sm btn-light border taguara-icon-button-sm"
                                                type="button"
                                                aria-label="Ver detalle"
                                                onclick={(event) => { event.stopPropagation(); openDetail(receipt); }}
                                            >
                                                <Eye size={15} />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>

                {#if receipts.links.length > 3}
                    <nav class="taguara-pagination" aria-label="Paginacion de compras">
                        {#each receipts.links as link}
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
                    <FileText size={34} />
                    <h4 class="h6 mb-1">No hay compras con estos filtros</h4>
                    <p class="text-secondary mb-0">Al recibir remisiones o facturas de compra, apareceran aqui.</p>
                </div>
            {/if}
        </section>
    </div>

    {#if selectedReceipt}
        <div class="taguara-drawer-backdrop" transition:fade={{ duration: 150 }} onclick={closeDetail} role="presentation"></div>
        <aside class="taguara-drawer" transition:fly={{ x: 480, duration: 220 }} aria-label="Detalle de compra">
            <div class="taguara-drawer-header">
                <div class="d-flex align-items-start justify-content-between gap-2">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h2 class="h5 mb-0">{selectedReceipt.document_number}</h2>
                            <span class={`badge ${statusClass(selectedReceipt.status.value)}`}>{selectedReceipt.status.label}</span>
                            <span class={`badge ${radianClass(selectedReceipt.radian_status.value)}`}>{selectedReceipt.radian_status.label}</span>
                        </div>
                        <p class="text-secondary small mb-0">{selectedReceipt.supplier.name ?? 'Proveedor no definido'}</p>
                    </div>
                    <button class="btn btn-light border taguara-icon-button" type="button" onclick={closeDetail} aria-label="Cerrar detalle">
                        <X size={17} />
                    </button>
                </div>
            </div>

            <div class="taguara-drawer-body">
                <div class="taguara-drawer-section">
                    <p class="text-uppercase small fw-semibold text-success mb-0">Documento</p>
                    <div class="taguara-drawer-grid">
                        <span class="taguara-drawer-label">Proveedor</span>
                        <span>{selectedReceipt.supplier.name ?? '—'}</span>
                        <span class="taguara-drawer-label">NIT</span>
                        <span>{selectedReceipt.supplier.nit ?? '—'}</span>
                        <span class="taguara-drawer-label">Fecha documento</span>
                        <span>{selectedReceipt.document_date ?? '—'}</span>
                        <span class="taguara-drawer-label">Recibido</span>
                        <span>{selectedReceipt.received_at ?? '—'}</span>
                        <span class="taguara-drawer-label">Usuario</span>
                        <span>{selectedReceipt.user ?? '—'}</span>
                    </div>
                    {#if selectedReceipt.has_source_file}
                        <a
                            class="btn btn-sm btn-light border d-inline-flex align-items-center justify-content-center gap-2 mt-3"
                            href={selectedReceipt.source_file_url}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <Upload size={14} />
                            Ver soporte
                        </a>
                    {/if}
                </div>

                <div class="taguara-drawer-section">
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">DIAN / RADIAN</p>
                            <div class="d-flex align-items-center gap-2">
                                {#if selectedReceipt.radian_status.value === 'validated'}
                                    <ShieldCheck size={17} class="text-success" />
                                {:else}
                                    <ShieldAlert size={17} class="text-warning" />
                                {/if}
                                <span class={`badge ${radianClass(selectedReceipt.radian_status.value)}`}>
                                    {selectedReceipt.radian_status.label}
                                </span>
                            </div>
                            {#if selectedReceipt.radian_checked_at}
                                <p class="text-secondary small mt-2 mb-0">Ultima validacion: {selectedReceipt.radian_checked_at}</p>
                            {/if}
                            {#if selectedReceipt.radian_error_message}
                                <p class="text-danger small mt-2 mb-0">{selectedReceipt.radian_error_message}</p>
                            {/if}
                        </div>
                        <button
                            class="btn btn-sm btn-light border d-inline-flex align-items-center gap-2"
                            type="button"
                            onclick={() => validateRadian(selectedReceipt)}
                        >
                            <ShieldCheck size={15} />
                            Validar
                        </button>
                    </div>
                </div>

                <div class="taguara-drawer-section">
                    <p class="text-uppercase small fw-semibold text-success mb-0">Totales</p>
                    <div class="taguara-drawer-grid">
                        <span class="taguara-drawer-label">Subtotal</span>
                        <span>{money.format(selectedReceipt.subtotal)}</span>
                        <span class="taguara-drawer-label">Impuestos</span>
                        <span>{money.format(selectedReceipt.tax_total)}</span>
                        <span class="taguara-drawer-label">Total</span>
                        <span class="fw-semibold">{money.format(selectedReceipt.total)}</span>
                    </div>
                </div>

                <div class="taguara-drawer-section">
                    <p class="text-uppercase small fw-semibold text-success mb-0">Lineas ({selectedReceipt.items_count})</p>
                    <div class="taguara-presentation-drawer">
                        {#each selectedReceipt.items as item}
                            <div class="taguara-presentation-drawer-row">
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                    <span class="fw-semibold small">{item.description}</span>
                                    <span class="small fw-semibold">{money.format(item.line_total)}</span>
                                </div>
                                <p class="text-secondary mb-0" style="font-size: 0.8125rem">
                                    Lote {item.lot_number} · {item.quantity} unidades · {money.format(item.unit_cost)}
                                    {#if item.expires_on} · Vence {item.expires_on}{/if}
                                </p>
                            </div>
                        {/each}
                    </div>
                </div>

                {#if selectedReceipt.notes}
                    <div class="taguara-drawer-section">
                        <p class="text-uppercase small fw-semibold text-success mb-0">Notas</p>
                        <p class="text-secondary small mb-0">{selectedReceipt.notes}</p>
                    </div>
                {/if}
            </div>
        </aside>
    {/if}
</AppLayout>
