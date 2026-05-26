<script>
    import { router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import { BarChart3, CircleDollarSign, Package, RotateCcw, TrendingUp } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';
    import ReportsNav from '../../Components/Reports/ReportsNav.svelte';

    let { auth, filters, summary, rows } = $props();

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const fmt = (v) => money.format(v ?? 0);
    const pct = (v) => `${Number(v ?? 0).toFixed(1)}%`;

    let form = $state(untrack(() => ({ from: filters.from, to: filters.to })));

    const submit = (e) => {
        e.preventDefault();
        router.get('/reports/profitability', form, { preserveState: true });
    };

    const resetToMonth = () => {
        const now = new Date();
        form.from = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
        form.to = now.toISOString().slice(0, 10);
        router.get('/reports/profitability', form, { preserveState: true });
    };

    const marginClass = (pctVal) => {
        if (pctVal >= 30) return 'text-success fw-semibold';
        if (pctVal >= 15) return 'text-warning fw-semibold';
        return 'text-danger fw-semibold';
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

        <ReportsNav active="profitability" />

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
                <button class="btn btn-light border taguara-icon-button" type="button" onclick={resetToMonth}><RotateCcw size={17} /></button>
            </form>
        </section>

        <section class="row g-3">
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-success"><CircleDollarSign size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Ingresos (sin IVA)</p>
                        <p class="h3 mb-1">{fmt(summary.revenue)}</p>
                        <p class="small text-secondary mb-0">{summary.products_count} productos vendidos</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-danger"><CircleDollarSign size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Costo de lotes</p>
                        <p class="h3 mb-1">{fmt(summary.cost)}</p>
                        <p class="small text-secondary mb-0">{summary.units_sold.toLocaleString('es-CO')} unidades</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon {summary.margin >= 0 ? 'text-bg-primary' : 'text-bg-danger'}">
                        <TrendingUp size={20} />
                    </span>
                    <div>
                        <p class="text-secondary small mb-1">Margen bruto</p>
                        <p class="h3 mb-1 {summary.margin >= 0 ? '' : 'text-danger'}">{fmt(summary.margin)}</p>
                        <p class="small text-secondary mb-0">Diferencia ingreso − costo</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon {summary.margin_pct >= 15 ? 'text-bg-success' : 'text-bg-warning'}">
                        <TrendingUp size={20} />
                    </span>
                    <div>
                        <p class="text-secondary small mb-1">% Margen bruto</p>
                        <p class="h3 mb-1">{pct(summary.margin_pct)}</p>
                        <p class="small text-secondary mb-0">Sobre ingresos del periodo</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Productos</p>
                    <h3 class="h5 mb-0">Rentabilidad por producto</h3>
                </div>
            </div>

            <div class="taguara-table-wrapper mt-3">
                <table class="taguara-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th class="text-center">Uds. vendidas</th>
                            <th class="text-end">Ingresos</th>
                            <th class="text-end">Costo</th>
                            <th class="text-end">Margen</th>
                            <th class="text-end">% Margen</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each rows as row}
                            <tr>
                                <td>
                                    <div class="taguara-table-name">{row.product}</div>
                                    {#if row.form}
                                        <div class="taguara-table-sub">{row.form}</div>
                                    {/if}
                                </td>
                                <td class="text-center">
                                    <span class="badge text-bg-light border text-secondary">{row.units_sold.toLocaleString('es-CO')}</span>
                                </td>
                                <td class="text-end" style="font-size:.875rem">{fmt(row.revenue)}</td>
                                <td class="text-end" style="font-size:.875rem">{fmt(row.cost)}</td>
                                <td class="text-end fw-semibold {row.margin >= 0 ? '' : 'text-danger'}">{fmt(row.margin)}</td>
                                <td class="text-end {marginClass(row.margin_pct)}">{pct(row.margin_pct)}</td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="6">
                                    <div class="taguara-empty-state" style="min-height:120px">
                                        <Package size={28} />
                                        <p class="text-secondary small mb-0">Sin ventas en el periodo seleccionado.</p>
                                    </div>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</AppLayout>
