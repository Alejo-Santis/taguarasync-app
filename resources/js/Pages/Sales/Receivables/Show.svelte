<script>
    import { Link, useForm } from '@inertiajs/svelte';
    import { fade, fly } from 'svelte/transition';
    import { AlertTriangle, ArrowLeft, CircleDollarSign, HandCoins, Plus, ReceiptText, X } from '@lucide/svelte';
    import AppLayout from '../../../Layouts/AppLayout.svelte';

    let { auth, customer, movements, summary, bankAccounts } = $props();

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const fmt = (v) => money.format(v ?? 0);

    let drawerOpen = $state(false);

    const collectionForm = useForm(() => ({
        bank_account_id: bankAccounts.find((a) => a.is_default)?.id?.toString() ?? '',
        collection_date: new Date().toISOString().slice(0, 10),
        amount: '',
        reference: '',
        notes: '',
    }));

    $effect(() => {
        document.body.style.overflow = drawerOpen ? 'hidden' : '';
        return () => { document.body.style.overflow = ''; };
    });

    const handleKeydown = (e) => { if (e.key === 'Escape') closeDrawer(); };
    const openCollection = () => { drawerOpen = true; };
    const closeDrawer = () => {
        drawerOpen = false;
        collectionForm.reset();
        collectionForm.clearErrors();
    };
    const saveCollection = () => {
        collectionForm.post(`/sales/receivables/${customer.uuid}/collections`, { onSuccess: closeDrawer, preserveScroll: true });
    };
</script>

<svelte:window onkeydown={handleKeydown} />

