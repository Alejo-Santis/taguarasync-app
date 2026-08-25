<script>
    import { Link, router } from '@inertiajs/svelte';
    import {
        AlertCircle,
        ArrowLeft,
        Ban,
        CalendarClock,
        CheckCircle2,
        CircleDollarSign,
        CreditCard,
        Paperclip,
        Printer,
        PrinterCheck,
        ReceiptText,
        ShieldCheck,
        ShieldOff,
        ShieldQuestion,
        User,
        X,
    } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';
    import QzPrinter from '../../Services/QzPrinter.js';

    let { auth, sale } = $props();

    const printerName  = $derived(auth?.tenant?.printer_settings?.printer_name ?? null);
    const paperWidth   = $derived(auth?.tenant?.printer_settings?.paper_width ?? '80mm');
    const copies       = $derived(auth?.tenant?.printer_settings?.copies ?? 1);

    let thermalPrinting = $state(false);
    let thermalError    = $state('');

    const thermalSaleData = $derived({
        document_number: sale.document_number,
        total:           sale.total,
        subtotal:        sale.subtotal,
        tax_total:       sale.tax_total,
        discount_total:  0,
        payment_form:    sale.payment_form_value,
        payment_method:  sale.payment_method,
        change_amount:   sale.change_amount,
        payment_due_date: sale.payment_due_date,
        customer_name:   sale.customer?.full_name ?? null,
        cashier_name:    sale.cashier,
        created_at:      sale.created_at,
        status:          sale.status?.value,
        items:           sale.items.map(i => ({
            description:    i.description,
            quantity:       i.quantity,
            unit_price:     i.unit_price,
            line_total:     i.line_total,
        })),
        fe_cufe:   sale.fe?.cufe ?? null,
        fe_qr_url: sale.fe?.qr_code?.startsWith?.('http') ? sale.fe.qr_code : null,
    });

    async function printThermal() {
        if (!printerName) return;
        thermalPrinting = true;
        thermalError    = '';
        try {
            if (!QzPrinter.connected) await QzPrinter.connect();
            await QzPrinter.printReceipt(printerName, thermalSaleData, { paperWidth, copies });
        } catch (err) {
            thermalError = err?.message ?? 'Error al imprimir.';
        } finally {
            thermalPrinting = false;
        }
    }

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const fmt = (v) => money.format(v ?? 0);
    const pct = (v) => `${Number(v ?? 0).toFixed(0)}%`;

    const isCredit = $derived(sale.payment_form_value === '2');

    const statusClass = (v) => {
        if (v === 'completed') return 'text-bg-success';
        if (v === 'voided') return 'text-bg-danger';
        return 'text-bg-secondary';
    };

    const feStatusClass = (v) => {
        if (v === 'accepted') return 'text-bg-success';
        if (v === 'rejected') return 'text-bg-danger';
        if (v === 'contingency' || v === 'pending') return 'text-bg-warning text-dark';
        if (v === 'not_applicable') return 'text-bg-light border text-secondary';
        return 'text-bg-secondary';
    };

    const FeIcon = (v) => {
        if (v === 'accepted') return ShieldCheck;
        if (v === 'rejected') return ShieldOff;
        if (v === 'pending' || v === 'contingency') return AlertCircle;
        if (v === 'not_applicable') return ShieldOff;
        return ShieldQuestion;
    };

    let confirmVoid = $state(false);

    const doVoid = () => {
        router.post(`/sales/${sale.uuid}/void`, {}, {
            onSuccess: () => { confirmVoid = false; },
            preserveScroll: true,
        });
    };

    const retryFe = () => {
        router.post(`/sales/${sale.uuid}/retry-fe`, {}, { preserveScroll: true });
    };
</script>

