<script>
    import { Link, router } from '@inertiajs/svelte';
    import { ArrowLeft, ClipboardList, Send, X } from '@lucide/svelte';
    import AppLayout from '../../../Layouts/AppLayout.svelte';

    let { auth, order } = $props();

    let isSending = $state(false);
    let isCancelling = $state(false);

    const money = new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    });

    const statusClass = (status) => {
        if (status === 'received') return 'text-bg-success';
        if (status === 'partially_received') return 'text-bg-info';
        if (status === 'sent') return 'text-bg-primary';
        if (status === 'cancelled') return 'text-bg-danger';
        return 'text-bg-secondary';
    };

    const sendOrder = () => {
        isSending = true;
        router.post(`/purchases/orders/${order.uuid}/send`, {}, {
            preserveScroll: true,
            onFinish: () => { isSending = false; },
        });
    };

    const cancelOrder = () => {
        if (!confirm('¿Seguro que deseas cancelar esta orden?')) { return; }
        isCancelling = true;
        router.post(`/purchases/orders/${order.uuid}/cancel`, {}, {
            preserveScroll: true,
            onFinish: () => { isCancelling = false; },
        });
    };
</script>

<AppLayout title="Orden {order.order_number}" activeSection="purchases" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <Link class="btn btn-sm btn-light border d-inline-flex align-items-center gap-1" href="/purchases/orders">
                        <ArrowLeft size={15} />
                        Órdenes
                    </Link>
                </div>
                <p class="text-uppercase small fw-semibold text-primary mb-2">Detalle de orden</p>
                <h2 class="h3 mb-2">{order.order_number}</h2>
                <p class="text-secondary mb-0">{order.supplier.name}{order.supplier.nit ? ` · NIT ${order.supplier.nit}` : ''}</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge fs-6 {statusClass(order.status)}">{order.status_label}</span>
                {#if order.status === 'draft'}
                    <button class="btn btn-primary d-inline-flex align-items-center gap-2" type="button" onclick={sendOrder} disabled={isSending || isCancelling}>
                        {#if isSending}
                            <span class="spinner-border spinner-border-sm" role="status"></span>
                        {:else}
                            <Send size={16} />
                        {/if}
                        Marcar como enviada
                    </button>
                    <button class="btn btn-outline-danger d-inline-flex align-items-center gap-2" type="button" onclick={cancelOrder} disabled={isSending || isCancelling}>
                        {#if isCancelling}
                            <span class="spinner-border spinner-border-sm" role="status"></span>
                        {:else}
                            <X size={16} />
                        {/if}
                        Cancelar orden
                    </button>
                {:else if order.status === 'sent'}
                    <button class="btn btn-outline-danger d-inline-flex align-items-center gap-2" type="button" onclick={cancelOrder} disabled={isCancelling}>
                        {#if isCancelling}
                            <span class="spinner-border spinner-border-sm" role="status"></span>
                        {:else}
                            <X size={16} />
                        {/if}
                        Cancelar orden
                    </button>
                {/if}
            </div>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-primary mb-1">Encabezado</p>
                    <h3 class="h5 mb-0">Datos de la orden</h3>
                </div>
                <ClipboardList class="text-secondary" size={22} />
            </div>

            <dl class="row g-2 mb-0">
                <dt class="col-6 col-md-3 text-secondary small">Fecha de orden</dt>
                <dd class="col-6 col-md-3 mb-0">{order.order_date}</dd>

                <dt class="col-6 col-md-3 text-secondary small">Entrega esperada</dt>
                <dd class="col-6 col-md-3 mb-0">{order.expected_date ?? '—'}</dd>

                {#if order.receipts_count > 0}
                    <dt class="col-6 col-md-3 text-secondary small">Recepciones</dt>
                    <dd class="col-6 col-md-3 mb-0">
                        <Link href="/purchases" class="text-decoration-none">{order.receipts_count} recepción(es)</Link>
                    </dd>
                {/if}

                {#if order.user}
                    <dt class="col-6 col-md-3 text-secondary small">Creado por</dt>
                    <dd class="col-6 col-md-3 mb-0">{order.user.name}</dd>
                {/if}

                {#if order.notes}
                    <dt class="col-12 text-secondary small">Observaciones</dt>
                    <dd class="col-12 mb-0">{order.notes}</dd>
                {/if}
            </dl>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-primary mb-1">Productos</p>
                    <h3 class="h5 mb-0">{order.items.length} ítem(s) solicitado(s)</h3>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table taguara-table">
                    <thead>
                        <tr>
                            <th>Descripción</th>
                            <th class="text-end">Cantidad</th>
                            <th class="text-end">Costo unit.</th>
                            <th class="text-end">IVA</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each order.items as item (item.id)}
                            <tr>
                                <td>{item.description}</td>
                                <td class="text-end">{item.quantity}</td>
                                <td class="text-end">{money.format(item.unit_cost)}</td>
                                <td class="text-end">{Number(item.tax_rate)}%</td>
                                <td class="text-end fw-semibold">{money.format(item.line_total)}</td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>

            <div class="taguara-totals-footer">
                <div class="taguara-totals-row">
                    <span class="text-secondary">Subtotal</span>
                    <span>{money.format(order.subtotal)}</span>
                </div>
                {#if order.tax_total > 0}
                    <div class="taguara-totals-row">
                        <span class="text-secondary">IVA</span>
                        <span>{money.format(order.tax_total)}</span>
                    </div>
                {/if}
                <div class="taguara-totals-row taguara-totals-total">
                    <span>Total orden</span>
                    <span>{money.format(order.total)}</span>
                </div>
            </div>
        </section>
    </div>
</AppLayout>
