<script>
    import { Link, router, useForm } from '@inertiajs/svelte';
    import { fade, fly } from 'svelte/transition';
    import { Banknote, Building2, Landmark, Pencil, Plus, RotateCcw, Search, Settings, ToggleLeft, ToggleRight, X } from '@lucide/svelte';
    import AppLayout from '../../../Layouts/AppLayout.svelte';
    import SettingsNav from '../../../Components/Settings/SettingsNav.svelte';

    let { auth, items, movements, filters, stats } = $props();

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const fmt = (value) => money.format(value ?? 0);

    let form = $state({ q: '', status: '' });
    let drawerOpen = $state(false);
    let editingItem = $state(null);

    const accountForm = useForm({
        bank_name: '',
        account_name: '',
        account_number: '',
        type: 'savings',
        is_default: false,
        notes: '',
    });

    const accountTypeLabel = (type) => ({
        savings: 'Ahorros',
        checking: 'Corriente',
        wallet: 'Billetera',
    }[type] ?? type);

    const movementTypeLabel = (type) => type === 'outflow' ? 'Salida' : 'Entrada';

    $effect(() => { form.q = filters.q ?? ''; form.status = filters.status ?? ''; });
    $effect(() => {
        document.body.style.overflow = drawerOpen ? 'hidden' : '';
        return () => { document.body.style.overflow = ''; };
    });

    const handleKeydown = (event) => { if (event.key === 'Escape') closeDrawer(); };
    const submit = (event) => { event.preventDefault(); router.get('/settings/banks', form, { preserveState: true, replace: true }); };
    const resetFilters = () => { form = { q: '', status: '' }; router.get('/settings/banks', {}, { preserveState: true, replace: true }); };

    const openCreate = () => {
        editingItem = null;
        accountForm.reset();
        accountForm.type = 'savings';
        accountForm.is_default = false;
        drawerOpen = true;
    };

    const openEdit = (item) => {
        editingItem = item;
        accountForm.bank_name = item.bank_name;
        accountForm.account_name = item.account_name;
        accountForm.account_number = item.account_number ?? '';
        accountForm.type = item.type;
        accountForm.is_default = item.is_default;
        accountForm.notes = item.notes ?? '';
        drawerOpen = true;
    };

    const closeDrawer = () => {
        drawerOpen = false;
        editingItem = null;
        accountForm.reset();
        accountForm.clearErrors();
    };

    const saveForm = () => {
        if (editingItem) {
            accountForm.put(`/settings/banks/${editingItem.id}`, { onSuccess: closeDrawer, preserveScroll: true });
        } else {
            accountForm.post('/settings/banks', { onSuccess: closeDrawer, preserveScroll: true });
        }
    };

    const toggle = (item) => { router.patch(`/settings/banks/${item.id}/toggle`, {}, { preserveScroll: true }); };
</script>

<svelte:window onkeydown={handleKeydown} />

