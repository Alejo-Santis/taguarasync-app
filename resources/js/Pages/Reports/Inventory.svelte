<script>
    import { Link, router } from '@inertiajs/svelte';
    import { AlertTriangle, BarChart3, Boxes, CircleDollarSign, RotateCcw } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';
    import ReportsNav from '../../Components/Reports/ReportsNav.svelte';

    let { auth, filters, lots, summary } = $props();

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const fmt = (v) => money.format(v ?? 0);

    let expiry = $state(filters.expiry ?? '');

    const expiryClass = (days) => {
        if (days === null) return 'text-secondary';
        if (days < 0) return 'text-danger fw-semibold';
        if (days <= 30) return 'text-danger';
        if (days <= 90) return 'text-warning';
        return 'text-success';
    };
</script>

<AppLayout title="Reportes" activeSection="reports" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Reportes</p>
                <h2 class="h3 mb-2">Analisis operativo</h2>
                <p class="text-secondary mb-0">Ventas, inventario y compras en un vistazo.</p>
            </div>
            <BarChart3 class="text-secondary" size={22} />
        </section>

        <ReportsNav active="inventory" />

        <!-- Filtros -->
        <section class="taguara-panel">
            <div class="d-flex flex-wrap gap-2">
                {#each [['', 'Todo el inventario'], ['expiring', 'Por vencer (90 dias)'], ['expired', 'Vencidos en stock']] as [val, lbl]}
                    <button
                        type="button"
                        class={`btn btn-sm ${expiry === val ? 'btn-taguara' : 'btn-light border'}`}
                        onclick={() => { expiry = val; router.get('/reports/inventory', { expiry: val }, { preserveState: true }); }}
                    >
                        {lbl}
                    </button>
                {/each}
            </div>
        </section>

        <!-- KPIs -->
        <section class="row g-3">
            <div class="col-12 col-md-4">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-primary"><Boxes size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Unidades en stock</p>
                        <p class="h3 mb-1">{summary.total_units.toLocaleString('es-CO')}</p>
                        <p class="small text-secondary mb-0">{summary.lots_count} lotes activos</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-4">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-success"><CircleDollarSign size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Valor del inventario</p>
                        <p class="h3 mb-1">{fmt(summary.total_value)}</p>
                        <p class="small text-secondary mb-0">A precio de costo</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-4">
                <article class="taguara-kpi-card">
                    <span class={`taguara-kpi-icon ${summary.expiring_30 > 0 || summary.expired > 0 ? 'text-bg-warning' : 'text-bg-secondary'}`}>
                        <AlertTriangle size={20} />
                    </span>
                    <div>
                        <p class="text-secondary small mb-1">Alertas de vencimiento</p>
                        <p class="h3 mb-1">{summary.expiring_30 + summary.expired}</p>
                        <p class="small text-secondary mb-0">{summary.expiring_30} por vencer · {summary.expired} vencidos</p>
                    </div>
                </article>
            </div>
        </section>

        <!-- Tabla de lotes -->
        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Lotes</p>
                    <h3 class="h5 mb-0">{lots.total} lotes disponibles</h3>
                </div>
            </div>

            <div class="taguara-table-wrapper">
                <table class="taguara-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Lote</th>
                            <th>Presentacion</th>
                            <th class="text-end">Stock</th>
                            <th class="text-end">Costo unit.</th>
                            <th class="text-end">Valor</th>
                            <th>Vencimiento</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each lots.data as lot}
                            <tr>
                                <td>
                                    <div class="taguara-table-name">{lot.product}</div>
                                    <div class="taguara-table-sub">{lot.internal_code}</div>
                                </td>
                                <td class="fw-semibold small">{lot.lot_number}</td>
                                <td class="text-secondary" style="font-size:.875rem">{lot.presentation}</td>
                                <td class="text-end fw-semibold">{lot.current_quantity.toLocaleString('es-CO')}</td>
                                <td class="text-end" style="font-size:.875rem">{fmt(lot.unit_cost)}</td>
                                <td class="text-end fw-semibold">{fmt(lot.value)}</td>
                                <td class={expiryClass(lot.days_to_expiry)}>{lot.expires_on}</td>
                            </tr>
                        {:else}
                            <tr><td colspan="7"><div class="taguara-empty-state" style="min-height:120px"><Boxes size={28} /><p class="text-secondary small mb-0">Sin lotes con este filtro.</p></div></td></tr>
                        {/each}
                    </tbody>
                </table>
            </div>

            {#if lots.links.length > 3}
                <nav class="taguara-pagination mt-3">
                    {#each lots.links as link}
                        {#if link.url}<Link class={`btn btn-sm ${link.active ? 'btn-taguara' : 'btn-light border'}`} href={link.url}>{@html link.label}</Link>
                        {:else}<span class="btn btn-sm btn-light border disabled">{@html link.label}</span>{/if}
                    {/each}
                </nav>
            {/if}
        </section>
    </div>
</AppLayout>
