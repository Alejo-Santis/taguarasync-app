<script>
    import { Link, router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import { BarChart3, CircleDollarSign, ReceiptText, RotateCcw } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';
    import ReportsNav from '../../Components/Reports/ReportsNav.svelte';

    let { auth, filters, receipts, summary } = $props();

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const fmt = (v) => money.format(v ?? 0);

    let form = $state(untrack(() => ({ from: filters.from, to: filters.to })));

    const submit = (e) => {
        e.preventDefault();
        router.get('/reports/purchases', form, { preserveState: true });
    };

    const resetToMonth = () => {
        const now = new Date();
        form.from = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
        form.to = now.toISOString().slice(0, 10);
        router.get('/reports/purchases', form, { preserveState: true });
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

        <ReportsNav active="purchases" />

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
                <button class="btn btn-light border taguara-icon-button" type="button" aria-label="Reiniciar al mes actual" onclick={resetToMonth}><RotateCcw size={17} /></button>
            </form>
        </section>

        <section class="row g-3">
            <div class="col-12 col-md-4">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-primary"><ReceiptText size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Recepciones</p>
                        <p class="h3 mb-1">{summary.count}</p>
                        <p class="small text-secondary mb-0">Documentos en el periodo</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-4">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-success"><CircleDollarSign size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Total invertido</p>
                        <p class="h3 mb-1">{fmt(summary.total)}</p>
                        <p class="small text-secondary mb-0">Costo de compra</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-4">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-info"><CircleDollarSign size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">IVA pagado</p>
                        <p class="h3 mb-1">{fmt(summary.tax_total)}</p>
                        <p class="small text-secondary mb-0">En compras del periodo</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Recepciones</p>
                    <h3 class="h5 mb-0">{receipts.total} en el periodo</h3>
                </div>
            </div>

            <div class="taguara-table-wrapper">
                <table class="taguara-table">
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Proveedor</th>
                            <th>Recibido</th>
                            <th class="text-center">Items</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-end">IVA</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each receipts.data as r}
                            <tr>
                                <td><div class="taguara-table-name">{r.document_number}</div></td>
                                <td class="text-secondary" style="font-size:.875rem">{r.supplier}</td>
                                <td class="text-secondary" style="font-size:.875rem">{r.received_at}</td>
                                <td class="text-center"><span class="badge text-bg-light border text-secondary">{r.items_count}</span></td>
                                <td class="text-end" style="font-size:.875rem">{fmt(r.subtotal)}</td>
                                <td class="text-end" style="font-size:.875rem">{fmt(r.tax_total)}</td>
                                <td class="text-end fw-semibold">{fmt(r.total)}</td>
                            </tr>
                        {:else}
                            <tr><td colspan="7"><div class="taguara-empty-state" style="min-height:120px"><ReceiptText size={28} /><p class="text-secondary small mb-0">Sin compras en el periodo seleccionado.</p></div></td></tr>
                        {/each}
                    </tbody>
                </table>
            </div>

            {#if receipts.links.length > 3}
                <nav class="taguara-pagination mt-3">
                    {#each receipts.links as link}
                        {#if link.url}<Link class={`btn btn-sm ${link.active ? 'btn-taguara' : 'btn-light border'}`} href={link.url}>{@html link.label}</Link>
                        {:else}<span class="btn btn-sm btn-light border disabled">{@html link.label}</span>{/if}
                    {/each}
                </nav>
            {/if}
        </section>
    </div>
</AppLayout>