<AppLayout title="Bancos" activeSection="configuracion" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Tesoreria</p>
                <h2 class="h3 mb-2">Bancos y conciliación</h2>
                <p class="text-secondary mb-0">Controla las cuentas donde llegan transferencias, tarjetas y billeteras del POS.</p>
            </div>
            <Landmark class="text-secondary" size={22} />
        </section>

        <SettingsNav active="banks" />

        <section class="taguara-action-grid">
            <article class="taguara-kpi-card">
                <span class="taguara-kpi-icon text-bg-success"><Banknote size={20} /></span>
                <div>
                    <p class="taguara-kpi-label">Saldo operativo</p>
                    <h3>{fmt(stats.balance)}</h3>
                </div>
            </article>
            <article class="taguara-kpi-card">
                <span class="taguara-kpi-icon text-bg-primary"><Building2 size={20} /></span>
                <div>
                    <p class="taguara-kpi-label">Cuentas activas</p>
                    <h3>{stats.active}</h3>
                </div>
            </article>
            <article class="taguara-kpi-card">
                <span class="taguara-kpi-icon text-bg-warning"><Landmark size={20} /></span>
                <div>
                    <p class="taguara-kpi-label">Pendiente por conciliar</p>
                    <h3>{fmt(stats.pending)}</h3>
                </div>
            </article>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header align-items-start">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Cuentas</p>
                    <h3 class="h5 mb-0">{stats.accounts} registradas</h3>
                </div>
                <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="button" onclick={openCreate}>
                    <Plus size={17} />
                    Nueva cuenta
                </button>
            </div>

            <form class="taguara-filter-grid" onsubmit={submit} style="grid-template-columns: minmax(240px,1fr) minmax(160px,200px) auto">
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Banco, cuenta o numero</span>
                    <span class="taguara-filter-input">
                        <Search size={17} />
                        <input class="form-control" bind:value={form.q} type="search" placeholder="Bancolombia, Nequi, 1234..." />
                    </span>
                </label>
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Estado</span>
                    <select class="form-select" bind:value={form.status}>
                        <option value="">Todos</option>
                        <option value="active">Activas</option>
                        <option value="inactive">Inactivas</option>
                    </select>
                </label>
                <div class="d-flex align-items-end gap-2">
                    <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="submit"><Search size={17} />Filtrar</button>
                    <button class="btn btn-light border taguara-icon-button" type="button" onclick={resetFilters}><RotateCcw size={17} /></button>
                </div>
            </form>

            <div class="taguara-table-wrapper mt-3">
                <table class="taguara-table">
                    <thead>
                        <tr>
                            <th>Cuenta</th>
                            <th>Tipo</th>
                            <th class="text-end">Entradas</th>
                            <th class="text-end">Salidas</th>
                            <th class="text-end">Saldo</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each items.data as item}
                            <tr onclick={() => openEdit(item)}>
                                <td>
                                    <div class="taguara-table-name">{item.bank_name}</div>
                                    <div class="taguara-table-sub">{item.account_name}{item.account_number ? ` · ${item.account_number}` : ''}</div>
                                </td>
                                <td><span class="badge text-bg-light border text-secondary">{accountTypeLabel(item.type)}</span></td>
                                <td class="text-end">{fmt(item.inflows_total)}</td>
                                <td class="text-end">{fmt(item.outflows_total)}</td>
                                <td class="text-end fw-semibold">{fmt(item.balance)}</td>
                                <td>
                                    <div class="d-flex gap-1 flex-wrap">
                                        <span class={`badge ${item.is_active ? 'text-bg-success' : 'text-bg-secondary'}`}>{item.is_active ? 'Activa' : 'Inactiva'}</span>
                                        {#if item.is_default}<span class="badge text-bg-primary">Principal</span>{/if}
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex gap-1 justify-content-end">
                                        <button class="btn btn-sm btn-light border taguara-icon-button-sm" type="button" onclick={(e) => { e.stopPropagation(); toggle(item); }}>
                                            {#if item.is_active}<ToggleRight size={15} class="text-success" />{:else}<ToggleLeft size={15} class="text-secondary" />{/if}
                                        </button>
                                        <button class="btn btn-sm btn-light border taguara-icon-button-sm" type="button" onclick={(e) => { e.stopPropagation(); openEdit(item); }}><Pencil size={15} /></button>
                                    </div>
                                </td>
                            </tr>
                        {:else}
                            <tr><td colspan="7"><div class="taguara-empty-state"><Landmark size={34} /><h4 class="h6 mb-1">No hay cuentas bancarias</h4><p class="text-secondary mb-0">Registra las cuentas donde recibes pagos electrónicos.</p></div></td></tr>
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

        <section class="taguara-panel">
            <div class="taguara-panel-header align-items-start">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Movimientos</p>
                    <h3 class="h5 mb-0">Últimas entradas y salidas</h3>
                </div>
            </div>
            <div class="taguara-table-wrapper">
                <table class="taguara-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Cuenta</th>
                            <th>Referencia</th>
                            <th>Tipo</th>
                            <th class="text-end">Monto</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each movements as movement}
                            <tr>
                                <td>{movement.occurred_at}</td>
                                <td><div class="taguara-table-name">{movement.bank}</div><div class="taguara-table-sub">{movement.account}</div></td>
                                <td>{movement.reference ?? 'Sin referencia'}</td>
                                <td>{movementTypeLabel(movement.type)}</td>
                                <td class="text-end fw-semibold">{fmt(movement.amount)}</td>
                                <td><span class={`badge ${movement.status === 'confirmed' ? 'text-bg-success' : 'text-bg-warning text-dark'}`}>{movement.status === 'confirmed' ? 'Conciliado' : 'Pendiente'}</span></td>
                            </tr>
                        {:else}
                            <tr><td colspan="6"><div class="taguara-empty-state" style="min-height:120px"><Banknote size={28} /><p class="text-secondary small mb-0">Aún no hay movimientos bancarios.</p></div></td></tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    {#if drawerOpen}
        <div class="taguara-drawer-backdrop" transition:fade={{ duration: 150 }} onclick={closeDrawer} role="presentation"></div>
        <aside class="taguara-drawer" transition:fly={{ x: 480, duration: 220 }}>
            <div class="taguara-drawer-header">
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <div>
                        <p class="text-uppercase small fw-semibold text-success mb-1">Cuenta bancaria</p>
                        <h2 class="h5 mb-0">{editingItem ? editingItem.bank_name : 'Nueva cuenta'}</h2>
                    </div>
                    <button class="btn btn-light border taguara-icon-button flex-shrink-0" type="button" onclick={closeDrawer}><X size={17} /></button>
                </div>
            </div>
            <div class="taguara-drawer-body">
                <form id="bank-form" onsubmit={(event) => { event.preventDefault(); saveForm(); }}>
                    <div class="vstack gap-3">
                        <div>
                            <label class="form-label" for="bank-name">Banco o billetera <span class="text-danger">*</span></label>
                            <input id="bank-name" class="form-control" class:is-invalid={accountForm.errors.bank_name} type="text" bind:value={accountForm.bank_name} placeholder="Bancolombia, Nequi, Davivienda">
                            {#if accountForm.errors.bank_name}<div class="invalid-feedback">{accountForm.errors.bank_name}</div>{/if}
                        </div>
                        <div>
                            <label class="form-label" for="account-name">Nombre de cuenta <span class="text-danger">*</span></label>
                            <input id="account-name" class="form-control" class:is-invalid={accountForm.errors.account_name} type="text" bind:value={accountForm.account_name} placeholder="Cuenta ventas POS">
                            {#if accountForm.errors.account_name}<div class="invalid-feedback">{accountForm.errors.account_name}</div>{/if}
                        </div>
                        <div>
                            <label class="form-label" for="account-number">Numero</label>
                            <input id="account-number" class="form-control" class:is-invalid={accountForm.errors.account_number} type="text" bind:value={accountForm.account_number} placeholder="Opcional">
                            {#if accountForm.errors.account_number}<div class="invalid-feedback">{accountForm.errors.account_number}</div>{/if}
                        </div>
                        <div>
                            <label class="form-label" for="account-type">Tipo</label>
                            <select id="account-type" class="form-select" bind:value={accountForm.type}>
                                <option value="savings">Ahorros</option>
                                <option value="checking">Corriente</option>
                                <option value="wallet">Billetera</option>
                            </select>
                        </div>
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" bind:checked={accountForm.is_default}>
                            <span class="form-check-label">Usar como cuenta principal para pagos electrónicos</span>
                        </label>
                        <div>
                            <label class="form-label" for="bank-notes">Notas</label>
                            <textarea id="bank-notes" class="form-control" rows="3" bind:value={accountForm.notes} placeholder="Observaciones internas"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="taguara-drawer-footer">
                <button class="btn btn-taguara w-100" type="submit" form="bank-form" disabled={accountForm.processing}>
                    {accountForm.processing ? 'Guardando...' : (editingItem ? 'Actualizar cuenta' : 'Crear cuenta')}
                </button>
            </div>
        </aside>
    {/if}
</AppLayout>
