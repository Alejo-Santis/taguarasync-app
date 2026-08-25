<script>
    import { Link } from '@inertiajs/svelte';
    import { ArrowRight, CheckCircle2, CircleDollarSign, CreditCard, DollarSign, Printer, ReceiptText, ShieldAlert } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';
    import QzPrinter from '../../Services/QzPrinter.js';

    let { auth, session } = $props();

    const printerName = $derived(auth?.tenant?.printer_settings?.printer_name ?? null);
    const paperWidth  = $derived(auth?.tenant?.printer_settings?.paper_width ?? '80mm');
    const copies      = $derived(auth?.tenant?.printer_settings?.copies ?? 1);

    let zPrinting = $state(false);
    let zError    = $state('');

    async function printThermal() {
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

    const printBrowser = () => window.print();

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const fmt = (v) => money.format(v ?? 0);

    const differenceClass = (value) => {
        if (value === null || value === undefined) return 'text-secondary';
        if (value < 0) return 'text-danger fw-semibold';
        if (value > 0) return 'text-warning fw-semibold';
        return 'text-success fw-semibold';
    };
</script>

<AppLayout title="Turno cerrado" activeSection="pos" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band taguara-print-hide">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2 d-flex align-items-center gap-2">
                    <CheckCircle2 size={16} class="text-success" />
                    Turno cerrado
                </p>
                <h2 class="h3 mb-2">{session.register.name} · {session.cashier}</h2>
                <p class="text-secondary mb-0">Abierta {session.opened_at} · Cerrada {session.closed_at}</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                {#if printerName}
                    <button
                        class="btn btn-light border d-inline-flex align-items-center gap-2"
                        type="button"
                        onclick={printThermal}
                        disabled={zPrinting}
                        title="Imprimir cierre en impresora térmica"
                    >
                        <Printer size={17} />
                        {zPrinting ? 'Imprimiendo...' : 'Imprimir (térmica)'}
                    </button>
                {/if}
                <button
                    class="btn btn-light border d-inline-flex align-items-center gap-2"
                    type="button"
                    onclick={printBrowser}
                    title="Imprimir en impresora normal"
                >
                    <Printer size={17} />
                    Imprimir
                </button>
                <Link class="btn btn-taguara d-inline-flex align-items-center gap-2" href="/pos">
                    Volver al POS
                    <ArrowRight size={18} />
                </Link>
            </div>
            {#if zError}
                <p class="text-danger small mt-1 mb-0">{zError}</p>
            {/if}
        </section>

        <section class="taguara-panel d-none taguara-print-block">
            <div class="d-flex align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Taguara Sync</p>
                    <h1 class="h4 mb-1">Cierre de caja</h1>
                    <p class="text-secondary mb-0">{session.register.name} · {session.cashier}</p>
                    <p class="text-secondary mb-0">Abierta {session.opened_at} · Cerrada {session.closed_at}</p>
                </div>
                <ReceiptText size={28} class="text-secondary" />
            </div>
        </section>

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
                        <p class="small text-secondary mb-0">{session.closed_by ? `Cerró ${session.closed_by}` : ''}</p>
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
                            <h3 class="h5 mb-0">Distribución por método</h3>
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
    </div>
</AppLayout>
