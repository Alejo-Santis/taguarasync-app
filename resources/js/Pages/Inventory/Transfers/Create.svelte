<script>
    import { router, useForm } from '@inertiajs/svelte';
    import { ArrowLeftRight, Plus, Search, Trash2, X } from '@lucide/svelte';
    import AppLayout from '../../../Layouts/AppLayout.svelte';

    let { auth, branches, lots } = $props();

    const form = useForm({
        from_branch_id: '',
        to_branch_id: '',
        notes: '',
        items: [],
    });

    let searchQuery = $state('');
    let selectedItems = $state([]);

    // Filtered lots by from_branch and search query
    const availableLots = $derived(
        lots.filter((lot) => {
            if (form.from_branch_id && lot.branch_id !== Number(form.from_branch_id)) return false;
            if (!searchQuery) return true;
            const q = searchQuery.toLowerCase();
            return (
                lot.product_name?.toLowerCase().includes(q) ||
                lot.lot_number?.toLowerCase().includes(q)
            );
        })
    );

    const addItem = (lot) => {
        const existing = selectedItems.find((i) => i.lot_id === lot.id);
        if (existing) return;
        selectedItems = [...selectedItems, { lot_id: lot.id, lot_number: lot.lot_number, product_name: lot.product_name, max_quantity: lot.current_quantity, quantity: 1, expires_on: lot.expires_on }];
        searchQuery = '';
    };

    const removeItem = (lotId) => {
        selectedItems = selectedItems.filter((i) => i.lot_id !== lotId);
    };

    const onFromBranchChange = () => {
        selectedItems = [];
        searchQuery = '';
    };

    const submit = () => {
        form.items = selectedItems.map((i) => ({ lot_id: i.lot_id, quantity: i.quantity }));
        form.post('/inventory/transfers', { preserveScroll: true });
    };
</script>

