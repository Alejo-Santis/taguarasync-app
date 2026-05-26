<script>
    import { router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import { BookOpenCheck, Calculator, Download, FileText, Landmark, Printer, RotateCcw, Scale } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';
    import ReportsNav from '../../Components/Reports/ReportsNav.svelte';

    let { auth, filters, summary, tax_breakdown, documents } = $props();

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const fmt = (value) => money.format(value ?? 0);

    let form = $state(untrack(() => ({ from: filters.from, to: filters.to })));

    const submit = (event) => {
        event.preventDefault();
        router.get('/reports/fiscal', form, { preserveState: true, replace: true });
    };

    const resetToMonth = () => {
        const now = new Date();
        form.from = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
        form.to = now.toISOString().slice(0, 10);
        router.get('/reports/fiscal', form, { preserveState: true, replace: true });
    };

    const printReport = () => window.print();

    const documentClass = (impact) => {
        if (impact === 'negative') return 'text-danger';
        if (impact === 'purchase') return 'text-primary';
        return 'text-success';
    };
</script>

<AppLayout title="Libro fiscal" activeSection="reports" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band taguara-print-hide">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Reportes</p>
                <h2 class="h3 mb-2">Libro fiscal e IVA</h2>
                <p class="text-secondary mb-0">Ventas FE, notas credito, compras recibidas e impuesto neto del periodo.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-light border taguara-icon-button" type="button" aria-label="Imprimir reporte" onclick={printReport}>
                    <Printer size={18} />
                </button>
                <a class="btn btn-light border taguara-icon-button" href={`/reports/fiscal/export?from=${form.from}&to=${form.to}`} aria-label="Exportar CSV">
                    <Download size={18} />
                </a>
                <BookOpenCheck class="text-secondary" size={22} />
            </div>
        </section>

        <div class="taguara-print-hide">
            <ReportsNav active="fiscal" />
        </div>

        <section class="taguara-panel taguara-print-hide">
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
                <button class="btn btn-light border taguara-icon-button" type="button" aria-label="Mes actual" onclick={resetToMonth}>
                    <RotateCcw size={17} />
                </button>
            </form>
        </section>

        <section class="taguara-panel d-none taguara-print-block">
            <div class="d-flex align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Taguara Sync</p>
                    <h1 class="h4 mb-1">Libro fiscal e IVA</h1>
                    <p class="text-secondary mb-0">Periodo {filters.from} a {filters.to}</p>
                </div>
                <BookOpenCheck size={28} class="text-secondary" />
            </div>
        </section>

        <section class="row g-3">
            <div class="col-12 col-md-6 col-xl-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-success"><Calculator size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">IVA generado neto</p>
                        <p class="h3 mb-1">{fmt(summary.vat.generated)}</p>
                        <p class="small text-secondary mb-0">Ventas menos notas credito</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-primary"><Landmark size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">IVA descontable</p>
                        <p class="h3 mb-1">{fmt(summary.vat.discounted)}</p>
                        <p class="small text-secondary mb-0">{summary.purchases.count} compras recibidas</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-warning"><Scale size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">IVA por pagar</p>
                        <p class="h3 mb-1">{fmt(summary.vat.payable)}</p>
                        <p class="small text-secondary mb-0">Saldo a cargo estimado</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-info"><Download size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Saldo a favor</p>
                        <p class="h3 mb-1">{fmt(summary.vat.favorable_balance)}</p>
                        <p class="small text-secondary mb-0">Cuando compras superan IVA generado</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="row g-3">
            <div class="col-12 col-xl-4">
                <div class="taguara-panel h-100">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Resumen</p>
                            <h3 class="h5 mb-0">Movimiento del periodo</h3>
                        </div>
                        <FileText class="text-secondary" size={20} />
                    </div>
                    <div class="vstack gap-3">
                        <div class="taguara-fiscal-row">
                            <span>Ventas facturadas</span>
                            <strong>{fmt(summary.sales.total)}</strong>
                        </div>
                        <div class="taguara-fiscal-row">
                            <span>Notas credito</span>
                            <strong class="text-danger">{fmt(-summary.credit_notes.total)}</strong>
                        </div>
                        <div class="taguara-fiscal-row">
                            <span>Venta neta</span>
                            <strong>{fmt(summary.net_sales.total)}</strong>
                        </div>
                        <div class="taguara-fiscal-row">
                            <span>Compras recibidas</span>
                            <strong>{fmt(summary.purchases.total)}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-8">
                <div class="taguara-panel h-100">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">IVA</p>
                            <h3 class="h5 mb-0">Desglose por tarifa</h3>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 taguara-table">
                            <thead>
                                <tr>
                                    <th>Tarifa</th>
                                    <th class="text-end">Base ventas</th>
                                    <th class="text-end">IVA ventas</th>
                                    <th class="text-end">IVA NC</th>
                                    <th class="text-end">IVA compras</th>
                                    <th class="text-end">IVA neto</th>
                                </tr>
                            </thead>
                            <tbody>
                                {#if tax_breakdown.length === 0}
                                    <tr>
                                        <td colspan="6" class="text-center text-secondary py-4">Sin movimientos con impuesto en el periodo.</td>
                                    </tr>
                                {:else}
                                    {#each tax_breakdown as row}
                                        <tr>
                                            <td class="fw-semibold">{Number(row.rate).toFixed(2)}%</td>
                                            <td class="text-end">{fmt(row.sales_subtotal)}</td>
                                            <td class="text-end">{fmt(row.sales_tax)}</td>
                                            <td class="text-end text-danger">{fmt(row.credit_note_tax)}</td>
                                            <td class="text-end text-primary">{fmt(row.purchase_tax)}</td>
                                            <td class="text-end fw-semibold">{fmt(row.net_generated_tax)}</td>
                                        </tr>
                                    {/each}
                                {/if}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Libro</p>
                    <h3 class="h5 mb-0">Documentos del periodo</h3>
                </div>
                <span class="badge text-bg-light border">{documents.length} registros</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0 taguara-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Documento</th>
                            <th>Estado</th>
                            <th class="text-end">Base</th>
                            <th class="text-end">IVA</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#if documents.length === 0}
                            <tr>
                                <td colspan="7" class="text-center text-secondary py-4">No hay documentos para este periodo.</td>
                            </tr>
                        {:else}
                            {#each documents as document}
                                <tr>
                                    <td class="text-secondary" style="font-size:.85rem">{document.date}</td>
                                    <td><span class={`fw-semibold ${documentClass(document.impact)}`}>{document.type}</span></td>
                                    <td class="fw-semibold">{document.document_number}</td>
                                    <td><span class="badge text-bg-light border">{document.fe_status}</span></td>
                                    <td class="text-end">{fmt(document.subtotal)}</td>
                                    <td class="text-end">{fmt(document.tax_total)}</td>
                                    <td class="text-end fw-semibold">{fmt(document.total)}</td>
                                </tr>
                            {/each}
                        {/if}
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</AppLayout>
