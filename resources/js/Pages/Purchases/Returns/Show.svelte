<script>
    import { Link } from '@inertiajs/svelte';
    import { ArrowLeft, Undo2 } from '@lucide/svelte';
    import AppLayout from '../../../Layouts/AppLayout.svelte';

    let { auth, return: ret } = $props();

    const money = new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    });

    const statusClass = (status) => {
        if (status === 'confirmed') return 'text-bg-success';
        if (status === 'voided') return 'text-bg-danger';
        return 'text-bg-warning text-dark';
    };
</script>

<AppLayout title="Devolución {ret.document_number}" activeSection="purchases" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <Link class="btn btn-sm btn-light border d-inline-flex align-items-center gap-1" href="/purchases/returns">
                        <ArrowLeft size={15} />
                        Devoluciones
                    </Link>
                </div>
                <p class="text-uppercase small fw-semibold text-danger mb-2">Detalle</p>
                <h2 class="h3 mb-2">{ret.document_number}</h2>
                <p class="text-secondary mb-0">{ret.supplier.name}{ret.supplier.nit ? ` · NIT ${ret.supplier.nit}` : ''}</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge fs-6 {statusClass(ret.status)}">{ret.status_label}</span>
            </div>
        </section>

        <!-- Info -->
        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-danger mb-1">Encabezado</p>
                    <h3 class="h5 mb-0">Datos del documento</h3>
                </div>
                <Undo2 class="text-secondary" size={22} />
            </div>

            <dl class="row g-2 mb-0">
                <dt class="col-6 col-md-3 text-secondary small">Fecha devolución</dt>
                <dd class="col-6 col-md-3 mb-0">{ret.return_date}</dd>

                {#if ret.purchase_receipt}
                    <dt class="col-6 col-md-3 text-secondary small">Compra origen</dt>
                    <dd class="col-6 col-md-3 mb-0">{ret.purchase_receipt.document_number}</dd>
                {/if}

                {#if ret.reason}
                    <dt class="col-6 col-md-3 text-secondary small">Motivo</dt>
                    <dd class="col-6 col-md-3 mb-0">{ret.reason}</dd>
                {/if}

                {#if ret.user}
                    <dt class="col-6 col-md-3 text-secondary small">Registrado por</dt>
                    <dd class="col-6 col-md-3 mb-0">{ret.user.name}</dd>
                {/if}

                {#if ret.notes}
                    <dt class="col-12 text-secondary small">Observaciones</dt>
                    <dd class="col-12 mb-0">{ret.notes}</dd>
                {/if}
            </dl>
        </section>

        <!-- Items -->
        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-danger mb-1">Productos</p>
                    <h3 class="h5 mb-0">{ret.items.length} ítem(s) devuelto(s)</h3>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table taguara-table">
                    <thead>
                        <tr>
                            <th>Descripción</th>
                            <th>Lote</th>
                            <th class="text-end">Cantidad</th>
                            <th class="text-end">Costo unit.</th>
                            <th class="text-end">IVA</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each ret.items as item (item.id)}
                            <tr>
                                <td>{item.description}</td>
                                <td class="text-secondary">{item.lot_number}</td>
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
                    <span>{money.format(ret.subtotal)}</span>
                </div>
                {#if ret.tax_total > 0}
                    <div class="taguara-totals-row">
                        <span class="text-secondary">IVA</span>
                        <span>{money.format(ret.tax_total)}</span>
                    </div>
                {/if}
                <div class="taguara-totals-row taguara-totals-total">
                    <span>Total devuelto</span>
                    <span>{money.format(ret.total)}</span>
                </div>
            </div>
        </section>
    </div>
</AppLayout>
