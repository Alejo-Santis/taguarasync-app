<script>
    import { Link, router } from '@inertiajs/svelte';
    import {
        ArrowLeft,
        CircleDollarSign,
        Filter,
        Plus,
        RotateCcw,
        Search,
        Undo2,
    } from '@lucide/svelte';
    import AppLayout from '../../../Layouts/AppLayout.svelte';

    let { auth, returns, filters } = $props();

    let form = $state({ q: '' });

    const money = new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    });

    $effect(() => {
        form.q = filters.q ?? '';
    });

    const submit = (event) => {
        event.preventDefault();
        router.get('/purchases/returns', form, { preserveState: true, replace: true });
    };

    const resetFilters = () => {
        form = { q: '' };
        router.get('/purchases/returns', {}, { preserveState: true, replace: true });
    };

    const statusClass = (status) => {
        if (status === 'confirmed') return 'text-bg-success';
        if (status === 'voided') return 'text-bg-danger';
        return 'text-bg-warning text-dark';
    };
</script>

<AppLayout title="Devoluciones a proveedor" activeSection="purchases" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <Link class="btn btn-sm btn-light border d-inline-flex align-items-center gap-1" href="/purchases">
                        <ArrowLeft size={15} />
                        Compras
                    </Link>
                </div>
                <p class="text-uppercase small fw-semibold text-danger mb-2">Devoluciones</p>
                <h2 class="h3 mb-2">Devoluciones a proveedor</h2>
                <p class="text-secondary mb-0">Registra mercancía devuelta al proveedor y reversa el inventario automáticamente.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <Link class="btn btn-danger d-inline-flex align-items-center gap-2" href="/purchases/returns/create">
                    <Plus size={18} />
                    Nueva devolución
                </Link>
            </div>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header align-items-start">
                <div>
                    <p class="text-uppercase small fw-semibold text-danger mb-1">Búsqueda</p>
                    <h3 class="h5 mb-0">Filtrar devoluciones</h3>
                </div>
                <Filter class="text-secondary" size={22} />
            </div>

            <form class="taguara-filter-grid" onsubmit={submit}>
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Número de documento</span>
                    <span class="taguara-filter-input">
                        <Search size={17} />
                        <input class="form-control" bind:value={form.q} type="search" placeholder="DEV-001..." />
                    </span>
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
                    <p class="text-uppercase small fw-semibold text-danger mb-1">Resultados</p>
                    <h3 class="h5 mb-0">{returns.total} devoluciones encontradas</h3>
                </div>
                <span class="badge text-bg-light border text-secondary">
                    Página {returns.current_page} de {returns.last_page}
                </span>
            </div>

            {#if returns.data.length === 0}
                <div class="taguara-empty-state">
                    <Undo2 size={40} class="text-secondary mb-3" />
                    <p class="h5 mb-1">Sin devoluciones registradas</p>
                    <p class="text-secondary mb-3">Las devoluciones a proveedor aparecerán aquí.</p>
                    <Link class="btn btn-danger d-inline-flex align-items-center gap-2" href="/purchases/returns/create">
                        <Plus size={17} />
                        Registrar devolución
                    </Link>
                </div>
            {:else}
                <div class="table-responsive">
                    <table class="table taguara-table">
                        <thead>
                            <tr>
                                <th>Documento</th>
                                <th>Proveedor</th>
                                <th>Fecha</th>
                                <th>Motivo</th>
                                <th>Estado</th>
                                <th class="text-end">Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each returns.data as ret (ret.uuid)}
                                <tr>
                                    <td class="fw-semibold">{ret.document_number}</td>
                                    <td>{ret.supplier.name}</td>
                                    <td class="text-secondary">{ret.return_date}</td>
                                    <td class="text-secondary">{ret.reason ?? '—'}</td>
                                    <td>
                                        <span class="badge {statusClass(ret.status)}">{ret.status_label}</span>
                                    </td>
                                    <td class="text-end fw-semibold">{money.format(ret.total)}</td>
                                    <td class="text-end">
                                        <Link class="btn btn-sm btn-light border" href="/purchases/returns/{ret.uuid}">
                                            Ver
                                        </Link>
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>

                {#if returns.last_page > 1}
                    <div class="d-flex justify-content-center gap-2 mt-3">
                        {#each returns.links as link}
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
