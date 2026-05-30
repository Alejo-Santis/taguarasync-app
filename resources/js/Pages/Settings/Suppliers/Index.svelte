<script>
    import { Link, router, useForm } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import { fade, fly } from 'svelte/transition';
    import { Pencil, Plus, RotateCcw, Search, Settings, Truck, ToggleLeft, ToggleRight, X } from '@lucide/svelte';
    import AppLayout from '../../../Layouts/AppLayout.svelte';
    import SettingsNav from '../../../Components/Settings/SettingsNav.svelte';

    let { auth, items, filters, stats } = $props();

    let form = $state(untrack(() => ({ q: filters.q ?? '', status: filters.status ?? '' })));
    let drawerOpen = $state(false);
    let editingItem = $state(null);

    const form2 = useForm({ name: '', nit: '', contact_name: '', contact_email: '', contact_phone: '' });

    $effect(() => { form.q = filters.q ?? ''; form.status = filters.status ?? ''; });
    $effect(() => {
        document.body.style.overflow = drawerOpen ? 'hidden' : '';
        return () => { document.body.style.overflow = ''; };
    });

    const handleKeydown = (e) => { if (e.key === 'Escape') closeDrawer(); };
    const submit = (e) => { e.preventDefault(); router.get('/settings/suppliers', form, { preserveState: true, replace: true }); };
    const resetFilters = () => { form = { q: '', status: '' }; router.get('/settings/suppliers', {}, { preserveState: true, replace: true }); };

    const openCreate = () => { editingItem = null; form2.reset(); drawerOpen = true; };
    const openEdit = (item) => {
        editingItem = item;
        form2.name = item.name;
        form2.nit = item.nit ?? '';
        form2.contact_name = item.contact_name ?? '';
        form2.contact_email = item.contact_email ?? '';
        form2.contact_phone = item.contact_phone ?? '';
        drawerOpen = true;
    };
    const closeDrawer = () => { drawerOpen = false; editingItem = null; form2.reset(); form2.clearErrors(); };

    const saveForm = () => {
        if (editingItem) {
            form2.put(`/settings/suppliers/${editingItem.uuid}`, { onSuccess: closeDrawer, preserveScroll: true });
        } else {
            form2.post('/settings/suppliers', { onSuccess: closeDrawer, preserveScroll: true });
        }
    };

    const toggle = (item) => { router.patch(`/settings/suppliers/${item.uuid}/toggle`, {}, { preserveScroll: true }); };
</script>

<svelte:window onkeydown={handleKeydown} />

