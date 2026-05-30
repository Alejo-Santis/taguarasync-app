<script>
    import { Link, router, useForm } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import { fade, fly } from 'svelte/transition';
    import { FlaskConical, Pencil, Plus, RotateCcw, Search, Settings, Trash2, X } from '@lucide/svelte';
    import AppLayout from '../../../Layouts/AppLayout.svelte';
    import SettingsNav from '../../../Components/Settings/SettingsNav.svelte';

    let { auth, items, filters, stats } = $props();

    let form = $state(untrack(() => ({ q: filters.q ?? '' })));
    let drawerOpen = $state(false);
    let editingItem = $state(null);

    const form2 = useForm({ dci_name: '', pharmacological_group: '', atc_classification: '' });

    $effect(() => { form.q = filters.q ?? ''; });
    $effect(() => {
        document.body.style.overflow = drawerOpen ? 'hidden' : '';
        return () => { document.body.style.overflow = ''; };
    });

    const handleKeydown = (e) => { if (e.key === 'Escape') closeDrawer(); };
    const submit = (e) => { e.preventDefault(); router.get('/settings/active-ingredients', form, { preserveState: true, replace: true }); };
    const resetFilters = () => { form = { q: '' }; router.get('/settings/active-ingredients', {}, { preserveState: true, replace: true }); };

    const openCreate = () => { editingItem = null; form2.reset(); drawerOpen = true; };
    const openEdit = (item) => {
        editingItem = item;
        form2.dci_name = item.dci_name;
        form2.pharmacological_group = item.pharmacological_group ?? '';
        form2.atc_classification = item.atc_classification ?? '';
        drawerOpen = true;
    };
    const closeDrawer = () => { drawerOpen = false; editingItem = null; form2.reset(); form2.clearErrors(); };

    const saveForm = () => {
        if (editingItem) {
            form2.put(`/settings/active-ingredients/${editingItem.id}`, { onSuccess: closeDrawer, preserveScroll: true });
        } else {
            form2.post('/settings/active-ingredients', { onSuccess: closeDrawer, preserveScroll: true });
        }
    };

    const destroy = (item) => {
        if (confirm(`Eliminar "${item.dci_name}"?`)) {
            router.delete(`/settings/active-ingredients/${item.id}`, { preserveScroll: true });
        }
    };
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

        <SettingsNav active="principios-activos" />

        <section class="taguara-panel">
            <div class="taguara-panel-header align-items-start">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Principios activos (DCI)</p>
                    <h3 class="h5 mb-0">{stats.total} registrados</h3>
                </div>
                <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="button" onclick={openCreate}>
                    <Plus size={17} />
                    Nuevo principio activo
                </button>
            </div>

            <form class="taguara-filter-grid" onsubmit={submit} style="grid-template-columns: minmax(300px,1fr) auto">
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Buscar por nombre DCI o grupo</span>
                    <span class="taguara-filter-input">
                        <Search size={17} />
                        <input class="form-control" bind:value={form.q} type="search" placeholder="Acetaminofen, AINES..." />
                    </span>
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
                            <th>Nombre DCI</th>
                            <th>Grupo farmacologico</th>
                            <th>Clasificacion ATC</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each items.data as item}
                            <tr onclick={() => openEdit(item)}>
                                <td><div class="taguara-table-name">{item.dci_name}</div></td>
                                <td class="text-secondary" style="font-size:.875rem">{item.pharmacological_group ?? '—'}</td>
                                <td><code class="small">{item.atc_classification ?? '—'}</code></td>
                                <td>
                                    <div class="d-flex gap-1 justify-content-end">
                                        <button class="btn btn-sm btn-light border taguara-icon-button-sm" type="button" aria-label="Editar" onclick={(e) => { e.stopPropagation(); openEdit(item); }}><Pencil size={15} /></button>
                                        <button class="btn btn-sm btn-light border taguara-icon-button-sm text-danger" type="button" aria-label="Eliminar" onclick={(e) => { e.stopPropagation(); destroy(item); }}><Trash2 size={15} /></button>
                                    </div>
                                </td>
                            </tr>
                        {:else}
                            <tr><td colspan="4"><div class="taguara-empty-state"><FlaskConical size={34} /><h4 class="h6 mb-1">No hay principios activos</h4><p class="text-secondary mb-0">Agrega los principios activos (DCI) para los productos del catalogo.</p></div></td></tr>
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
                        <p class="text-uppercase small fw-semibold text-success mb-1">Principio activo</p>
                        <h2 class="h5 mb-0">{editingItem ? editingItem.dci_name : 'Nuevo principio activo'}</h2>
                    </div>
                    <button class="btn btn-light border taguara-icon-button flex-shrink-0" type="button" aria-label="Cerrar panel" onclick={closeDrawer}><X size={17} /></button>
                </div>
            </div>
            <div class="taguara-drawer-body">
                <form id="ingredient-form" onsubmit={(e) => { e.preventDefault(); saveForm(); }}>
                    <div class="vstack gap-3">
                        <div>
                            <label class="form-label" for="ing-dci">Nombre DCI <span class="text-danger">*</span></label>
                            <input id="ing-dci" class="form-control" class:is-invalid={form2.errors.dci_name} type="text" bind:value={form2.dci_name} placeholder="Acetaminofen">
                            {#if form2.errors.dci_name}<div class="invalid-feedback">{form2.errors.dci_name}</div>{/if}
                        </div>
                        <div>
                            <label class="form-label" for="ing-group">Grupo farmacologico</label>
                            <input id="ing-group" class="form-control" type="text" bind:value={form2.pharmacological_group} placeholder="AINES, Analgesicos...">
                        </div>
                        <div>
                            <label class="form-label" for="ing-atc">Clasificacion ATC</label>
                            <input id="ing-atc" class="form-control" type="text" bind:value={form2.atc_classification} placeholder="N02BE01">
                        </div>
                    </div>
                </form>
            </div>
            <div class="taguara-drawer-footer">
                <button class="btn btn-taguara w-100" type="submit" form="ingredient-form" disabled={form2.processing}>
                    {form2.processing ? 'Guardando...' : (editingItem ? 'Actualizar' : 'Crear principio activo')}
                </button>
            </div>
        </aside>
    {/if}
</AppLayout>