<AppLayout title="Nuevo traslado" activeSection="transfers" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Inventario</p>
                <h2 class="h3 mb-2">Nuevo traslado</h2>
                <p class="text-secondary mb-0">Selecciona las sucursales y los lotes a trasladar.</p>
            </div>
            <ArrowLeftRight class="text-secondary" size={22} />
        </section>

        <div class="row g-3">
            <!-- Sucursales y notas -->
            <div class="col-12 col-lg-4">
                <section class="taguara-panel h-100">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Configuracion</p>
                            <h3 class="h6 mb-0">Sucursales y notas</h3>
                        </div>
                    </div>
                    <div class="vstack gap-3">
                        <div>
                            <label class="form-label" for="from-branch">Sucursal origen <span class="text-danger">*</span></label>
                            <select
                                id="from-branch"
                                class="form-select"
                                class:is-invalid={form.errors.from_branch_id}
                                bind:value={form.from_branch_id}
                                onchange={onFromBranchChange}
                            >
                                <option value="">Seleccionar...</option>
                                {#each branches as branch}
                                    <option value={branch.id}>{branch.name}</option>
                                {/each}
                            </select>
                            {#if form.errors.from_branch_id}<div class="invalid-feedback">{form.errors.from_branch_id}</div>{/if}
                        </div>
                        <div>
                            <label class="form-label" for="to-branch">Sucursal destino <span class="text-danger">*</span></label>
                            <select
                                id="to-branch"
                                class="form-select"
                                class:is-invalid={form.errors.to_branch_id}
                                bind:value={form.to_branch_id}
                            >
                                <option value="">Seleccionar...</option>
                                {#each branches.filter((b) => b.id !== Number(form.from_branch_id)) as branch}
                                    <option value={branch.id}>{branch.name}</option>
                                {/each}
                            </select>
                            {#if form.errors.to_branch_id}<div class="invalid-feedback">{form.errors.to_branch_id}</div>{/if}
                        </div>
                        <div>
                            <label class="form-label" for="notes">Notas</label>
                            <textarea id="notes" class="form-control" rows="3" bind:value={form.notes} placeholder="Motivo del traslado..."></textarea>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Buscador de lotes -->
            <div class="col-12 col-lg-8">
                <section class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Lotes</p>
                            <h3 class="h6 mb-0">Buscar y agregar lotes</h3>
                        </div>
                    </div>

                    {#if !form.from_branch_id}
                        <p class="text-secondary small mb-3">Selecciona la sucursal origen para ver los lotes disponibles.</p>
                    {:else}
                        <div class="taguara-filter-input mb-3">
                            <Search size={17} />
                            <input class="form-control" type="search" placeholder="Buscar por producto o numero de lote..." bind:value={searchQuery}>
                        </div>

                        {#if searchQuery}
                            <div class="taguara-table-wrapper mb-3" style="max-height:260px;overflow-y:auto">
                                <table class="taguara-table">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Lote</th>
                                            <th class="text-end">Stock</th>
                                            <th>Vence</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {#each availableLots as lot}
                                            <tr>
                                                <td style="font-size:.875rem">{lot.product_name}</td>
                                                <td class="text-secondary" style="font-size:.875rem">{lot.lot_number}</td>
                                                <td class="text-end">{lot.current_quantity}</td>
                                                <td class="text-secondary" style="font-size:.875rem">{lot.expires_on ?? 'Sin venc.'}</td>
                                                <td>
                                                    <button
                                                        class="btn btn-sm btn-taguara d-inline-flex align-items-center gap-1"
                                                        type="button"
                                                        onclick={() => addItem(lot)}
                                                        disabled={!!selectedItems.find((i) => i.lot_id === lot.id)}
                                                    >
                                                        <Plus size={14} />
                                                        Agregar
                                                    </button>
                                                </td>
                                            </tr>
                                        {:else}
                                            <tr>
                                                <td colspan="5" class="text-secondary text-center" style="font-size:.875rem">Sin resultados</td>
                                            </tr>
                                        {/each}
                                    </tbody>
                                </table>
                            </div>
                        {/if}
                    {/if}

                    <!-- Items seleccionados -->
                    {#if form.errors.items}
                        <div class="alert alert-danger py-2 small mb-3">{form.errors.items}</div>
                    {/if}

                    <div class="taguara-table-wrapper">
                        <table class="taguara-table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Lote</th>
                                    <th>Vence</th>
                                    <th class="text-end" style="width:120px">Cantidad</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                {#each selectedItems as item (item.lot_id)}
                                    <tr>
                                        <td style="font-size:.875rem">{item.product_name}</td>
                                        <td class="text-secondary" style="font-size:.875rem">{item.lot_number}</td>
                                        <td class="text-secondary" style="font-size:.875rem">{item.expires_on ?? 'Sin venc.'}</td>
                                        <td class="text-end">
                                            <input
                                                class="form-control form-control-sm text-end"
                                                style="width:90px;display:inline-block"
                                                type="number"
                                                min="1"
                                                max={item.max_quantity}
                                                bind:value={item.quantity}
                                            >
                                            <div class="text-secondary mt-1" style="font-size:.75rem">max {item.max_quantity}</div>
                                        </td>
                                        <td>
                                            <button
                                                class="btn btn-sm btn-light border taguara-icon-button-sm"
                                                type="button"
                                                aria-label="Eliminar"
                                                onclick={() => removeItem(item.lot_id)}
                                            >
                                                <Trash2 size={14} class="text-danger" />
                                            </button>
                                        </td>
                                    </tr>
                                {:else}
                                    <tr>
                                        <td colspan="5">
                                            <div class="taguara-empty-state" style="padding:2rem">
                                                <ArrowLeftRight size={28} />
                                                <p class="text-secondary small mb-0">Busca y agrega lotes para trasladar.</p>
                                            </div>
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 d-flex justify-content-end">
                        <button
                            class="btn btn-taguara d-inline-flex align-items-center gap-2"
                            type="button"
                            onclick={submit}
                            disabled={form.processing || selectedItems.length === 0}
                        >
                            <ArrowLeftRight size={18} />
                            {form.processing ? 'Creando...' : 'Crear traslado (borrador)'}
                        </button>
                    </div>
                </section>
            </div>
        </div>
    </div>
</AppLayout>
