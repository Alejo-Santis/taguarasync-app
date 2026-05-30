<script>
    import { Link, router, useForm } from '@inertiajs/svelte';
    import { fade, fly } from 'svelte/transition';
    import { Pencil, Plus, Tag, ToggleLeft, ToggleRight, X } from '@lucide/svelte';
    import AppLayout from '../../../Layouts/AppLayout.svelte';
    import SettingsNav from '../../../Components/Settings/SettingsNav.svelte';

    let { auth, lists } = $props();

    let drawerOpen = $state(false);
    let editingItem = $state(null);

    const listForm = useForm({
        name: '',
        description: '',
        is_default: false,
    });

    $effect(() => {
        document.body.style.overflow = drawerOpen ? 'hidden' : '';
        return () => { document.body.style.overflow = ''; };
    });

    const handleKeydown = (event) => { if (event.key === 'Escape') closeDrawer(); };

    const openCreate = () => {
        editingItem = null;
        listForm.reset();
        drawerOpen = true;
    };

    const openEdit = (item) => {
        editingItem = item;
        listForm.name = item.name;
        listForm.description = item.description ?? '';
        listForm.is_default = item.is_default;
        drawerOpen = true;
    };

    const closeDrawer = () => {
        drawerOpen = false;
        editingItem = null;
        listForm.reset();
        listForm.clearErrors();
    };

    const saveForm = () => {
        if (editingItem) {
            listForm.put(`/settings/price-lists/${editingItem.id}`, { onSuccess: closeDrawer, preserveScroll: true });
        } else {
            listForm.post('/settings/price-lists', { onSuccess: closeDrawer, preserveScroll: true });
        }
    };

    const toggle = (item) => { router.patch(`/settings/price-lists/${item.id}/toggle`, {}, { preserveScroll: true }); };
</script>

<svelte:window onkeydown={handleKeydown} />

<AppLayout title="Listas de precio" activeSection="configuracion" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Configuración</p>
                <h2 class="h3 mb-2">Listas de precio</h2>
                <p class="text-secondary mb-0">Define precios especiales para EPS, instituciones o segmentos de clientes.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <Tag class="text-secondary" size={22} />
            </div>
        </section>

        <SettingsNav active="price-lists" />

        <section class="taguara-panel">
            <div class="taguara-panel-header align-items-start">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Listas</p>
                    <h3 class="h5 mb-0">{lists.total} registradas</h3>
                </div>
                <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="button" onclick={openCreate}>
                    <Plus size={17} />
                    Nueva lista
                </button>
            </div>

            <div class="taguara-table-wrapper mt-3">
                <table class="taguara-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th class="text-end">Productos</th>
                            <th class="text-end">Clientes</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each lists.data as item}
                            <tr onclick={() => openEdit(item)}>
                                <td>
                                    <div class="taguara-table-name">
                                        <Link href={`/settings/price-lists/${item.id}`} onclick={(e) => e.stopPropagation()} class="text-decoration-none">
                                            {item.name}
                                        </Link>
                                    </div>
                                </td>
                                <td class="text-secondary">{item.description ?? '—'}</td>
                                <td class="text-end">{item.items_count}</td>
                                <td class="text-end">{item.customers_count}</td>
                                <td>
                                    <div class="d-flex gap-1 flex-wrap">
                                        <span class={`badge ${item.is_active ? 'text-bg-success' : 'text-bg-secondary'}`}>{item.is_active ? 'Activa' : 'Inactiva'}</span>
                                        {#if item.is_default}<span class="badge text-bg-primary">Por defecto</span>{/if}
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex gap-1 justify-content-end">
                                        <button class="btn btn-sm btn-light border taguara-icon-button-sm" type="button" aria-label={item.is_active ? 'Desactivar' : 'Activar'} onclick={(e) => { e.stopPropagation(); toggle(item); }}>
                                            {#if item.is_active}<ToggleRight size={15} class="text-success" />{:else}<ToggleLeft size={15} class="text-secondary" />{/if}
                                        </button>
                                        <button class="btn btn-sm btn-light border taguara-icon-button-sm" type="button" aria-label="Editar" onclick={(e) => { e.stopPropagation(); openEdit(item); }}><Pencil size={15} /></button>
                                    </div>
                                </td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="6">
                                    <div class="taguara-empty-state">
                                        <Tag size={34} />
                                        <h4 class="h6 mb-1">No hay listas de precio</h4>
                                        <p class="text-secondary mb-0">Crea listas para manejar precios especiales por segmento de cliente.</p>
                                    </div>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>

            {#if lists.links.length > 3}
                <nav class="taguara-pagination mt-3">
                    {#each lists.links as link}
                        {#if link.url}<Link class={`btn btn-sm ${link.active ? 'btn-taguara' : 'btn-light border'}`} href={link.url}>{@html link.label}</Link>
                        {:else}<span class="btn btn-sm btn-light border disabled">{@html link.label}</span>{/if}
                    {/each}
                </nav>
            {/if}
        </section>
    </div>

    {#if drawerOpen}
        <div class="taguara-drawer-backdrop" transition:fade={{ duration: 150 }} onclick={closeDrawer} role="presentation"></div>
        <aside class="taguara-drawer" transition:fly={{ x: 480, duration: 220 }}>
            <div class="taguara-drawer-header">
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <div>
                        <p class="text-uppercase small fw-semibold text-success mb-1">Lista de precio</p>
                        <h2 class="h5 mb-0">{editingItem ? editingItem.name : 'Nueva lista'}</h2>
                    </div>
                    <button class="btn btn-light border taguara-icon-button flex-shrink-0" type="button" aria-label="Cerrar panel" onclick={closeDrawer}><X size={17} /></button>
                </div>
            </div>
            <div class="taguara-drawer-body">
                <form id="list-form" onsubmit={(event) => { event.preventDefault(); saveForm(); }}>
                    <div class="vstack gap-3">
                        <div>
                            <label class="form-label" for="list-name">Nombre <span class="text-danger">*</span></label>
                            <input id="list-name" class="form-control" class:is-invalid={listForm.errors.name} type="text" bind:value={listForm.name} placeholder="EPS, Institucional, Mayorista...">
                            {#if listForm.errors.name}<div class="invalid-feedback">{listForm.errors.name}</div>{/if}
                        </div>
                        <div>
                            <label class="form-label" for="list-description">Descripción</label>
                            <input id="list-description" class="form-control" class:is-invalid={listForm.errors.description} type="text" bind:value={listForm.description} placeholder="Descripción interna opcional">
                            {#if listForm.errors.description}<div class="invalid-feedback">{listForm.errors.description}</div>{/if}
                        </div>
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" bind:checked={listForm.is_default}>
                            <span class="form-check-label">Usar como lista de precio por defecto</span>
                        </label>
                    </div>
                </form>
            </div>
            <div class="taguara-drawer-footer">
                <button class="btn btn-taguara w-100" type="submit" form="list-form" disabled={listForm.processing}>
                    {listForm.processing ? 'Guardando...' : (editingItem ? 'Actualizar lista' : 'Crear lista')}
                </button>
            </div>
        </aside>
    {/if}
</AppLayout>