<AppLayout title={sale.document_number} activeSection="sales" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Ventas</p>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h2 class="h3 mb-0">{sale.document_number}</h2>
                    <span class={`badge ${statusClass(sale.status.value)}`}>{sale.status.label}</span>
                    {#if isCredit}
                        <span class="badge text-bg-info">Crédito</span>
                    {/if}
                    {#if sale.fe?.status}
                        {@const Icon = FeIcon(sale.fe.status)}
                        <span class={`badge ${feStatusClass(sale.fe.status)}`}>
                            <Icon size={11} style="margin-right:3px" />
                            {sale.fe.status_label}
                        </span>
                    {/if}
                </div>
                <p class="text-secondary mb-0">{sale.created_at} · {sale.cashier} · {sale.register}</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <Link class="btn btn-light border d-inline-flex align-items-center gap-2" href="/sales">
                    <ArrowLeft size={17} />
                    Ventas
                </Link>
                <a
                    class="btn btn-light border d-inline-flex align-items-center gap-2"
                    href={`/sales/${sale.uuid}/receipt`}
                    target="_blank"
                    rel="noopener"
                >
                    <PrinterCheck size={17} />
                    Imprimir recibo
                </a>
                {#if printerName}
                    <button
                        class="btn btn-light border d-inline-flex align-items-center gap-2"
                        type="button"
                        onclick={printThermal}
                        disabled={thermalPrinting}
                        title="Reimprimir en impresora térmica"
                    >
                        <Printer size={17} />
                        {thermalPrinting ? 'Imprimiendo...' : 'Térmica'}
                    </button>
                {/if}
                {#if thermalError}
                    <span class="text-danger small align-self-center">{thermalError}</span>
                {/if}
                {#if sale.fe?.status === 'pending' || sale.fe?.status === 'rejected' || sale.fe?.status === 'contingency'}
                    <button class="btn btn-outline-warning d-inline-flex align-items-center gap-2" type="button" onclick={retryFe}>
                        <ShieldCheck size={17} />
                        Reintentar FE
                    </button>
                {/if}
                {#if sale.status.value === 'completed'}
                    <Link
                        class="btn btn-outline-secondary d-inline-flex align-items-center gap-2"
                        href={`/sales/${sale.uuid}/credit-notes/create`}
                    >
                        <ShieldOff size={17} />
                        Nota crédito
                    </Link>
                    <button class="btn btn-outline-danger d-inline-flex align-items-center gap-2" type="button" onclick={() => confirmVoid = true}>
                        <Ban size={17} />
                        Anular
                    </button>
                {/if}
            </div>
        </section>

        <div class="row g-3">
            <!-- Left col: items -->
            <div class="col-12 col-xl-8">
                <div class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Detalle</p>
                            <h3 class="h5 mb-0">{sale.items.length} {sale.items.length === 1 ? 'producto' : 'productos'}</h3>
                        </div>
                        <ReceiptText class="text-success" size={22} />
                    </div>

                    <div class="taguara-table-wrapper">
                        <table class="taguara-table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th class="text-center">Cant.</th>
                                    <th class="text-end">Precio unit.</th>
                                    <th class="text-end">Dto.</th>
                                    <th class="text-end">IVA</th>
                                    <th class="text-end">Total línea</th>
                                </tr>
                            </thead>
                            <tbody>
                                {#each sale.items as item}
                                    <tr>
                                        <td>
                                            <div class="taguara-table-name">{item.description}</div>
                                        </td>
                                        <td class="text-center">{item.quantity}</td>
                                        <td class="text-end">{fmt(item.unit_price)}</td>
                                        <td class="text-end text-success">
                                            {#if item.discount_rate > 0}
                                                -{pct(item.discount_rate)}
                                            {:else}
                                                —
                                            {/if}
                                        </td>
                                        <td class="text-end taguara-table-sub">{pct(item.tax_rate)}</td>
                                        <td class="text-end fw-semibold">{fmt(item.line_total)}</td>
                                    </tr>
                                {/each}
                            </tbody>
                            <tfoot>
                                <tr class="border-top">
                                    <td colspan="5" class="text-end text-secondary small">Subtotal</td>
                                    <td class="text-end">{fmt(sale.subtotal)}</td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="text-end text-secondary small">IVA</td>
                                    <td class="text-end">{fmt(sale.tax_total)}</td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="text-end fw-bold">Total</td>
                                    <td class="text-end fw-bold">{fmt(sale.total)}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right col: info panels -->
            <div class="col-12 col-xl-4 vstack gap-3">

                <!-- Cliente -->
                {#if sale.customer}
                    <div class="taguara-panel">
                        <div class="taguara-panel-header">
                            <div>
                                <p class="text-uppercase small fw-semibold text-success mb-1">Cliente</p>
                                <h3 class="h5 mb-0">{sale.customer.full_name}</h3>
                            </div>
                            <User class="text-success" size={20} />
                        </div>
                        <div class="taguara-drawer-grid">
                            <span class="taguara-drawer-label">Identificación</span>
                            <span>{sale.customer.identification}</span>
                        </div>
                    </div>
                {/if}

                <!-- Pago -->
                <div class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Pago</p>
                            <h3 class="h5 mb-0">{isCredit ? 'Venta a crédito' : sale.payment_form}</h3>
                        </div>
                        {#if isCredit}
                            <CreditCard class="text-info" size={20} />
                        {:else}
                            <CircleDollarSign class="text-success" size={20} />
                        {/if}
                    </div>
                    <div class="taguara-drawer-grid">
                        <span class="taguara-drawer-label">Forma</span>
                        <span>{sale.payment_form}</span>
                        {#if !isCredit}
                            <span class="taguara-drawer-label">Método</span>
                            <span>{sale.payment_method}</span>
                        {/if}
                        {#if isCredit && sale.payment_due_date}
                            <span class="taguara-drawer-label">Vence</span>
                            <span class="fw-semibold text-warning">{sale.payment_due_date}</span>
                        {/if}
                        {#if sale.amount_tendered}
                            <span class="taguara-drawer-label">Recibido</span>
                            <span>{fmt(sale.amount_tendered)}</span>
                            <span class="taguara-drawer-label">Cambio</span>
                            <span>{fmt(sale.change_amount)}</span>
                        {/if}
                        <span class="taguara-drawer-label">Caja</span>
                        <span>{sale.register}</span>
                        <span class="taguara-drawer-label">Cajero</span>
                        <span>{sale.cashier}</span>
                    </div>

                    <!-- Desglose de pagos múltiples -->
                    {#if sale.payments.length > 0 && !isCredit}
                        <div class="mt-3">
                            <p class="small fw-semibold text-secondary mb-2">Pagos registrados</p>
                            {#each sale.payments as payment}
                                <div class="d-flex justify-content-between align-items-center py-1 border-bottom" style="font-size:.85rem">
                                    <span>{payment.method}</span>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-semibold">{fmt(payment.amount)}</span>
                                        {#if payment.reference}
                                            <span class="taguara-table-sub">Ref: {payment.reference}</span>
                                        {/if}
                                        {#if payment.has_attachment}
                                            <a href={payment.attachment_url} target="_blank" rel="noreferrer" class="btn btn-sm btn-light border p-1" aria-label="Ver comprobante">
                                                <Paperclip size={13} />
                                            </a>
                                        {/if}
                                    </div>
                                </div>
                            {/each}
                        </div>
                    {/if}
                </div>

                <!-- Facturación electrónica -->
                {#if sale.fe?.status}
                    {@const FeIconEl = FeIcon(sale.fe.status)}
                    <div class="taguara-panel">
                        <div class="taguara-panel-header">
                            <div>
                                <p class="text-uppercase small fw-semibold text-success mb-1">Facturación electrónica</p>
                                <div class="d-flex align-items-center gap-2 h5 mb-0">
                                    <FeIconEl size={16} />
                                    {sale.fe.status_label}
                                </div>
                            </div>
                            <span class={`badge ${feStatusClass(sale.fe.status)}`}>{sale.fe.status_label}</span>
                        </div>
                        {#if sale.fe.cufe || sale.fe.sent_at || sale.fe.accepted_at}
                            <div class="taguara-drawer-grid">
                                {#if sale.fe.cufe}
                                    <span class="taguara-drawer-label">CUFE</span>
                                    <span class="font-monospace text-truncate" style="font-size:.7rem" title={sale.fe.cufe}>
                                        {sale.fe.cufe.slice(0, 24)}…
                                    </span>
                                {/if}
                                {#if sale.fe.sent_at}
                                    <span class="taguara-drawer-label">Enviada</span>
                                    <span>{sale.fe.sent_at}</span>
                                {/if}
                                {#if sale.fe.accepted_at}
                                    <span class="taguara-drawer-label">Aceptada</span>
                                    <span class="text-success fw-semibold">{sale.fe.accepted_at}</span>
                                {/if}
                            </div>
                        {/if}
                        {#if sale.fe.qr_code}
                            <div class="text-center mt-3">
                                <img src={sale.fe.qr_code} alt="QR DIAN" style="width:120px;height:120px" />
                            </div>
                        {/if}
                        {#if sale.fe.error_message}
                            <div class="alert alert-danger py-2 px-3 small mt-3 mb-0 d-flex gap-2">
                                <AlertCircle size={14} class="flex-shrink-0 mt-1" />
                                {sale.fe.error_message}
                            </div>
                        {/if}
                    </div>
                {/if}

            </div>
        </div>
    </div>

    <!-- Modal confirmación anulación -->
    {#if confirmVoid}
        <div class="taguara-modal" role="dialog" aria-modal="true">
            <div class="taguara-modal-inner" style="max-width:400px">
                <div class="taguara-modal-header">
                    <div class="d-flex align-items-center gap-2">
                        <AlertCircle size={20} class="text-danger" />
                        <h2 class="h5 mb-0">Anular venta</h2>
                    </div>
                    <button class="btn btn-light border taguara-icon-button" type="button" onclick={() => confirmVoid = false}><X size={17} /></button>
                </div>
                <div class="taguara-modal-body">
                    <p class="mb-0">¿Confirmas la anulación de <strong>{sale.document_number}</strong>?
                    El inventario será restaurado automáticamente.</p>
                </div>
                <div class="taguara-modal-footer">
                    <button class="btn btn-light border flex-fill" type="button" onclick={() => confirmVoid = false}>Cancelar</button>
                    <button class="btn btn-danger flex-fill" type="button" onclick={doVoid}>Anular venta</button>
                </div>
            </div>
        </div>
        <div class="taguara-drawer-backdrop" role="presentation" onclick={() => confirmVoid = false}></div>
    {/if}
</AppLayout>
