<script>
    import { Link, router } from '@inertiajs/svelte';
    import {
        ArrowLeft,
        ClipboardList,
        Filter,
        Plus,
        RotateCcw,
        Search,
    } from '@lucide/svelte';
    import AppLayout from '../../../Layouts/AppLayout.svelte';

    let { auth, orders, filters, statuses } = $props();

    let form = $state({ q: '', status: '' });

    const money = new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    });

    $effect(() => {
        form.q = filters.q ?? '';
        form.status = filters.status ?? '';
    });

    const submit = (event) => {
        event.preventDefault();
        router.get('/purchases/orders', form, { preserveState: true, replace: true });
    };

    const resetFilters = () => {
        form = { q: '', status: '' };
        router.get('/purchases/orders', {}, { preserveState: true, replace: true });
    };

    const statusClass = (status) => {
        if (status === 'received') return 'text-bg-success';
        if (status === 'partially_received') return 'text-bg-info';
        if (status === 'sent') return 'text-bg-primary';
        if (status === 'cancelled') return 'text-bg-danger';
        return 'text-bg-secondary';
    };
</script>

<AppLayout title="Órdenes de compra" activeSection="purchases" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <Link class="btn btn-sm btn-light border d-inline-flex align-items-center gap-1" href="/purchases">
                        <ArrowLeft size={15} />
                        Compras
                    </Link>
                </div>
                <p class="text-uppercase small fw-semibold text-primary mb-2">Órdenes de compra</p>
                <h2 class="h3 mb-2">Órdenes a proveedores</h2>
                <p class="text-secondary mb-0">Gestiona pedidos a proveedores antes de recibir la mercancía.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <Link class="btn btn-taguara d-inline-flex align-items-center gap-2" href="/purchases/orders/create">
                    <Plus size={18} />
                    Nueva orden
                </Link>
            </div>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header align-items-start">
                <div>
                    <p class="text-uppercase small fw-semibold text-primary mb-1">Búsqueda</p>
                    <h3 class="h5 mb-0">Filtrar órdenes</h3>
                </div>
                <Filter class="text-secondary" size={22} />
            </div>

            <form class="taguara-filter-grid" onsubmit={submit}>
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Número de orden</span>
                    <span class="taguara-filter-input">
                        <Search size={17} />
                        <input class="form-control" bind:value={form.q} type="search" placeholder="OC-001..." />
                    </span>
                </label>
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Estado</span>
                    <select class="form-select" bind:value={form.status}>
                        <option value="">Todos</option>
                        {#each statuses as status}
                            <option value={status.value}>{status.label}</option>
                        {/each}
                    </select>
                </label>
                <div class="d-flex align-items-end gap-2">
                    <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="submit">
                        <Search size={17} />
                        Filtrar
                    </button>
                    <button class="btn btn-light border taguara-icon-button" type="button" aria-label="Limpiar filtros" onclick={resetFilters}>
                        <RotateCcw size={17} />
                    </button>
                </div>
            </form>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-primary mb-1">Resultados</p>
                    <h3 class="h5 mb-0">{orders.total} órdenes encontradas</h3>
                </div>
                <span class="badge text-bg-light border text-secondary">
                    Página {orders.current_page} de {orders.last_page}
                </span>
            </div>

            {#if orders.data.length === 0}
                <div class="taguara-empty-state">
                    <ClipboardList size={40} class="text-secondary mb-3" />
                    <p class="h5 mb-1">Sin órdenes de compra</p>
                    <p class="text-secondary mb-3">Crea órdenes para llevar control de pedidos a proveedores.</p>
                    <Link class="btn btn-taguara d-inline-flex align-items-center gap-2" href="/purchases/orders/create">
                        <Plus size={17} />
                        Nueva orden
                    </Link>
                </div>
            {:else}
                <div class="table-responsive">
                    <table class="table taguara-table">
                        <thead>
                            <tr>
                                <th>Orden</th>
                                <th>Proveedor</th>
                                <th>Fecha</th>
                                <th>Entrega esperada</th>
                                <th>Estado</th>
                                <th class="text-end">Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each orders.data as order (order.uuid)}
                                <tr>
                                    <td class="fw-semibold">{order.order_number}</td>
                                    <td>{order.supplier.name}</td>
                                    <td class="text-secondary">{order.order_date}</td>
                                    <td class="text-secondary">{order.expected_date ?? '—'}</td>
                                    <td>
                                        <span class="badge {statusClass(order.status)}">{order.status_label}</span>
                                    </td>
                                    <td class="text-end fw-semibold">{money.format(order.total)}</td>
                                    <td class="text-end">
                                        <Link class="btn btn-sm btn-light border" href="/purchases/orders/{order.uuid}">
                                            Ver
                                        </Link>
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>

                {#if orders.last_page > 1}
                    <div class="d-flex justify-content-center gap-2 mt-3">
                        {#each orders.links as link}
                            {#if link.url}
                                <Link class="btn btn-sm {link.active ? 'btn-taguara' : 'btn-light border'}" href={link.url}>
                                    {@html link.label}
                                </Link>
                            {:else}
                                <span class="btn btn-sm btn-light border disabled">{@html link.label}</span>
                            {/if}
                        {/each}
                    </div>
                {/if}
            {/if}
        </section>
    </div>
</AppLayout>
