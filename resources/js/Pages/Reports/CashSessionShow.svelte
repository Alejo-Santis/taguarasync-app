<script>
    import { Link } from '@inertiajs/svelte';
    import { ArrowLeft, CircleDollarSign, CreditCard, DollarSign, Printer, ReceiptText, ShieldAlert } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';
    import ReportsNav from '../../Components/Reports/ReportsNav.svelte';
    import QzPrinter from '../../Services/QzPrinter.js';

    let { auth, session, sales } = $props();

    const printerName = $derived(auth?.tenant?.printer_settings?.printer_name ?? null);
    const paperWidth  = $derived(auth?.tenant?.printer_settings?.paper_width ?? '80mm');
    const copies      = $derived(auth?.tenant?.printer_settings?.copies ?? 1);

    let zPrinting = $state(false);
    let zError    = $state('');

    async function printZReport() {
        if (!printerName) return;
        zPrinting = true;
        zError    = '';
        try {
            if (!QzPrinter.connected) await QzPrinter.connect();
            await QzPrinter.printCashSession(printerName, session, { paperWidth, copies });
        } catch (err) {
            zError = err?.message ?? 'Error al imprimir.';
        } finally {
            zPrinting = false;
        }
    }

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const fmt = (v) => money.format(v ?? 0);

    const differenceClass = (value) => {
        if (value === null || value === undefined) return 'text-secondary';
        if (value < 0) return 'text-danger fw-semibold';
        if (value > 0) return 'text-warning fw-semibold';
        return 'text-success fw-semibold';
    };
</script>

<AppLayout title="Detalle de caja" activeSection="reports" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Caja {session.register.code}</p>
                <h2 class="h3 mb-2">{session.register.name} · {session.cashier}</h2>
                <p class="text-secondary mb-0">Abierta {session.opened_at}{session.closed_at ? ` · Cerrada ${session.closed_at}` : ' · Turno abierto'}</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                {#if printerName}
                    <button
                        class="btn btn-light border d-inline-flex align-items-center gap-2"
                        type="button"
                        onclick={printZReport}
                        disabled={zPrinting}
                        title="Imprimir cierre de caja en térmica"
                    >
                        <Printer size={17} />
                        {zPrinting ? 'Imprimiendo...' : 'Imprimir cierre'}
                    </button>
                {/if}
                <Link class="btn btn-light border d-inline-flex align-items-center gap-2" href="/reports/cash-sessions">
                    <ArrowLeft size={18} />
                    Volver
                </Link>
            </div>
            {#if zError}
                <p class="text-danger small mt-1 mb-0">{zError}</p>
            {/if}
        </section>

        <ReportsNav active="cash-sessions" />

        <section class="row g-3">
            <div class="col-12 col-md-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-success"><DollarSign size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Efectivo</p>
                        <p class="h3 mb-1">{fmt(session.cash_sales_total)}</p>
                        <p class="small text-secondary mb-0">Impacta el cierre</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-primary"><CreditCard size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Tarjeta</p>
                        <p class="h3 mb-1">{fmt(session.card_sales_total)}</p>
                        <p class="small text-secondary mb-0">Referencia del turno</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-info"><CircleDollarSign size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Transferencia</p>
                        <p class="h3 mb-1">{fmt(session.transfer_sales_total)}</p>
                        <p class="small text-secondary mb-0">Referencia del turno</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-3">
                <article class="taguara-kpi-card">
                    <span class={`taguara-kpi-icon ${session.difference === 0 ? 'text-bg-secondary' : 'text-bg-warning'}`}><ShieldAlert size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Diferencia</p>
                        <p class={`h3 mb-1 ${differenceClass(session.difference)}`}>{session.difference === null ? '—' : fmt(session.difference)}</p>
                        <p class="small text-secondary mb-0">{session.closed_by ? `Cerro ${session.closed_by}` : 'Sin cierre registrado'}</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="row g-3">
            <div class="col-12 col-lg-5">
                <div class="taguara-panel h-100">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Arqueo</p>
                            <h3 class="h5 mb-0">Resumen de cierre</h3>
                        </div>
                    </div>
                    <div class="taguara-drawer-grid">
                        <span class="taguara-drawer-label">Saldo apertura</span>
                        <span>{fmt(session.opening_amount)}</span>
                        <span class="taguara-drawer-label">Ventas efectivo</span>
                        <span>{fmt(session.cash_sales_total)}</span>
                        <span class="taguara-drawer-label">Cierre esperado</span>
                        <span class="fw-semibold">{fmt(session.expected_closing)}</span>
                        <span class="taguara-drawer-label">Efectivo contado</span>
                        <span>{session.actual_closing_amount === null ? '—' : fmt(session.actual_closing_amount)}</span>
                        <span class="taguara-drawer-label">Diferencia</span>
                        <span class={differenceClass(session.difference)}>{session.difference === null ? '—' : fmt(session.difference)}</span>
                        <span class="taguara-drawer-label">Notas</span>
                        <span>{session.notes ?? '—'}</span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-7">
                <div class="taguara-panel h-100">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Ventas</p>
                            <h3 class="h5 mb-0">Distribucion por metodo</h3>
                        </div>
                        <ReceiptText class="text-secondary" size={20} />
                    </div>
                    <div class="taguara-drawer-grid">
                        <span class="taguara-drawer-label">Ventas del turno</span>
                        <span>{session.sales_count}</span>
                        <span class="taguara-drawer-label">Total vendido</span>
                        <span class="fw-semibold">{fmt(session.sales_total)}</span>
                        <span class="taguara-drawer-label">Efectivo</span>
                        <span>{fmt(session.cash_sales_total)}</span>
                        <span class="taguara-drawer-label">Tarjeta</span>
                        <span>{fmt(session.card_sales_total)}</span>
                        <span class="taguara-drawer-label">Transferencia</span>
                        <span>{fmt(session.transfer_sales_total)}</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Documentos</p>
                    <h3 class="h5 mb-0">Ventas asociadas al turno</h3>
                </div>
                <span class="badge text-bg-light border text-secondary">{sales.total} ventas</span>
            </div>

            <div class="taguara-table-wrapper">
                <table class="taguara-table">
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Metodo</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-end">IVA</th>
                            <th class="text-end">Total</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each sales.data as sale}
                            <tr>
                                <td class="fw-semibold">{sale.document_number}</td>
                                <td>{sale.customer}</td>
                                <td class="small text-secondary">{sale.created_at}</td>
                                <td>{sale.payment_method}</td>
                                <td class="text-end">{fmt(sale.subtotal)}</td>
                                <td class="text-end">{fmt(sale.tax_total)}</td>
                                <td class="text-end fw-semibold">{fmt(sale.total)}</td>
                                <td><span class="badge text-bg-light border text-secondary">{sale.status}</span></td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="8">
                                    <div class="taguara-empty-state" style="min-height:120px">
                                        <ReceiptText size={28} />
                                        <p class="text-secondary small mb-0">Este turno no tiene ventas asociadas.</p>
                                    </div>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>

            {#if sales.links.length > 3}
                <nav class="taguara-pagination mt-3">
                    {#each sales.links as link}
                        {#if link.url}<Link class={`btn btn-sm ${link.active ? 'btn-taguara' : 'btn-light border'}`} href={link.url}>{@html link.label}</Link>
                        {:else}<span class="btn btn-sm btn-light border disabled">{@html link.label}</span>{/if}
                    {/each}
                </nav>
            {/if}
        </section>
    </div>
</AppLayout>
