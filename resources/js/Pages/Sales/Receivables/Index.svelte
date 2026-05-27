<script>
    import { Link } from '@inertiajs/svelte';
    import { ArrowRight, CircleDollarSign, HandCoins, ReceiptText, Users } from '@lucide/svelte';
    import AppLayout from '../../../Layouts/AppLayout.svelte';

    let { auth, customers, totals } = $props();

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const fmt = (v) => money.format(v ?? 0);

    const balanceClass = (b) => b > 0 ? 'text-danger fw-semibold' : b < 0 ? 'text-success fw-semibold' : 'text-secondary';
</script>

<AppLayout title="Cartera de clientes" activeSection="receivables" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Ventas</p>
                <h2 class="h3 mb-2">Cartera de clientes</h2>
                <p class="text-secondary mb-0">Saldo pendiente por cobrar a clientes con ventas a crédito.</p>
            </div>
            <div class="d-flex gap-2">
                <Link class="btn btn-light border d-inline-flex align-items-center gap-2" href="/sales">
                    <ReceiptText size={17} />
                    Ventas
                </Link>
            </div>
        </section>

        <section class="taguara-action-grid">
            <article class="taguara-kpi-card">
                <span class="taguara-kpi-icon text-bg-danger"><ReceiptText size={20} /></span>
                <div>
                    <p class="taguara-kpi-label">Total facturado a crédito</p>
                    <h3>{fmt(totals.total_invoiced)}</h3>
                </div>
            </article>
            <article class="taguara-kpi-card">
                <span class="taguara-kpi-icon text-bg-success"><HandCoins size={20} /></span>
                <div>
                    <p class="taguara-kpi-label">Total cobrado</p>
                    <h3>{fmt(totals.total_collected)}</h3>
                </div>
            </article>
            <article class="taguara-kpi-card">
                <span class="taguara-kpi-icon {totals.balance > 0 ? 'text-bg-danger' : 'text-bg-success'}"><CircleDollarSign size={20} /></span>
                <div>
                    <p class="taguara-kpi-label">Saldo por cobrar</p>
                    <h3 class={totals.balance > 0 ? 'text-danger' : ''}>{fmt(totals.balance)}</h3>
                </div>
            </article>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header align-items-start">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Clientes con crédito</p>
                    <h3 class="h5 mb-0">{customers.length} cliente{customers.length !== 1 ? 's' : ''}</h3>
                </div>
            </div>

            <div class="taguara-table-wrapper mt-3">
                <table class="taguara-table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Identificación</th>
                            <th class="text-end">Facturado</th>
                            <th class="text-end">Cobrado</th>
                            <th class="text-end">Saldo</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each customers as c}
                            <tr>
                                <td><div class="taguara-table-name">{c.full_name}</div></td>
                                <td class="text-secondary" style="font-size:.875rem">{c.identification}</td>
                                <td class="text-end">{fmt(c.total_invoiced)}</td>
                                <td class="text-end text-success">{fmt(c.total_collected)}</td>
                                <td class="text-end {balanceClass(c.balance)}">{fmt(c.balance)}</td>
                                <td>
                                    <Link href={`/sales/receivables/${c.uuid}`} class="btn btn-sm btn-light border taguara-icon-button-sm" title="Ver estado de cuenta">
                                        <ArrowRight size={15} />
                                    </Link>
                                </td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="6">
                                    <div class="taguara-empty-state">
                                        <Users size={34} />
                                        <h4 class="h6 mb-1">Sin cartera activa</h4>
                                        <p class="text-secondary mb-0">No hay ventas a crédito registradas.</p>
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
