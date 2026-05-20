<script>
    import { router } from '@inertiajs/svelte';
    import { BarChart3, CircleDollarSign, CreditCard, DollarSign, Package, RotateCcw } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';
    import ReportsNav from '../../Components/Reports/ReportsNav.svelte';

    let { auth, filters, totals, byDay, topProducts } = $props();

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const fmt = (v) => money.format(v ?? 0);

    let form = $state({ from: filters.from, to: filters.to });

    const submit = (e) => {
        e.preventDefault();
        router.get('/reports/sales', form, { preserveState: true });
    };

    const resetToMonth = () => {
        const now = new Date();
        form.from = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
        form.to = now.toISOString().slice(0, 10);
        router.get('/reports/sales', form, { preserveState: true });
    };

    const maxTotal = $derived(Math.max(...byDay.map((d) => d.total), 1));
    const barH = (v) => Math.max(4, Math.round((v / maxTotal) * 100));
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

        <ReportsNav active="sales" />

        <!-- Filtro de fechas -->
        <section class="taguara-panel">
            <form class="d-flex flex-wrap align-items-end gap-3" onsubmit={submit}>
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Desde</span>
                    <input class="form-control" type="date" bind:value={form.from} />
                </label>
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Hasta</span>
                    <input class="form-control" type="date" bind:value={form.to} />
                </label>
                <button class="btn btn-taguara" type="submit">Aplicar</button>
                <button class="btn btn-light border taguara-icon-button" type="button" aria-label="Mes actual" onclick={resetToMonth}><RotateCcw size={17} /></button>
            </form>
        </section>

        <!-- KPIs -->
        <section class="row g-3">
            <div class="col-12 col-md-4">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-success"><CircleDollarSign size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Total facturado</p>
                        <p class="h3 mb-1">{fmt(totals.gross_total)}</p>
                        <p class="small text-secondary mb-0">{totals.sales_count} ventas · {fmt(totals.tax_total)} IVA</p>
                    </div>
                </article>
            </div>
            {#each totals.by_method as m}
                <div class="col-12 col-md-4">
                    <article class="taguara-kpi-card">
                        <span class="taguara-kpi-icon text-bg-primary">
                            {#if m.method === 'Efectivo'}<DollarSign size={20} />{:else}<CreditCard size={20} />{/if}
                        </span>
                        <div>
                            <p class="text-secondary small mb-1">{m.method}</p>
                            <p class="h3 mb-1">{fmt(m.total)}</p>
                            <p class="small text-secondary mb-0">{m.count} ventas</p>
                        </div>
                    </article>
                </div>
            {/each}
        </section>

        <section class="row g-3">
            <!-- Gráfica por día -->
            <div class="col-12 col-xl-8">
                <div class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Tendencia</p>
                            <h3 class="h5 mb-0">Ventas por dia</h3>
                        </div>
                    </div>
                    {#if byDay.length === 0}
                        <div class="taguara-empty-state" style="min-height:140px">
                            <BarChart3 size={28} />
                            <p class="text-secondary small mb-0">Sin ventas en el periodo seleccionado.</p>
                        </div>
                    {:else}
                        <div class="taguara-chart-container" style="height:160px">
                            <div class="taguara-chart-bars-real">
                                {#each byDay as day}
                                    <div class="taguara-chart-col">
                                        <span class="taguara-chart-tooltip">{fmt(day.total)}</span>
                                        <span class="taguara-chart-bar" style="height:{barH(day.total)}%"></span>
                                        <span class="taguara-chart-label">{day.date.slice(8)}/{day.date.slice(5, 7)}</span>
                                    </div>
                                {/each}
                            </div>
                        </div>
                    {/if}
                </div>
            </div>

            <!-- Top productos -->
            <div class="col-12 col-xl-4">
                <div class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Ranking</p>
                            <h3 class="h5 mb-0">Productos mas vendidos</h3>
                        </div>
                        <Package class="text-secondary" size={20} />
                    </div>
                    {#if topProducts.length === 0}
                        <p class="text-secondary small mb-0">Sin ventas en el periodo.</p>
                    {:else}
                        <div class="vstack gap-2">
                            {#each topProducts as product, i}
                                <div class="d-flex align-items-center justify-content-between gap-2">
                                    <div class="d-flex align-items-center gap-2 min-w-0">
                                        <span class="taguara-alert-value" style="min-width:26px;height:26px;font-size:.75rem">{i + 1}</span>
                                        <span class="small fw-semibold text-truncate">{product.description}</span>
                                    </div>
                                    <div class="text-end flex-shrink-0">
                                        <div class="small fw-semibold">{fmt(product.revenue)}</div>
                                        <div class="taguara-table-sub">{product.qty_sold} und.</div>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    {/if}
                </div>
            </div>
        </section>
    </div>
</AppLayout>
