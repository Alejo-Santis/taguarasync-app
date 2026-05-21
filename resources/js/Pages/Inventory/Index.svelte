<script>
    import { Link, router, useForm } from '@inertiajs/svelte';
    import { fade, fly } from 'svelte/transition';
    import {
        AlertTriangle,
        ArrowDownCircle,
        ArrowUpCircle,
        Boxes,
        CalendarClock,
        CircleDollarSign,
        Eye,
        Filter,
        PackageSearch,
        RotateCcw,
        Search,
        X,
    } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';

    let { auth, lots, filters, stats, statuses } = $props();

    let form = $state({
        q: '',
        status: '',
        expiry: '',
    });
    let selectedLot = $state(null);

    // Adjustment modal
    let adjustModalOpen = $state(false);
    let adjustingLot = $state(null);
    const adjustForm = useForm({ inventory_lot_id: '', type: 'in', quantity: 1, reason: '' });

    const openAdjust = (lot) => {
        adjustingLot = lot;
        adjustForm.inventory_lot_id = lot.id ?? '';
        adjustForm.type = 'in';
        adjustForm.quantity = 1;
        adjustForm.reason = '';
        selectedLot = null;
        adjustModalOpen = true;
    };

    const closeAdjust = () => {
        adjustModalOpen = false;
        adjustingLot = null;
        adjustForm.reset();
        adjustForm.clearErrors();
    };

    const submitAdjust = () => {
        adjustForm.post('/inventory/adjust', {
            onSuccess: closeAdjust,
            preserveScroll: true,
        });
    };

    const money = new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    });

    const hasLots = $derived(lots.data.length > 0);

    $effect(() => {
        form.q = filters.q ?? '';
        form.status = filters.status ?? '';
        form.expiry = filters.expiry ?? '';
    });

    $effect(() => {
        document.body.style.overflow = selectedLot ? 'hidden' : '';
        return () => { document.body.style.overflow = ''; };
    });

    const submit = (event) => {
        event.preventDefault();

        router.get('/inventory', form, {
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        form = { q: '', status: '', expiry: '' };

        router.get('/inventory', {}, {
            preserveState: true,
            replace: true,
        });
    };

    const openDetail = (lot) => { selectedLot = lot; };
    const closeDetail = () => { selectedLot = null; };

    const handleKeydown = (event) => {
        if (event.key === 'Escape' && selectedLot) {
            closeDetail();
        }
    };

    const statusClass = (status) => {
        if (status === 'available') {
            return 'text-bg-success';
        }

        if (status === 'depleted') {
            return 'text-bg-secondary';
        }

        if (status === 'expired') {
            return 'text-bg-danger';
        }

        return 'text-bg-warning';
    };

    const expiryClass = (days) => {
        if (days === null) {
            return 'text-secondary';
        }

        if (days < 0) {
            return 'text-danger';
        }

        if (days <= 90) {
            return 'text-warning';
        }

        return 'text-success';
    };
</script>

<svelte:window onkeydown={handleKeydown} />

<AppLayout title="Inventario" activeSection="inventory" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Stock por lote</p>
                <h2 class="h3 mb-2">Inventario operativo</h2>
                <p class="text-secondary mb-0">Consulta existencias, vencimientos y valor en stock antes de conectar compras y POS.</p>
            </div>
        </section>

        <section class="row g-3">
            <div class="col-12 col-md-6 col-xl-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-primary"><Boxes size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Lotes</p>
                        <p class="h3 mb-1">{stats.lots}</p>
                        <p class="small text-secondary mb-0">Registrados</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-success"><PackageSearch size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Unidades minimas</p>
                        <p class="h3 mb-1">{stats.units}</p>
                        <p class="small text-secondary mb-0">Disponibles</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-warning"><CalendarClock size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Por vencer</p>
                        <p class="h3 mb-1">{stats.expiring}</p>
                        <p class="small text-secondary mb-0">Proximos 90 dias</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-secondary"><CircleDollarSign size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Agotados</p>
                        <p class="h3 mb-1">{stats.depleted}</p>
                        <p class="small text-secondary mb-0">Sin stock actual</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header align-items-start">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Busqueda</p>
                    <h3 class="h5 mb-0">Filtros de lotes</h3>
                </div>
                <Filter class="text-secondary" size={22} />
            </div>

            <form class="taguara-filter-grid" onsubmit={submit}>
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Producto, codigo o lote</span>
                    <span class="taguara-filter-input">
                        <Search size={17} />
                        <input class="form-control" bind:value={form.q} type="search" placeholder="Acetaminofen, ACET-500, LOT-001" />
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
                    <span class="small fw-semibold text-secondary">Vencimiento</span>
                    <select class="form-select" bind:value={form.expiry}>
                        <option value="">Todos</option>
                        <option value="soon">Proximos 90 dias</option>
                        <option value="expired">Vencidos</option>
                        <option value="none">Sin vencimiento</option>
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
                    <h3 class="h5 mb-0">{lots.total} lotes encontrados</h3>
                </div>
                <span class="badge text-bg-light border text-secondary">
                    Pagina {lots.current_page} de {lots.last_page}
                </span>
            </div>

            {#if hasLots}
                <div class="taguara-table-wrapper">
                    <table class="taguara-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Lote</th>
                                <th>Vencimiento</th>
                                <th class="text-end">Stock</th>
                                <th class="text-end">Costo unit.</th>
                                <th class="text-end">Valor</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each lots.data as lot}
                                <tr onclick={() => openDetail(lot)}>
                                    <td>
                                        <div class="taguara-table-name">{lot.product.name}</div>
                                        <div class="taguara-table-sub">
                                            {lot.product.internal_code ?? 'Sin codigo'} · {lot.presentation?.name ?? 'Sin presentacion'}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-semibold">{lot.lot_number}</span>
                                    </td>
                                    <td>
                                        <span class={expiryClass(lot.days_to_expiry)}>{lot.expires_label}</span>
                                        {#if lot.days_to_expiry !== null && lot.days_to_expiry <= 90}
                                            <div class="small text-secondary">
                                                {lot.days_to_expiry < 0 ? 'Vencido' : `${lot.days_to_expiry} dias`}
                                            </div>
                                        {/if}
                                    </td>
                                    <td class="text-end fw-semibold">{lot.current_quantity}</td>
                                    <td class="text-end">{money.format(lot.unit_cost)}</td>
                                    <td class="text-end fw-semibold">{money.format(lot.inventory_value)}</td>
                                    <td>
                                        <span class={`badge ${statusClass(lot.status.value)}`}>{lot.status.label}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 justify-content-end">
                                            <button
                                                class="btn btn-sm btn-light border taguara-icon-button-sm"
                                                type="button"
                                                aria-label="Ajustar inventario"
                                                title="Ajustar inventario"
                                                onclick={(event) => { event.stopPropagation(); openAdjust(lot); }}
                                            >
                                                <ArrowUpCircle size={15} />
                                            </button>
                                            <button
                                                class="btn btn-sm btn-light border taguara-icon-button-sm"
                                                type="button"
                                                aria-label="Ver detalle"
                                                onclick={(event) => { event.stopPropagation(); openDetail(lot); }}
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

                {#if lots.links.length > 3}
                    <nav class="taguara-pagination" aria-label="Paginacion de inventario">
                        {#each lots.links as link}
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
                    <AlertTriangle size={34} />
                    <h4 class="h6 mb-1">No hay lotes con estos filtros</h4>
                    <p class="text-secondary mb-0">Al registrar recepciones de compra, los lotes apareceran aqui con su stock real.</p>
                </div>
            {/if}
        </section>
    </div>

    {#if selectedLot}
        <div
            class="taguara-drawer-backdrop"
            transition:fade={{ duration: 150 }}
            onclick={closeDetail}
            role="presentation"
        ></div>

        <aside
            class="taguara-drawer"
            transition:fly={{ x: 480, duration: 220 }}
            aria-label="Detalle del lote"
        >
            <div class="taguara-drawer-header">
                <div class="d-flex align-items-start justify-content-between gap-2">
                    <div class="min-w-0">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <h2 class="h5 mb-0">{selectedLot.lot_number}</h2>
                            <span class={`badge ${statusClass(selectedLot.status.value)}`}>{selectedLot.status.label}</span>
                        </div>
                        <p class="text-secondary small mb-0">{selectedLot.product.name}</p>
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
                    <p class="text-uppercase small fw-semibold text-success mb-0">Producto</p>
                    <div class="taguara-drawer-grid">
                        <span class="taguara-drawer-label">Producto</span>
                        <span>{selectedLot.product.name ?? '—'}</span>
                        <span class="taguara-drawer-label">Codigo interno</span>
                        <span>{selectedLot.product.internal_code ?? '—'}</span>
                        <span class="taguara-drawer-label">Generico</span>
                        <span>{selectedLot.product.generic_name ?? '—'}</span>
                        <span class="taguara-drawer-label">Forma</span>
                        <span>{selectedLot.product.form ?? '—'}</span>
                        <span class="taguara-drawer-label">Concentracion</span>
                        <span>{selectedLot.product.concentration ?? '—'}</span>
                        <span class="taguara-drawer-label">Presentacion</span>
                        <span>{selectedLot.presentation?.name ?? '—'}</span>
                    </div>
                </div>

                <div class="taguara-drawer-section">
                    <p class="text-uppercase small fw-semibold text-success mb-0">Lote y vencimiento</p>
                    <div class="taguara-drawer-grid">
                        <span class="taguara-drawer-label">Lote</span>
                        <span>{selectedLot.lot_number}</span>
                        <span class="taguara-drawer-label">Vencimiento</span>
                        <span class={expiryClass(selectedLot.days_to_expiry)}>{selectedLot.expires_label}</span>
                        <span class="taguara-drawer-label">Dias restantes</span>
                        <span>
                            {selectedLot.days_to_expiry === null
                                ? '—'
                                : selectedLot.days_to_expiry < 0
                                    ? 'Vencido'
                                    : `${selectedLot.days_to_expiry} dias`}
                        </span>
                        <span class="taguara-drawer-label">Recibido</span>
                        <span>{selectedLot.received_at ?? '—'}</span>
                    </div>
                </div>

                <div class="taguara-drawer-section">
                    <p class="text-uppercase small fw-semibold text-success mb-0">Stock</p>
                    <div class="taguara-drawer-grid">
                        <span class="taguara-drawer-label">Cantidad inicial</span>
                        <span>{selectedLot.initial_quantity}</span>
                        <span class="taguara-drawer-label">Cantidad actual</span>
                        <span class="fw-semibold">{selectedLot.current_quantity}</span>
                        <span class="taguara-drawer-label">Costo unitario</span>
                        <span>{money.format(selectedLot.unit_cost)}</span>
                        <span class="taguara-drawer-label">Valor inventario</span>
                        <span class="fw-semibold">{money.format(selectedLot.inventory_value)}</span>
                    </div>
                </div>
            </div>
        </aside>
    {/if}

    <!-- Adjustment modal -->
    {#if adjustModalOpen}
        <div class="taguara-drawer-backdrop" transition:fade={{ duration: 150 }} onclick={closeAdjust} role="presentation"></div>
        <div class="taguara-pos-modal" transition:fly={{ y: -20, duration: 180 }}>
            <div class="taguara-pos-modal-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Ajuste de inventario</p>
                    <h2 class="h5 mb-0">{adjustingLot?.lot_number ?? ''}</h2>
                    {#if adjustingLot}
                        <p class="text-secondary small mb-0">{adjustingLot.product.name} · Stock actual: <strong>{adjustingLot.current_quantity}</strong></p>
                    {/if}
                </div>
                <button class="btn btn-light border taguara-icon-button" type="button" onclick={closeAdjust}><X size={17} /></button>
            </div>
            <div class="taguara-pos-modal-body">
                {#if adjustForm.errors.adjust}
                    <div class="alert alert-danger small mb-3">{adjustForm.errors.adjust}</div>
                {/if}

                <form id="adjust-form" class="vstack gap-3" onsubmit={(e) => { e.preventDefault(); submitAdjust(); }}>
                    <input type="hidden" bind:value={adjustForm.inventory_lot_id} />

                    <div>
                        <label class="form-label fw-semibold">Tipo de ajuste</label>
                        <div class="d-flex gap-2">
                            <button
                                type="button"
                                class={`btn flex-fill d-inline-flex align-items-center justify-content-center gap-2 ${adjustForm.type === 'in' ? 'btn-taguara' : 'btn-light border'}`}
                                onclick={() => adjustForm.type = 'in'}
                            >
                                <ArrowUpCircle size={17} />
                                Entrada de stock
                            </button>
                            <button
                                type="button"
                                class={`btn flex-fill d-inline-flex align-items-center justify-content-center gap-2 ${adjustForm.type === 'out' ? 'btn-danger' : 'btn-light border'}`}
                                onclick={() => adjustForm.type = 'out'}
                            >
                                <ArrowDownCircle size={17} />
                                Salida de stock
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="form-label" for="adj-qty">Cantidad (unidades minimas) <span class="text-danger">*</span></label>
                        <input id="adj-qty" class="form-control form-control-lg text-end" class:is-invalid={adjustForm.errors.quantity} type="number" min="1" bind:value={adjustForm.quantity}>
                        {#if adjustForm.errors.quantity}<div class="invalid-feedback">{adjustForm.errors.quantity}</div>{/if}
                    </div>

                    <div>
                        <label class="form-label" for="adj-reason">Motivo del ajuste <span class="text-danger">*</span></label>
                        <input id="adj-reason" class="form-control" class:is-invalid={adjustForm.errors.reason} type="text" bind:value={adjustForm.reason} placeholder="Merma, producto dañado, inventario fisico...">
                        {#if adjustForm.errors.reason}<div class="invalid-feedback">{adjustForm.errors.reason}</div>{/if}
                    </div>

                    <button
                        class={`btn btn-lg w-100 ${adjustForm.type === 'out' ? 'btn-danger' : 'btn-taguara'}`}
                        type="submit"
                        disabled={adjustForm.processing}
                    >
                        {adjustForm.processing ? 'Registrando...' : `Confirmar ${adjustForm.type === 'in' ? 'entrada' : 'salida'}`}
                    </button>
                </form>
            </div>
        </div>
    {/if}
</AppLayout>
