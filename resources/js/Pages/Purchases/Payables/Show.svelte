<script>
    import { Link, useForm } from '@inertiajs/svelte';
    import { fade, fly } from 'svelte/transition';
    import { ArrowLeft, CircleDollarSign, Download, HandCoins, Plus, ReceiptText, RotateCcw, X } from '@lucide/svelte';
    import AppLayout from '../../../Layouts/AppLayout.svelte';

    let { auth, supplier, movements, summary, bankAccounts } = $props();

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const fmt = (value) => money.format(value ?? 0);

    let drawerOpen = $state(false);

    const paymentForm = useForm(() => ({
        bank_account_id: bankAccounts.find((a) => a.is_default)?.id?.toString() ?? '',
        payment_date: new Date().toISOString().slice(0, 10),
        amount: '',
        reference: '',
        notes: '',
    }));

    $effect(() => {
        document.body.style.overflow = drawerOpen ? 'hidden' : '';
        return () => { document.body.style.overflow = ''; };
    });

    const handleKeydown = (event) => { if (event.key === 'Escape') closeDrawer(); };

    const openPayment = () => { drawerOpen = true; };
    const closeDrawer = () => {
        drawerOpen = false;
        paymentForm.reset();
        paymentForm.clearErrors();
    };

    const savePayment = () => {
        paymentForm.post(`/purchases/payables/${supplier.uuid}/payments`, { onSuccess: closeDrawer, preserveScroll: true });
    };

    const typeIcon = (type) => {
        if (type === 'payment') { return HandCoins; }
        if (type === 'return') { return RotateCcw; }
        return ReceiptText;
    };

    const typeClass = (type) => {
        if (type === 'payment') { return 'text-bg-success'; }
        if (type === 'return') { return 'text-bg-warning'; }
        return 'text-bg-primary';
    };
</script>

<svelte:window onkeydown={handleKeydown} />

