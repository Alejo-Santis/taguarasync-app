<script>
    import { Link } from '@inertiajs/svelte';
    import { ArrowRight, Building2, CircleDollarSign, HandCoins, ReceiptText, RotateCcw } from '@lucide/svelte';
    import AppLayout from '../../../Layouts/AppLayout.svelte';

    let { auth, suppliers, totals } = $props();

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const fmt = (value) => money.format(value ?? 0);

    const balanceClass = (balance) => balance > 0 ? 'text-danger fw-semibold' : balance < 0 ? 'text-success fw-semibold' : 'text-secondary';
</script>

<AppLayout title="Cuentas por pagar" activeSection="payables" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Compras</p>
                <h2 class="h3 mb-2">Cuentas por pagar</h2>
                <p class="text-secondary mb-0">Saldo pendiente con cada proveedor: compras menos pagos y devoluciones.</p>
            </div>
            <div class="d-flex gap-2">
                <Link class="btn btn-light border d-inline-flex align-items-center gap-2" href="/purchases">
                    <ReceiptText size={17} />
                    Compras
                </Link>
            </div>
        </section>

        <section class="taguara-action-grid">
            <article class="taguara-kpi-card">
                <span class="taguara-kpi-icon text-bg-danger"><ReceiptText size={20} /></span>
                <div>
                    <p class="taguara-kpi-label">Total comprado</p>
                    <h3>{fmt(totals.total_purchased)}</h3>
                </div>
            </article>
            <article class="taguara-kpi-card">
                <span class="taguara-kpi-icon text-bg-success"><HandCoins size={20} /></span>
                <div>
                    <p class="taguara-kpi-label">Total pagado</p>
                    <h3>{fmt(totals.total_paid)}</h3>
                </div>
            </article>
            <article class="taguara-kpi-card">
                <span class="taguara-kpi-icon text-bg-warning"><RotateCcw size={20} /></span>
                <div>
                    <p class="taguara-kpi-label">Total devuelto</p>
                    <h3>{fmt(totals.total_returned)}</h3>
                </div>
            </article>
            <article class="taguara-kpi-card">
                <span class="taguara-kpi-icon text-bg-primary"><CircleDollarSign size={20} /></span>
                <div>
                    <p class="taguara-kpi-label">Saldo pendiente</p>
                    <h3 class={totals.balance > 0 ? 'text-danger' : ''}>{fmt(totals.balance)}</h3>
                </div>
            </article>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header align-items-start">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Proveedores activos</p>
                    <h3 class="h5 mb-0">{suppliers.length} proveedor{suppliers.length !== 1 ? 'es' : ''}</h3>
                </div>
            </div>

            <div class="taguara-table-wrapper mt-3">
                <table class="taguara-table">
                    <thead>
                        <tr>
                            <th>Proveedor</th>
                            <th class="text-end">Compras</th>
                            <th class="text-end">Pagado</th>
                            <th class="text-end">Devuelto</th>
                            <th class="text-end">Saldo</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each suppliers as supplier}
                            <tr>
                                <td>
                                    <div class="taguara-table-name">{supplier.name}</div>
                                    {#if supplier.nit}<div class="taguara-table-sub">NIT {supplier.nit}</div>{/if}
                                    {#if supplier.contact_name}<div class="taguara-table-sub">{supplier.contact_name}</div>{/if}
                                </td>
                                <td class="text-end">{fmt(supplier.total_purchased)}</td>
                                <td class="text-end text-success">{fmt(supplier.total_paid)}</td>
                                <td class="text-end text-warning-emphasis">{fmt(supplier.total_returned)}</td>
                                <td class="text-end {balanceClass(supplier.balance)}">{fmt(supplier.balance)}</td>
                                <td>
                                    <Link href={`/purchases/payables/${supplier.uuid}`} class="btn btn-sm btn-light border taguara-icon-button-sm" title="Ver estado de cuenta">
                                        <ArrowRight size={15} />
                                    </Link>
                                </td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="6">
                                    <div class="taguara-empty-state">
                                        <Building2 size={34} />
                                        <h4 class="h6 mb-1">Sin proveedores activos</h4>
                                        <p class="text-secondary mb-0">Registra proveedores en Configuración para ver sus saldos aquí.</p>
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
