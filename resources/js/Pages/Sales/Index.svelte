<script>
    import { Link, router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import { fade, fly } from 'svelte/transition';
    import {
        AlertCircle,
        Ban,
        CheckCircle2,
        CircleDollarSign,
        Eye,
        Filter,
        Paperclip,
        Printer,
        PrinterCheck,
        ReceiptText,
        RotateCcw,
        Search,
        ShieldCheck,
        ShieldOff,
        User,
        X,
    } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';
    import QzPrinter from '../../Services/QzPrinter.js';

    let { auth, sales, filters, stats, paymentMethods, statuses } = $props();

    const printerName = $derived(auth?.tenant?.printer_settings?.printer_name ?? null);
    const paperWidth  = $derived(auth?.tenant?.printer_settings?.paper_width ?? '80mm');
    const copies      = $derived(auth?.tenant?.printer_settings?.copies ?? 1);

    let thermalPrinting = $state(null); // uuid currently printing
    let thermalError    = $state('');

    function saleToThermalData(sale) {
        return {
            document_number:  sale.document_number,
            total:            sale.total,
            subtotal:         sale.subtotal,
            tax_total:        sale.tax_total,
            discount_total:   0,
            payment_form:     sale.payment_form?.value ?? '1',
            payment_method:   sale.payment_method?.label ?? '',
            change_amount:    sale.change_amount,
            payment_due_date: sale.payment_due_date,
            customer_name:    sale.customer_name ?? null,
            cashier_name:     sale.cashier,
            created_at:       sale.created_at,
            status:           sale.status?.value,
            items:            [], // no disponible en la lista
        };
    }

    async function printThermal(sale) {
        if (!printerName) return;
        thermalPrinting = sale.uuid;
        thermalError    = '';
        try {
            if (!QzPrinter.connected) await QzPrinter.connect();
            await QzPrinter.printReceipt(printerName, saleToThermalData(sale), { paperWidth, copies });
        } catch (err) {
            thermalError = err?.message ?? 'Error al imprimir.';
        } finally {
            thermalPrinting = null;
        }
    }

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const fmt = (v) => money.format(v ?? 0);

    let form = $state(untrack(() => ({
        q: filters.q ?? '',
        method: filters.method ?? '',
        status: filters.status ?? '',
        from: filters.from ?? '',
        to: filters.to ?? '',
    })));

    let selectedSale = $state(null);
    let confirmVoid = $state(null);

    $effect(() => {
        document.body.style.overflow = selectedSale || confirmVoid ? 'hidden' : '';
        return () => { document.body.style.overflow = ''; };
    });

    const handleKeydown = (e) => {
        if (e.key === 'Escape') { selectedSale = null; confirmVoid = null; }
    };

    const submit = (e) => {
        e.preventDefault();
        router.get('/sales', form, { preserveState: true, replace: true });
    };

    const resetFilters = () => {
        form = { q: '', method: '', status: '', from: '', to: '' };
        router.get('/sales', {}, { preserveState: true, replace: true });
    };

    const openDetail = (sale) => { selectedSale = sale; };
    const closeDetail = () => { selectedSale = null; };

    const openVoidConfirm = (sale) => {
        selectedSale = null;
        confirmVoid = sale;
    };

    const retryFe = (sale) => {
        router.post(`/sales/${sale.uuid}/retry-fe`, {}, { preserveScroll: true });
    };

    const doVoid = () => {
        if (!confirmVoid) return;
        router.post(`/sales/${confirmVoid.uuid}/void`, {}, {
            onSuccess: () => { confirmVoid = null; },
            preserveScroll: true,
        });
    };

    const statusClass = (value) => {
        if (value === 'completed') return 'text-bg-success';
        if (value === 'voided') return 'text-bg-danger';
        return 'text-bg-secondary';
    };
</script>

<svelte:window onkeydown={handleKeydown} />

<AppLayout title="Ventas" activeSection="sales" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Punto de venta</p>
                <h2 class="h3 mb-2">Historial de ventas</h2>
                <p class="text-secondary mb-0">Consulta, imprime y anula comprobantes de venta.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <Link class="btn btn-light border d-inline-flex align-items-center gap-2" href="/sales/receivables">
                    <CircleDollarSign size={17} />
                    Cartera
                </Link>
                <Link class="btn btn-taguara d-inline-flex align-items-center gap-2" href="/pos">
                    <ReceiptText size={18} />
                    Ir al POS
                </Link>
            </div>
        </section>

        <!-- KPIs del día -->
        <section class="row g-3">
            <div class="col-12 col-md-4">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-success"><CircleDollarSign size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Ventas de hoy</p>
                        <p class="h3 mb-1">{fmt(stats.total_today)}</p>
                        <p class="small text-secondary mb-0">{stats.count_today} transacciones</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-4">
                <article class="taguara-kpi-card">
                    <span class={`taguara-kpi-icon ${stats.voided_today > 0 ? 'text-bg-danger' : 'text-bg-secondary'}`}>
                        <Ban size={20} />
                    </span>
                    <div>
                        <p class="text-secondary small mb-1">Anuladas hoy</p>
                        <p class="h3 mb-1">{stats.voided_today}</p>
                        <p class="small text-secondary mb-0">Comprobantes revertidos</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-4">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-primary"><ReceiptText size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Total en lista</p>
                        <p class="h3 mb-1">{sales.total}</p>
                        <p class="small text-secondary mb-0">Con los filtros actuales</p>
                    </div>
                </article>
            </div>
        </section>

        <!-- Filtros -->
        <section class="taguara-panel">
            <div class="taguara-panel-header align-items-start">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Busqueda</p>
                    <h3 class="h5 mb-0">Filtros</h3>
                </div>
                <Filter class="text-secondary" size={22} />
            </div>
            <form class="row g-2 align-items-end" onsubmit={submit}>
                <div class="col-12 col-md-3">
                    <label class="form-label mb-0">
                        <span class="small fw-semibold text-secondary">Numero</span>
                        <span class="taguara-filter-input">
                            <Search size={17} />
                            <input class="form-control" bind:value={form.q} type="search" placeholder="VTA-00000001">
                        </span>
                    </label>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label mb-0">
                        <span class="small fw-semibold text-secondary">Metodo</span>
                        <select class="form-select" bind:value={form.method}>
                            <option value="">Todos</option>
                            {#each paymentMethods as m}
                                <option value={m.value}>{m.label}</option>
                            {/each}
                        </select>
                    </label>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label mb-0">
                        <span class="small fw-semibold text-secondary">Estado</span>
                        <select class="form-select" bind:value={form.status}>
                            <option value="">Todos</option>
                            {#each statuses as s}
                                <option value={s.value}>{s.label}</option>
                            {/each}
                        </select>
                    </label>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label mb-0">
                        <span class="small fw-semibold text-secondary">Desde</span>
                        <input class="form-control" type="date" bind:value={form.from}>
                    </label>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label mb-0">
                        <span class="small fw-semibold text-secondary">Hasta</span>
                        <input class="form-control" type="date" bind:value={form.to}>
                    </label>
                </div>
                <div class="col-12 col-md-1 d-flex gap-2">
                    <button class="btn btn-taguara" type="submit"><Search size={17} /></button>
                    <button class="btn btn-light border taguara-icon-button" type="button" onclick={resetFilters}><RotateCcw size={17} /></button>
                </div>
            </form>
        </section>

        <!-- Tabla -->
        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Resultados</p>
                    <h3 class="h5 mb-0">{sales.total} ventas encontradas</h3>
                </div>
                <span class="badge text-bg-light border text-secondary">
                    Pagina {sales.current_page} de {sales.last_page}
                </span>
            </div>

            {#if sales.data.length > 0}
                <div class="taguara-table-wrapper">
                    <table class="taguara-table">
                        <thead>
                            <tr>
                                <th>Comprobante</th>
                                <th>Cajero · Caja</th>
                                <th>Metodo</th>
                                <th class="text-center">Items</th>
                                <th class="text-end">Total</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each sales.data as sale}
                                <tr onclick={() => openDetail(sale)}>
                                    <td>
                                        <div class="taguara-table-name">{sale.document_number}</div>
                                        <div class="taguara-table-sub">{sale.created_at}</div>
                                    </td>
                                    <td>
                                        <div style="font-size:.875rem">{sale.cashier}</div>
                                        <div class="taguara-table-sub">{sale.register}</div>
                                    </td>
                                    <td style="font-size:.875rem">
                                        {#if sale.payment_form?.value === '2'}
                                            <span class="badge text-bg-info me-1">Crédito</span>
                                        {/if}
                                        <span class="text-secondary">{sale.payment_method.label}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge text-bg-light border text-secondary">{sale.items_count}</span>
                                    </td>
                                    <td class="text-end fw-semibold" style={sale.status.value === 'voided' ? 'text-decoration:line-through;color:var(--bs-secondary)' : ''}>
                                        {fmt(sale.total)}
                                    </td>
                                    <td>
                                        <span class={`badge ${statusClass(sale.status.value)}`}>{sale.status.label}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 justify-content-end">
                                            <a
                                                class="btn btn-sm btn-light border taguara-icon-button-sm"
                                                href={`/sales/${sale.uuid}/receipt`}
                                                target="_blank"
                                                rel="noopener"
                                                aria-label="Imprimir recibo HTML"
                                                onclick={(e) => e.stopPropagation()}
                                            >
                                                <PrinterCheck size={15} />
                                            </a>
                                            {#if printerName}
                                                <button
                                                    class="btn btn-sm btn-light border taguara-icon-button-sm"
                                                    type="button"
                                                    aria-label="Imprimir térmica"
                                                    disabled={thermalPrinting === sale.uuid}
                                                    onclick={(e) => { e.stopPropagation(); printThermal(sale); }}
                                                >
                                                    <Printer size={15} />
                                                </button>
                                            {/if}
                                            <button
                                                class="btn btn-sm btn-light border taguara-icon-button-sm"
                                                type="button"
                                                aria-label="Ver detalle"
                                                onclick={(e) => { e.stopPropagation(); openDetail(sale); }}
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

                {#if sales.links.length > 3}
                    <nav class="taguara-pagination">
                        {#each sales.links as link}
                            {#if link.url}
                                <Link class={`btn btn-sm ${link.active ? 'btn-taguara' : 'btn-light border'}`} href={link.url}>{@html link.label}</Link>
                            {:else}
                                <span class="btn btn-sm btn-light border disabled">{@html link.label}</span>
                            {/if}
                        {/each}
                    </nav>
                {/if}
            {:else}
                <div class="taguara-empty-state">
                    <ReceiptText size={34} />
                    <h4 class="h6 mb-1">No hay ventas con estos filtros</h4>
                    <p class="text-secondary mb-0">Ajusta los filtros o registra una venta desde el POS.</p>
                </div>
            {/if}
        </section>
    </div>

    <!-- Drawer de detalle -->
    {#if selectedSale}
        <div class="taguara-drawer-backdrop" transition:fade={{ duration: 150 }} onclick={closeDetail} role="presentation"></div>
        <aside class="taguara-drawer" transition:fly={{ x: 480, duration: 220 }}>
            <div class="taguara-drawer-header">
                <div class="d-flex align-items-start justify-content-between gap-2">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h2 class="h5 mb-0">{selectedSale.document_number}</h2>
                            <span class={`badge ${statusClass(selectedSale.status.value)}`}>{selectedSale.status.label}</span>
                        </div>
                        <p class="text-secondary small mb-0">{selectedSale.created_at} · {selectedSale.cashier}</p>
                    </div>
                    <button class="btn btn-light border taguara-icon-button flex-shrink-0" type="button" aria-label="Cerrar detalle" onclick={closeDetail}><X size={17} /></button>
                </div>
            </div>

            <div class="taguara-drawer-body">

                <!-- Cliente -->
                {#if selectedSale.customer_name}
                    <div class="taguara-drawer-section">
                        <p class="text-uppercase small fw-semibold text-success mb-2">Cliente</p>
                        <div class="d-flex align-items-center gap-2">
                            <User size={14} class="text-secondary" />
                            <span style="font-size:.875rem">{selectedSale.customer_name}</span>
                        </div>
                    </div>
                {/if}

                <!-- Pago y totales -->
                <div class="taguara-drawer-section">
                    <p class="text-uppercase small fw-semibold text-success mb-2">Pago</p>
                    <div class="taguara-drawer-grid">
                        <span class="taguara-drawer-label">Forma</span>
                        <span>
                            {selectedSale.payment_form?.label ?? 'Contado'}
                            {#if selectedSale.payment_form?.value === '2' && selectedSale.payment_due_date}
                                <span class="badge text-bg-warning text-dark ms-1" style="font-size:.65rem">Vence {selectedSale.payment_due_date}</span>
                            {/if}
                        </span>
                        <span class="taguara-drawer-label">Método</span>
                        <span>{selectedSale.payment_method.label}</span>
                        <span class="taguara-drawer-label">Subtotal</span>
                        <span>{fmt(selectedSale.subtotal)}</span>
                        <span class="taguara-drawer-label">IVA</span>
                        <span>{fmt(selectedSale.tax_total)}</span>
                        <span class="taguara-drawer-label fw-bold">Total</span>
                        <span class="fw-bold">{fmt(selectedSale.total)}</span>
                        {#if selectedSale.amount_tendered}
                            <span class="taguara-drawer-label">Recibido</span>
                            <span>{fmt(selectedSale.amount_tendered)}</span>
                            <span class="taguara-drawer-label">Cambio</span>
                            <span>{fmt(selectedSale.change_amount)}</span>
                        {/if}
                        <span class="taguara-drawer-label">Caja</span>
                        <span>{selectedSale.register}</span>
                        <span class="taguara-drawer-label">Cajero</span>
                        <span>{selectedSale.cashier}</span>
                    </div>
                    {#if selectedSale.payments?.some((payment) => payment.has_attachment)}
                        <div class="mt-3 d-grid gap-2">
                            {#each selectedSale.payments.filter((payment) => payment.has_attachment) as payment}
                                <a
                                    class="btn btn-sm btn-light border d-inline-flex align-items-center justify-content-center gap-2"
                                    href={payment.attachment_url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Paperclip size={14} />
                                    Comprobante {payment.method} · {fmt(payment.amount)}
                                </a>
                            {/each}
                        </div>
                    {/if}
                </div>

                <!-- Facturación electrónica -->
                {#if selectedSale.fe?.status}
                    <div class="taguara-drawer-section">
                        <p class="text-uppercase small fw-semibold text-success mb-2">Facturación electrónica</p>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            {#if selectedSale.fe.status === 'accepted'}
                                <ShieldCheck size={16} class="text-success" />
                                <span class="badge text-bg-success">{selectedSale.fe.status_label}</span>
                            {:else if selectedSale.fe.status === 'rejected'}
                                <ShieldOff size={16} class="text-danger" />
                                <span class="badge text-bg-danger">{selectedSale.fe.status_label}</span>
                            {:else if selectedSale.fe.status === 'contingency'}
                                <AlertCircle size={16} class="text-warning" />
                                <span class="badge text-bg-warning text-dark">{selectedSale.fe.status_label}</span>
                            {:else}
                                <span class="badge text-bg-warning text-dark">{selectedSale.fe.status_label}</span>
                            {/if}
                        </div>
                        {#if selectedSale.fe.cufe}
                            <div class="taguara-drawer-grid">
                                <span class="taguara-drawer-label">CUFE</span>
                                <span class="font-monospace text-truncate" style="font-size:.7rem" title={selectedSale.fe.cufe}>
                                    {selectedSale.fe.cufe.slice(0, 20)}…
                                </span>
                            </div>
                        {/if}
                        {#if selectedSale.fe.error_message}
                            <div class="alert alert-danger py-1 px-2 small mt-2 mb-0">
                                {selectedSale.fe.error_message}
                            </div>
                        {/if}
                    </div>
                {/if}

            </div>

            <div class="taguara-drawer-footer vstack gap-2">
                <Link
                    class="btn btn-taguara w-100 d-inline-flex align-items-center justify-content-center gap-2"
                    href={`/sales/${selectedSale.uuid}`}
                >
                    <Eye size={17} />
                    Ver detalle completo
                </Link>
                <div class="d-flex gap-2">
                    <a
                        class="btn btn-light border flex-fill d-inline-flex align-items-center justify-content-center gap-2"
                        href={`/sales/${selectedSale.uuid}/receipt`}
                        target="_blank"
                        rel="noopener"
                    >
                        <PrinterCheck size={17} />
                        Recibo HTML
                    </a>
                    {#if printerName}
                        <button
                            class="btn btn-light border flex-fill d-inline-flex align-items-center justify-content-center gap-2"
                            type="button"
                            disabled={thermalPrinting === selectedSale.uuid}
                            onclick={() => printThermal(selectedSale)}
                        >
                            <Printer size={17} />
                            {thermalPrinting === selectedSale.uuid ? 'Imprimiendo...' : 'Térmica'}
                        </button>
                    {/if}
                </div>
                {#if thermalError}
                    <p class="text-danger small mb-0">{thermalError}</p>
                {/if}
                {#if selectedSale.fe?.status === 'pending' || selectedSale.fe?.status === 'rejected' || selectedSale.fe?.status === 'contingency'}
                    <button
                        class="btn btn-outline-warning w-100 d-inline-flex align-items-center justify-content-center gap-2"
                        type="button"
                        onclick={() => retryFe(selectedSale)}
                    >
                        <ShieldCheck size={17} />
                        Reintentar emisión FE
                    </button>
                {/if}
                {#if selectedSale.status.value === 'completed'}
                    <a
                        class="btn btn-outline-secondary w-100 d-inline-flex align-items-center justify-content-center gap-2"
                        href={`/sales/${selectedSale.uuid}/credit-notes/create`}
                    >
                        <ShieldOff size={17} />
                        Nota crédito / devolución
                    </a>
                {/if}
                {#if selectedSale.status.value === 'completed'}
                    <button
                        class="btn btn-outline-danger w-100 d-inline-flex align-items-center justify-content-center gap-2"
                        type="button"
                        onclick={() => openVoidConfirm(selectedSale)}
                    >
                        <Ban size={17} />
                        Anular venta
                    </button>
                {/if}
            </div>
        </aside>
    {/if}

    <!-- Modal confirmación de anulación -->
    {#if confirmVoid}
        <div class="taguara-drawer-backdrop" transition:fade={{ duration: 150 }} onclick={() => confirmVoid = null} role="presentation"></div>
        <div class="taguara-pos-modal" transition:fly={{ y: -20, duration: 180 }}>
            <div class="taguara-pos-modal-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="taguara-kpi-icon text-bg-danger"><AlertCircle size={20} /></span>
                    <div>
                        <p class="text-uppercase small fw-semibold text-danger mb-1">Anulacion</p>
                        <h2 class="h5 mb-0">{confirmVoid.document_number}</h2>
                    </div>
                </div>
                <button class="btn btn-light border taguara-icon-button" type="button" onclick={() => confirmVoid = null}><X size={17} /></button>
            </div>
            <div class="taguara-pos-modal-body">
                <p class="text-secondary mb-4">
                    Esta accion anulara la venta <strong>{confirmVoid.document_number}</strong> ({fmt(confirmVoid.total)}).
                    El stock de todos los productos sera devuelto al inventario automaticamente.
                    Esta accion no se puede deshacer.
                </p>
                <div class="d-flex gap-2">
                    <button class="btn btn-light border flex-fill" type="button" onclick={() => confirmVoid = null}>Cancelar</button>
                    <button class="btn btn-danger flex-fill d-inline-flex align-items-center justify-content-center gap-2" type="button" onclick={doVoid}>
                        <Ban size={17} />
                        Confirmar anulacion
                    </button>
                </div>
            </div>
        </div>
    {/if}
</AppLayout>