<AppLayout title={supplier.name} activeSection="payables" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Cuentas por pagar</p>
                <h2 class="h3 mb-2">{supplier.name}</h2>
                <p class="text-secondary mb-0">
                    {#if supplier.nit}NIT {supplier.nit} · {/if}
                    {supplier.contact_name ?? supplier.contact_email ?? ''}
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap justify-content-end">
                <Link href="/purchases/payables" class="btn btn-light border d-inline-flex align-items-center gap-2">
                    <ArrowLeft size={17} />
                    Proveedores
                </Link>
                <a href={`/reports/suppliers/${supplier.uuid}/statement`} class="btn btn-light border d-inline-flex align-items-center gap-2" download>
                    <Download size={17} />
                    Exportar CSV
                </a>
                <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="button" onclick={openPayment}>
                    <Plus size={17} />
                    Registrar pago
                </button>
            </div>
        </section>

        <section class="taguara-action-grid">
            <article class="taguara-kpi-card">
                <span class="taguara-kpi-icon text-bg-danger"><ReceiptText size={20} /></span>
                <div>
                    <p class="taguara-kpi-label">Total comprado</p>
                    <h3>{fmt(summary.total_purchased)}</h3>
                </div>
            </article>
            <article class="taguara-kpi-card">
                <span class="taguara-kpi-icon text-bg-success"><HandCoins size={20} /></span>
                <div>
                    <p class="taguara-kpi-label">Total pagado</p>
                    <h3>{fmt(summary.total_paid)}</h3>
                </div>
            </article>
            <article class="taguara-kpi-card">
                <span class="taguara-kpi-icon text-bg-warning"><RotateCcw size={20} /></span>
                <div>
                    <p class="taguara-kpi-label">Devuelto</p>
                    <h3>{fmt(summary.total_returned)}</h3>
                </div>
            </article>
            <article class="taguara-kpi-card">
                <span class="taguara-kpi-icon {summary.balance > 0 ? 'text-bg-danger' : 'text-bg-success'}">
                    <CircleDollarSign size={20} />
                </span>
                <div>
                    <p class="taguara-kpi-label">Saldo pendiente</p>
                    <h3 class={summary.balance > 0 ? 'text-danger' : 'text-success'}>{fmt(summary.balance)}</h3>
                </div>
            </article>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Movimientos</p>
                    <h3 class="h5 mb-0">Estado de cuenta</h3>
                </div>
            </div>

            <div class="taguara-table-wrapper mt-3">
                <table class="taguara-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Referencia</th>
                            <th>Detalle</th>
                            <th class="text-end">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each movements as mov}
                            {@const Icon = typeIcon(mov.type)}
                            <tr>
                                <td class="text-secondary">{mov.date}</td>
                                <td>
                                    <span class={`badge ${typeClass(mov.type)}`}>{mov.label}</span>
                                </td>
                                <td>
                                    {#if mov.uuid && mov.type === 'receipt'}
                                        <Link href={`/purchases/${mov.uuid}`} class="text-decoration-none small">{mov.reference ?? '—'}</Link>
                                    {:else}
                                        <span class="small">{mov.reference ?? '—'}</span>
                                    {/if}
                                </td>
                                <td class="text-secondary small">
                                    {#if mov.bank}{mov.bank}{/if}
                                    {#if mov.notes}<div class="text-secondary" style="font-size:0.78rem">{mov.notes}</div>{/if}
                                </td>
                                <td class="text-end fw-semibold {mov.amount > 0 ? 'text-danger' : 'text-success'}">
                                    {mov.amount > 0 ? '+' : ''}{fmt(mov.amount)}
                                </td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="5">
                                    <div class="taguara-empty-state" style="min-height:120px">
                                        <ReceiptText size={28} />
                                        <p class="text-secondary small mb-0">No hay movimientos con este proveedor.</p>
                                    </div>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    {#if drawerOpen}
        <div class="taguara-drawer-backdrop" transition:fade={{ duration: 150 }} onclick={closeDrawer} role="presentation"></div>
        <aside class="taguara-drawer" transition:fly={{ x: 480, duration: 220 }}>
            <div class="taguara-drawer-header">
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <div>
                        <p class="text-uppercase small fw-semibold text-success mb-1">Pago a proveedor</p>
                        <h2 class="h5 mb-0">{supplier.name}</h2>
                    </div>
                    <button class="btn btn-light border taguara-icon-button flex-shrink-0" type="button" aria-label="Cerrar panel" onclick={closeDrawer}><X size={17} /></button>
                </div>
            </div>
            <div class="taguara-drawer-body">
                <form id="payment-form" onsubmit={(e) => { e.preventDefault(); savePayment(); }}>
                    <div class="vstack gap-3">
                        <div>
                            <label class="form-label" for="payment-date">Fecha de pago <span class="text-danger">*</span></label>
                            <input id="payment-date" class="form-control" class:is-invalid={paymentForm.errors.payment_date} type="date" bind:value={paymentForm.payment_date} />
                            {#if paymentForm.errors.payment_date}<div class="invalid-feedback">{paymentForm.errors.payment_date}</div>{/if}
                        </div>
                        <div>
                            <label class="form-label" for="payment-amount">Monto (COP) <span class="text-danger">*</span></label>
                            <input id="payment-amount" class="form-control" class:is-invalid={paymentForm.errors.amount} type="number" min="1" step="1" bind:value={paymentForm.amount} placeholder="0" />
                            {#if paymentForm.errors.amount}<div class="invalid-feedback">{paymentForm.errors.amount}</div>{/if}
                            {#if summary.balance > 0}
                                <div class="form-text">Saldo pendiente: <strong>{fmt(summary.balance)}</strong></div>
                            {/if}
                        </div>
                        <div>
                            <label class="form-label" for="payment-bank">Cuenta bancaria</label>
                            <select id="payment-bank" class="form-select" bind:value={paymentForm.bank_account_id}>
                                <option value="">Sin cuenta (efectivo u otro)</option>
                                {#each bankAccounts as account}
                                    <option value={account.id}>{account.bank_name} · {account.account_name}</option>
                                {/each}
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="payment-reference">Referencia</label>
                            <input id="payment-reference" class="form-control" class:is-invalid={paymentForm.errors.reference} type="text" bind:value={paymentForm.reference} placeholder="Número de transferencia, cheque..." />
                            {#if paymentForm.errors.reference}<div class="invalid-feedback">{paymentForm.errors.reference}</div>{/if}
                        </div>
                        <div>
                            <label class="form-label" for="payment-notes">Notas</label>
                            <textarea id="payment-notes" class="form-control" rows="3" bind:value={paymentForm.notes} placeholder="Observaciones opcionales"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="taguara-drawer-footer">
                <button class="btn btn-taguara w-100" type="submit" form="payment-form" disabled={paymentForm.processing}>
                    {paymentForm.processing ? 'Registrando...' : 'Registrar pago'}
                </button>
            </div>
        </aside>
    {/if}
</AppLayout>