<AppLayout title="Configuracion" activeSection="configuracion" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Configuracion</p>
                <h2 class="h3 mb-2">Catalogo base</h2>
                <p class="text-secondary mb-0">Gestiona los datos maestros que alimentan el catalogo de productos y las operaciones.</p>
            </div>
            <Settings class="text-secondary" size={22} />
        </section>

        <SettingsNav active="proveedores" />

        <section class="taguara-panel">
            <div class="taguara-panel-header align-items-start">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Proveedores</p>
                    <h3 class="h5 mb-0">{stats.total} registrados · {stats.active} activos</h3>
                </div>
                <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="button" onclick={openCreate}>
                    <Plus size={17} />
                    Nuevo proveedor
                </button>
            </div>

            <form class="taguara-filter-grid" onsubmit={submit} style="grid-template-columns: minmax(240px,1fr) minmax(160px,200px) auto">
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Nombre o NIT</span>
                    <span class="taguara-filter-input">
                        <Search size={17} />
                        <input class="form-control" bind:value={form.q} type="search" placeholder="Distribuciones XYZ, 900..." />
                    </span>
                </label>
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Estado</span>
                    <select class="form-select" bind:value={form.status}>
                        <option value="">Todos</option>
                        <option value="active">Activos</option>
                        <option value="inactive">Inactivos</option>
                    </select>
                </label>
                <div class="d-flex align-items-end gap-2">
                    <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="submit"><Search size={17} />Filtrar</button>
                    <button class="btn btn-light border taguara-icon-button" type="button" aria-label="Limpiar filtros" onclick={resetFilters}><RotateCcw size={17} /></button>
                </div>
            </form>

            <div class="taguara-table-wrapper mt-3">
                <table class="taguara-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>NIT</th>
                            <th>Contacto</th>
                            <th class="text-center">Compras</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each items.data as item}
                            <tr onclick={() => openEdit(item)}>
                                <td><div class="taguara-table-name">{item.name}</div></td>
                                <td class="text-secondary" style="font-size:.875rem">{item.nit ?? '—'}</td>
                                <td>
                                    <div style="font-size:.875rem">{item.contact_name ?? '—'}</div>
                                    {#if item.contact_phone}<div class="taguara-table-sub">{item.contact_phone}</div>{/if}
                                </td>
                                <td class="text-center"><span class="badge text-bg-light border text-secondary">{item.purchase_receipts_count}</span></td>
                                <td><span class={`badge ${item.is_active ? 'text-bg-success' : 'text-bg-secondary'}`}>{item.is_active ? 'Activo' : 'Inactivo'}</span></td>
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
                            <tr><td colspan="6"><div class="taguara-empty-state"><Truck size={34} /><h4 class="h6 mb-1">No hay proveedores registrados</h4><p class="text-secondary mb-0">Agrega los distribuidores y laboratorios que te surten.</p></div></td></tr>
                        {/each}
                    </tbody>
                </table>
            </div>

            {#if items.links.length > 3}
                <nav class="taguara-pagination mt-3">
                    {#each items.links as link}
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
                        <p class="text-uppercase small fw-semibold text-success mb-1">Proveedor</p>
                        <h2 class="h5 mb-0">{editingItem ? editingItem.name : 'Nuevo proveedor'}</h2>
                    </div>
                    <button class="btn btn-light border taguara-icon-button flex-shrink-0" type="button" aria-label="Cerrar panel" onclick={closeDrawer}><X size={17} /></button>
                </div>
            </div>
            <div class="taguara-drawer-body">
                <form id="supplier-form" onsubmit={(e) => { e.preventDefault(); saveForm(); }}>
                    <div class="vstack gap-3">
                        <div>
                            <label class="form-label" for="sup-name">Nombre o razon social <span class="text-danger">*</span></label>
                            <input id="sup-name" class="form-control" class:is-invalid={form2.errors.name} type="text" bind:value={form2.name} placeholder="Distribuciones XYZ S.A.S.">
                            {#if form2.errors.name}<div class="invalid-feedback">{form2.errors.name}</div>{/if}
                        </div>
                        <div>
                            <label class="form-label" for="sup-nit">NIT</label>
                            <input id="sup-nit" class="form-control" type="text" bind:value={form2.nit} placeholder="900123456-1">
                        </div>
                        <div>
                            <label class="form-label" for="sup-contact-name">Nombre de contacto</label>
                            <input id="sup-contact-name" class="form-control" type="text" bind:value={form2.contact_name}>
                        </div>
                        <div>
                            <label class="form-label" for="sup-contact-email">Email</label>
                            <input id="sup-contact-email" class="form-control" class:is-invalid={form2.errors.contact_email} type="email" bind:value={form2.contact_email}>
                            {#if form2.errors.contact_email}<div class="invalid-feedback">{form2.errors.contact_email}</div>{/if}
                        </div>
                        <div>
                            <label class="form-label" for="sup-contact-phone">Telefono</label>
                            <input id="sup-contact-phone" class="form-control" type="text" bind:value={form2.contact_phone}>
                        </div>
                    </div>
                </form>
            </div>
            <div class="taguara-drawer-footer">
                <button class="btn btn-taguara w-100" type="submit" form="supplier-form" disabled={form2.processing}>
                    {form2.processing ? 'Guardando...' : (editingItem ? 'Actualizar' : 'Crear proveedor')}
                </button>
            </div>
        </aside>
    {/if}
</AppLayout>
