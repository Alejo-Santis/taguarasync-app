<script>
    import { Link, router } from '@inertiajs/svelte';
    import { ArrowLeft, ArrowDownCircle, ArrowUpCircle, ClipboardList, Filter, RotateCcw, Search } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';

    let { auth, filters, movements, summary, types } = $props();

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const fmt = (v) => money.format(v ?? 0);

    let form = $state({
        q: filters.q ?? '',
        type: filters.type ?? '',
        from: filters.from,
        to: filters.to,
    });

    const submit = (event) => {
        event.preventDefault();
        router.get('/inventory/kardex', form, { preserveState: true, replace: true });
    };

    const reset = () => {
        const now = new Date();
        form = {
            q: '',
            type: '',
            from: `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`,
            to: now.toISOString().slice(0, 10),
        };
        router.get('/inventory/kardex', form, { preserveState: true, replace: true });
    };

    const movementClass = (delta) => delta >= 0 ? 'text-success fw-semibold' : 'text-danger fw-semibold';
    const typeClass = (value) => {
        if (['purchase', 'opening', 'sale_return', 'adjustment_in', 'transfer_in'].includes(value)) return 'text-bg-success';
        if (['sale', 'purchase_return', 'adjustment_out', 'transfer_out'].includes(value)) return 'text-bg-danger';
        return 'text-bg-secondary';
    };
</script>

<AppLayout title="Kardex" activeSection="inventory" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Inventario</p>
                <h2 class="h3 mb-2">Kardex de movimientos</h2>
                <p class="text-secondary mb-0">Audita entradas, salidas y saldos por producto, lote o documento.</p>
            </div>
            <Link class="btn btn-light border d-inline-flex align-items-center gap-2" href="/inventory">
                <ArrowLeft size={18} />
                Inventario
            </Link>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header align-items-start">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Filtros</p>
                    <h3 class="h5 mb-0">Consultar movimientos</h3>
                </div>
                <Filter class="text-secondary" size={22} />
            </div>

            <form class="taguara-filter-grid" onsubmit={submit}>
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Producto, codigo, lote o documento</span>
                    <span class="taguara-filter-input">
                        <Search size={17} />
                        <input class="form-control" bind:value={form.q} type="search" placeholder="Dolex, LOT-001, VTA-00000001" />
                    </span>
                </label>
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Movimiento</span>
                    <select class="form-select" bind:value={form.type}>
                        <option value="">Todos</option>
                        {#each types as type}
                            <option value={type.value}>{type.label}</option>
                        {/each}
                    </select>
                </label>
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Desde</span>
                    <input class="form-control" type="date" bind:value={form.from} />
                </label>
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Hasta</span>
                    <input class="form-control" type="date" bind:value={form.to} />
                </label>
                <div class="d-flex align-items-end gap-2">
                    <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="submit">
                        <Search size={17} />
                        Filtrar
                    </button>
                    <button class="btn btn-light border taguara-icon-button" type="button" aria-label="Limpiar filtros" onclick={reset}>
                        <RotateCcw size={17} />
                    </button>
                </div>
            </form>
        </section>

        <section class="row g-3">
            <div class="col-12 col-md-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-primary"><ClipboardList size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Movimientos</p>
                        <p class="h3 mb-1">{summary.movements_count}</p>
                        <p class="small text-secondary mb-0">En el periodo</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-success"><ArrowUpCircle size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Entradas</p>
                        <p class="h3 mb-1">{summary.entries.toLocaleString('es-CO')}</p>
                        <p class="small text-secondary mb-0">Unidades minimas</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-danger"><ArrowDownCircle size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Salidas</p>
                        <p class="h3 mb-1">{summary.exits.toLocaleString('es-CO')}</p>
                        <p class="small text-secondary mb-0">Unidades minimas</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-3">
                <article class="taguara-kpi-card">
                    <span class={`taguara-kpi-icon ${summary.net >= 0 ? 'text-bg-secondary' : 'text-bg-warning'}`}><ClipboardList size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Neto</p>
                        <p class={`h3 mb-1 ${movementClass(summary.net)}`}>{summary.net.toLocaleString('es-CO')}</p>
                        <p class="small text-secondary mb-0">Entradas menos salidas</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Auditoria</p>
                    <h3 class="h5 mb-0">Movimientos de inventario</h3>
                </div>
                <span class="badge text-bg-light border text-secondary">Pagina {movements.current_page} de {movements.last_page}</span>
            </div>

            <div class="taguara-table-wrapper">
                <table class="taguara-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Lote</th>
                            <th>Movimiento</th>
                            <th class="text-end">Antes</th>
                            <th class="text-end">Mov.</th>
                            <th class="text-end">Saldo</th>
                            <th class="text-end">Costo</th>
                            <th class="text-end">Valor</th>
                            <th>Referencia</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each movements.data as movement}
                            <tr>
                                <td class="small text-secondary">{movement.occurred_at}</td>
                                <td>
                                    <div class="taguara-table-name">{movement.product.name}</div>
                                    <div class="taguara-table-sub">{movement.product.internal_code ?? 'Sin codigo'} · {movement.presentation ?? 'Sin presentacion'}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{movement.lot.number}</div>
                                    <div class="taguara-table-sub">{movement.lot.expires_on}</div>
                                </td>
                                <td><span class={`badge ${typeClass(movement.type.value)}`}>{movement.type.label}</span></td>
                                <td class="text-end">{movement.quantity_before.toLocaleString('es-CO')}</td>
                                <td class={`text-end ${movementClass(movement.quantity_delta)}`}>{movement.quantity_delta.toLocaleString('es-CO')}</td>
                                <td class="text-end fw-semibold">{movement.quantity_after.toLocaleString('es-CO')}</td>
                                <td class="text-end">{fmt(movement.unit_cost)}</td>
                                <td class="text-end">{fmt(movement.movement_value)}</td>
                                <td>
                                    <div class="fw-semibold">{movement.reference_code ?? '—'}</div>
                                    {#if movement.notes}<div class="taguara-table-sub">{movement.notes}</div>{/if}
                                </td>
                                <td>{movement.user ?? 'Sistema'}</td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="11">
                                    <div class="taguara-empty-state" style="min-height:130px">
                                        <ClipboardList size={30} />
                                        <p class="text-secondary small mb-0">No hay movimientos con estos filtros.</p>
                                    </div>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>

            {#if movements.links.length > 3}
                <nav class="taguara-pagination mt-3" aria-label="Paginacion de Kardex">
                    {#each movements.links as link}
                        {#if link.url}<Link class={`btn btn-sm ${link.active ? 'btn-taguara' : 'btn-light border'}`} href={link.url}>{@html link.label}</Link>
                        {:else}<span class="btn btn-sm btn-light border disabled">{@html link.label}</span>{/if}
                    {/each}
                </nav>
            {/if}
        </section>
    </div>
</AppLayout>