<AppLayout title={customer.full_name} activeSection="receivables" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Cartera</p>
                <h2 class="h3 mb-2">{customer.full_name}</h2>
                <p class="text-secondary mb-0">
                    {customer.identification}
                    {#if customer.phone} · {customer.phone}{/if}
                    {#if customer.email} · {customer.email}{/if}
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap justify-content-end">
                <Link href="/sales/receivables" class="btn btn-light border d-inline-flex align-items-center gap-2">
                    <ArrowLeft size={17} />
                    Cartera
                </Link>
                <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="button" onclick={openCollection}>
                    <Plus size={17} />
                    Registrar cobro
                </button>
            </div>
        </section>

        <section class="taguara-action-grid">
            <article class="taguara-kpi-card">
                <span class="taguara-kpi-icon text-bg-danger"><ReceiptText size={20} /></span>
                <div>
                    <p class="taguara-kpi-label">Facturado a crédito</p>
                    <h3>{fmt(summary.total_invoiced)}</h3>
                </div>
            </article>
            <article class="taguara-kpi-card">
                <span class="taguara-kpi-icon text-bg-success"><HandCoins size={20} /></span>
                <div>
                    <p class="taguara-kpi-label">Cobrado</p>
                    <h3>{fmt(summary.total_collected)}</h3>
                </div>
            </article>
            <article class="taguara-kpi-card">
                <span class="taguara-kpi-icon {summary.balance > 0 ? 'text-bg-danger' : 'text-bg-success'}">
                    <CircleDollarSign size={20} />
                </span>
                <div>
                    <p class="taguara-kpi-label">Saldo por cobrar</p>
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
                            <th>Vencimiento</th>
                            <th>Detalle</th>
                            <th class="text-end">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each movements as mov}
                            <tr>
                                <td class="text-secondary">{mov.date}</td>
                                <td>
                                    <span class={`badge ${mov.type === 'invoice' ? 'text-bg-primary' : 'text-bg-success'}`}>{mov.label}</span>
                                </td>
                                <td>
                                    {#if mov.uuid && mov.type === 'invoice'}
                                        <Link href={`/sales/${mov.uuid}`} class="text-decoration-none small">{mov.document_number ?? '—'}</Link>
                                    {:else}
                                        <span class="small">{mov.reference ?? '—'}</span>
                                    {/if}
                                </td>
                                <td>
                                    {#if mov.due_date}
                                        <span class={`badge ${mov.is_overdue ? 'text-bg-danger' : 'text-bg-light border text-secondary'}`}>
                                            {#if mov.is_overdue}<AlertTriangle size={11} class="me-1" />{/if}
                                            {mov.due_date}
                                        </span>
                                    {:else}
                                        <span class="text-secondary">—</span>
                                    {/if}
                                </td>
                                <td class="text-secondary small">
                                    {#if mov.bank}{mov.bank}{/if}
                                    {#if mov.notes}<div style="font-size:.78rem">{mov.notes}</div>{/if}
                                </td>
                                <td class="text-end fw-semibold {mov.amount > 0 ? 'text-danger' : 'text-success'}">
                                    {mov.amount > 0 ? '+' : ''}{fmt(mov.amount)}
                                </td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="6">
                                    <div class="taguara-empty-state" style="min-height:120px">
                                        <ReceiptText size={28} />
                                        <p class="text-secondary small mb-0">Sin movimientos a crédito para este cliente.</p>
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
                        <p class="text-uppercase small fw-semibold text-success mb-1">Cobro</p>
                        <h2 class="h5 mb-0">{customer.full_name}</h2>
                    </div>
                    <button class="btn btn-light border taguara-icon-button flex-shrink-0" type="button" onclick={closeDrawer}><X size={17} /></button>
                </div>
            </div>
            <div class="taguara-drawer-body">
                <form id="collection-form" onsubmit={(e) => { e.preventDefault(); saveCollection(); }}>
                    <div class="vstack gap-3">
                        <div>
                            <label class="form-label" for="col-date">Fecha de cobro <span class="text-danger">*</span></label>
                            <input id="col-date" class="form-control" class:is-invalid={collectionForm.errors.collection_date} type="date" bind:value={collectionForm.collection_date} />
                            {#if collectionForm.errors.collection_date}<div class="invalid-feedback">{collectionForm.errors.collection_date}</div>{/if}
                        </div>
                        <div>
                            <label class="form-label" for="col-amount">Monto (COP) <span class="text-danger">*</span></label>
                            <input id="col-amount" class="form-control" class:is-invalid={collectionForm.errors.amount} type="number" min="1" step="1" bind:value={collectionForm.amount} placeholder="0" />
                            {#if collectionForm.errors.amount}<div class="invalid-feedback">{collectionForm.errors.amount}</div>{/if}
                            {#if summary.balance > 0}
                                <div class="form-text">Saldo por cobrar: <strong>{fmt(summary.balance)}</strong></div>
                            {/if}
                        </div>
                        <div>
                            <label class="form-label" for="col-bank">Cuenta bancaria</label>
                            <select id="col-bank" class="form-select" bind:value={collectionForm.bank_account_id}>
                                <option value="">Sin cuenta (efectivo u otro)</option>
                                {#each bankAccounts as account}
                                    <option value={account.id}>{account.bank_name} · {account.account_name}</option>
                                {/each}
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="col-ref">Referencia</label>
                            <input id="col-ref" class="form-control" class:is-invalid={collectionForm.errors.reference} type="text" bind:value={collectionForm.reference} placeholder="Número de transferencia, cheque..." />
                            {#if collectionForm.errors.reference}<div class="invalid-feedback">{collectionForm.errors.reference}</div>{/if}
                        </div>
                        <div>
                            <label class="form-label" for="col-notes">Notas</label>
                            <textarea id="col-notes" class="form-control" rows="3" bind:value={collectionForm.notes}></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="taguara-drawer-footer">
                <button class="btn btn-taguara w-100" type="submit" form="collection-form" disabled={collectionForm.processing}>
                    {collectionForm.processing ? 'Registrando...' : 'Registrar cobro'}
                </button>
            </div>
        </aside>
    {/if}
</AppLayout>
