<script>
    import { Link, router, useForm } from '@inertiajs/svelte';
    import { fade, fly } from 'svelte/transition';
    import { Building, Pencil, Plus, ToggleLeft, ToggleRight, X } from '@lucide/svelte';
    import AppLayout from '../../../Layouts/AppLayout.svelte';

    let { auth, branches, filters } = $props();

    let drawerOpen = $state(false);
    let editingItem = $state(null);

    const form = useForm({
        name: '',
        address: '',
        phone: '',
        is_main: false,
    });

    $effect(() => {
        document.body.style.overflow = drawerOpen ? 'hidden' : '';
        return () => { document.body.style.overflow = ''; };
    });

    const handleKeydown = (e) => { if (e.key === 'Escape') closeDrawer(); };

    const openCreate = () => {
        editingItem = null;
        form.reset();
        form.clearErrors();
        drawerOpen = true;
    };

    const openEdit = (item) => {
        editingItem = item;
        form.name = item.name;
        form.address = item.address ?? '';
        form.phone = item.phone ?? '';
        form.is_main = item.is_main;
        form.clearErrors();
        drawerOpen = true;
    };

    const closeDrawer = () => {
        drawerOpen = false;
        editingItem = null;
        form.reset();
        form.clearErrors();
    };

    const saveForm = () => {
        if (editingItem) {
            form.put(`/settings/branches/${editingItem.id}`, { onSuccess: closeDrawer, preserveScroll: true });
        } else {
            form.post('/settings/branches', { onSuccess: closeDrawer, preserveScroll: true });
        }
    };

    const toggle = (item) => {
        router.patch(`/settings/branches/${item.id}/toggle`, {}, { preserveScroll: true });
    };
</script>

<svelte:window onkeydown={handleKeydown} />

<AppLayout title="Sucursales" activeSection="branches" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Administracion</p>
                <h2 class="h3 mb-2">Sucursales</h2>
                <p class="text-secondary mb-0">Gestiona las sucursales del establecimiento y su inventario asociado.</p>
            </div>
            <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="button" onclick={openCreate}>
                <Plus size={18} />
                Nueva sucursal
            </button>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Sucursales</p>
                    <h3 class="h5 mb-0">{branches.total} registradas</h3>
                </div>
                <Building class="text-secondary" size={22} />
            </div>

            <div class="taguara-table-wrapper">
                <table class="taguara-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Direccion</th>
                            <th>Telefono</th>
                            <th class="text-center">Cajas</th>
                            <th>Principal</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each branches.data as item}
                            <tr onclick={() => openEdit(item)}>
                                <td>
                                    <div class="taguara-table-name">{item.name}</div>
                                </td>
                                <td class="text-secondary" style="font-size:.875rem">{item.address ?? '—'}</td>
                                <td class="text-secondary" style="font-size:.875rem">{item.phone ?? '—'}</td>
                                <td class="text-center">
                                    <span class="badge text-bg-light border text-secondary">{item.registers_count}</span>
                                </td>
                                <td>
                                    {#if item.is_main}
                                        <span class="badge text-bg-primary">Principal</span>
                                    {/if}
                                </td>
                                <td>
                                    <span class={`badge ${item.is_active ? 'text-bg-success' : 'text-bg-secondary'}`}>
                                        {item.is_active ? 'Activa' : 'Inactiva'}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-1 justify-content-end">
                                        <button
                                            class="btn btn-sm btn-light border taguara-icon-button-sm"
                                            type="button"
                                            aria-label={item.is_active ? 'Desactivar' : 'Activar'}
                                            title={item.is_active ? 'Desactivar' : 'Activar'}
                                            onclick={(e) => { e.stopPropagation(); toggle(item); }}
                                        >
                                            {#if item.is_active}
                                                <ToggleRight size={15} class="text-success" />
                                            {:else}
                                                <ToggleLeft size={15} class="text-secondary" />
                                            {/if}
                                        </button>
                                        <button
                                            class="btn btn-sm btn-light border taguara-icon-button-sm"
                                            type="button"
                                            aria-label="Editar"
                                            onclick={(e) => { e.stopPropagation(); openEdit(item); }}
                                        >
                                            <Pencil size={15} />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="7">
                                    <div class="taguara-empty-state">
                                        <Building size={34} />
                                        <h4 class="h6 mb-1">No hay sucursales registradas</h4>
                                        <p class="text-secondary mb-0">Crea la primera sucursal del establecimiento.</p>
                                    </div>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>

            {#if branches.links.length > 3}
                <nav class="taguara-pagination mt-3">
                    {#each branches.links as link}
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

    {#if drawerOpen}
        <div class="taguara-drawer-backdrop" transition:fade={{ duration: 150 }} onclick={closeDrawer} role="presentation"></div>
        <aside class="taguara-drawer" transition:fly={{ x: 480, duration: 220 }} aria-label={editingItem ? 'Editar sucursal' : 'Nueva sucursal'}>
            <div class="taguara-drawer-header">
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <div>
                        <p class="text-uppercase small fw-semibold text-success mb-1">Sucursal</p>
                        <h2 class="h5 mb-0">{editingItem ? editingItem.name : 'Nueva sucursal'}</h2>
                    </div>
                    <button class="btn btn-light border taguara-icon-button flex-shrink-0" type="button" onclick={closeDrawer} aria-label="Cerrar">
                        <X size={17} />
                    </button>
                </div>
            </div>

            <div class="taguara-drawer-body">
                <form id="branch-form" onsubmit={(e) => { e.preventDefault(); saveForm(); }}>
                    <div class="vstack gap-3">
                        <div>
                            <label class="form-label" for="b-name">Nombre <span class="text-danger">*</span></label>
                            <input id="b-name" class="form-control" class:is-invalid={form.errors.name} type="text" bind:value={form.name} placeholder="Sucursal Centro">
                            {#if form.errors.name}<div class="invalid-feedback">{form.errors.name}</div>{/if}
                        </div>
                        <div>
                            <label class="form-label" for="b-address">Direccion</label>
                            <input id="b-address" class="form-control" type="text" bind:value={form.address} placeholder="Calle 10 # 5-23">
                        </div>
                        <div>
                            <label class="form-label" for="b-phone">Telefono</label>
                            <input id="b-phone" class="form-control" type="text" bind:value={form.phone} placeholder="3001234567">
                        </div>
                        <div class="form-check">
                            <input id="b-main" class="form-check-input" type="checkbox" bind:checked={form.is_main}>
                            <label class="form-check-label" for="b-main">Sucursal principal</label>
                            <div class="form-text">Al marcarla como principal se quita el indicador de las demas.</div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="taguara-drawer-footer">
                <button class="btn btn-taguara w-100" type="submit" form="branch-form" disabled={form.processing}>
                    {form.processing ? 'Guardando...' : (editingItem ? 'Actualizar sucursal' : 'Crear sucursal')}
                </button>
            </div>
        </aside>
    {/if}
</AppLayout>
