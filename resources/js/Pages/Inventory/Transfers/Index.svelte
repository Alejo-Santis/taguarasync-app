<script>
    import { Link } from '@inertiajs/svelte';
    import { ArrowLeftRight, Eye } from '@lucide/svelte';
    import AppLayout from '../../../Layouts/AppLayout.svelte';

    let { auth, transfers, branches } = $props();

    const statusClass = (value) => {
        if (value === 'confirmed') return 'text-bg-success';
        if (value === 'cancelled') return 'text-bg-danger';
        return 'text-bg-secondary';
    };
</script>

<AppLayout title="Traslados de inventario" activeSection="transfers" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Inventario</p>
                <h2 class="h3 mb-2">Traslados entre sucursales</h2>
                <p class="text-secondary mb-0">Mueve productos entre sucursales manteniendo el control de inventario.</p>
            </div>
            <Link class="btn btn-taguara d-inline-flex align-items-center gap-2" href="/inventory/transfers/create">
                <ArrowLeftRight size={18} />
                Nuevo traslado
            </Link>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Historial</p>
                    <h3 class="h5 mb-0">{transfers.total} traslados</h3>
                </div>
                <ArrowLeftRight class="text-secondary" size={22} />
            </div>

            <div class="taguara-table-wrapper">
                <table class="taguara-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Origen</th>
                            <th>Destino</th>
                            <th class="text-center">Items</th>
                            <th>Estado</th>
                            <th>Confirmado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each transfers.data as transfer}
                            <tr>
                                <td class="text-secondary" style="font-size:.875rem">{transfer.created_at}</td>
                                <td>
                                    <div class="taguara-table-name">{transfer.from_branch}</div>
                                </td>
                                <td>
                                    <div class="taguara-table-name">{transfer.to_branch}</div>
                                </td>
                                <td class="text-center">
                                    <span class="badge text-bg-light border text-secondary">{transfer.items_count}</span>
                                </td>
                                <td>
                                    <span class={`badge ${statusClass(transfer.status.value)}`}>
                                        {transfer.status.label}
                                    </span>
                                </td>
                                <td class="text-secondary" style="font-size:.875rem">{transfer.confirmed_at ?? '—'}</td>
                                <td>
                                    <Link
                                        class="btn btn-sm btn-light border taguara-icon-button-sm"
                                        href={`/inventory/transfers/${transfer.uuid}`}
                                        title="Ver detalle"
                                    >
                                        <Eye size={15} />
                                    </Link>
                                </td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="7">
                                    <div class="taguara-empty-state">
                                        <ArrowLeftRight size={34} />
                                        <h4 class="h6 mb-1">No hay traslados registrados</h4>
                                        <p class="text-secondary mb-0">Crea el primer traslado de inventario entre sucursales.</p>
                                    </div>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>

            {#if transfers.links.length > 3}
                <nav class="taguara-pagination mt-3">
                    {#each transfers.links as link}
                        {#if link.url}
                            <Link class={`btn btn-sm ${link.active ? 'btn-taguara' : 'btn-light border'}`} href={link.url}>{@html link.label}</Link>
                        {:else}
                            <span class="btn btn-sm btn-light border disabled">{@html link.label}</span>
                        {/if}
                    {/each}
                </nav>
            {/if}
        </section>
    </div>
</AppLayout>
