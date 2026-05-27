<script>
    import { Link, router, useForm } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import { fade, fly } from 'svelte/transition';
    import {
        Building2,
        Pencil,
        Plus,
        RotateCcw,
        Search,
        ToggleLeft,
        ToggleRight,
        User,
        Users,
        X,
    } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';

    let { auth, items, filters, stats, options } = $props();

    // ── Filtros ───────────────────────────────────────────────────────────────
    let filterForm = $state(untrack(() => ({ q: filters.q ?? '', status: filters.status ?? '' })));

    $effect(() => {
        filterForm.q = filters.q ?? '';
        filterForm.status = filters.status ?? '';
    });

    const submitFilters = (e) => {
        e.preventDefault();
        router.get('/customers', filterForm, { preserveState: true, replace: true });
    };

    const resetFilters = () => {
        filterForm = { q: '', status: '' };
        router.get('/customers', {}, { preserveState: true, replace: true });
    };

    // ── Cálculo DV colombiano ─────────────────────────────────────────────────
    const calculateDv = (nit) => {
        const primes = [71, 67, 59, 53, 47, 43, 41, 37, 29, 23, 19, 17, 13, 7, 3];
        const digits = String(nit ?? '').replace(/\D/g, '');
        if (!digits || digits.length > 15) return '';
        let sum = 0;
        const offset = primes.length - digits.length;
        for (let i = 0; i < digits.length; i++) sum += parseInt(digits[i]) * primes[offset + i];
        const r = sum % 11;
        return String(r === 0 || r === 1 ? r : 11 - r);
    };

    // ── Drawer ────────────────────────────────────────────────────────────────
    let drawerOpen = $state(false);
    let editingItem = $state(null);

    const form = useForm({
        identification_type_code: '',
        identification_number: '',
        merchant_registration: '',
        first_name: '',
        last_name: '',
        business_name: '',
        organization_type_code: '',
        regime_type_code: '',
        email: '',
        phone: '',
        address: '',
        municipality_code: '',
        price_list_id: '',
        credit_limit: '',
    });

    const isNit = $derived(form.identification_type_code === '31');
    const dv = $derived(isNit ? calculateDv(form.identification_number) : '');

    $effect(() => {
        document.body.style.overflow = drawerOpen ? 'hidden' : '';
        return () => { document.body.style.overflow = ''; };
    });

    const handleKeydown = (e) => { if (e.key === 'Escape') closeDrawer(); };

    const openCreate = () => {
        editingItem = null;
        form.reset();
        drawerOpen = true;
    };

    const openEdit = (item) => {
        editingItem = item;
        form.identification_type_code = item.identification_type_code ?? '';
        form.identification_number = item.identification_number ?? '';
        form.merchant_registration = item.merchant_registration ?? '';
        form.first_name = item.first_name ?? '';
        form.last_name = item.last_name ?? '';
        form.business_name = item.business_name ?? '';
        form.organization_type_code = item.organization_type_code ?? '';
        form.regime_type_code = item.regime_type_code ?? '';
        form.email = item.email ?? '';
        form.phone = item.phone ?? '';
        form.address = item.address ?? '';
        form.municipality_code = item.municipality_code ?? '';
        form.price_list_id = item.price_list_id ? String(item.price_list_id) : '';
        form.credit_limit = item.credit_limit ? String(item.credit_limit) : '';
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
            form.put(`/customers/${editingItem.uuid}`, { onSuccess: closeDrawer, preserveScroll: true });
        } else {
            form.post('/customers', { onSuccess: closeDrawer, preserveScroll: true });
        }
    };

    const toggle = (item) => {
        router.patch(`/customers/${item.uuid}/toggle`, {}, { preserveScroll: true });
    };

    const idTypeLabel = (code) =>
        options.identification_types.find((t) => t.code === code)?.name ?? code;

    const municipalityLabel = (code) => {
        const m = options.municipalities.find((m) => m.code === code);
        return m ? `${m.department_name} – ${m.name}` : code;
    };
</script>

<svelte:window onkeydown={handleKeydown} />

<AppLayout title="Clientes" activeSection="customers" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Clientes</p>
                <h2 class="h3 mb-2">Directorio de clientes</h2>
                <p class="text-secondary mb-0">Gestiona los clientes con sus datos fiscales para facturación electrónica.</p>
            </div>
            <Users class="text-secondary" size={22} />
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header align-items-start">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Clientes</p>
                    <h3 class="h5 mb-0">{stats.total} registrados · {stats.active} activos</h3>
                </div>
                <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="button" onclick={openCreate}>
                    <Plus size={17} />
                    Nuevo cliente
                </button>
            </div>

            <form class="taguara-filter-grid" onsubmit={submitFilters} style="grid-template-columns: minmax(260px,1fr) minmax(160px,200px) auto">
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Buscar</span>
                    <span class="taguara-filter-input">
                        <Search size={17} />
                        <input class="form-control" bind:value={filterForm.q} type="search" placeholder="Nombre, documento..." />
                    </span>
                </label>
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Estado</span>
                    <select class="form-select" bind:value={filterForm.status}>
                        <option value="">Todos</option>
                        <option value="active">Activos</option>
                        <option value="inactive">Inactivos</option>
                    </select>
                </label>
                <div class="d-flex align-items-end gap-2">
                    <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="submit">
                        <Search size={17} />
                        Filtrar
                    </button>
                    <button class="btn btn-light border taguara-icon-button" type="button" aria-label="Limpiar" onclick={resetFilters}>
                        <RotateCcw size={17} />
                    </button>
                </div>
            </form>

            <div class="taguara-table-wrapper mt-3">
                <table class="taguara-table">
                    <thead>
                        <tr>
                            <th>Nombre / Razón social</th>
                            <th>Documento</th>
                            <th>Régimen</th>
                            <th>Contacto</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each items.data as item}
                            <tr onclick={() => openEdit(item)}>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="taguara-avatar-sm">
                                            {#if item.organization_type_code === '1'}
                                                <Building2 size={14} />
                                            {:else}
                                                <User size={14} />
                                            {/if}
                                        </span>
                                        <div>
                                            <div class="taguara-table-name">{item.full_name}</div>
                                            {#if item.municipality_code}
                                                <div class="taguara-table-sub">{municipalityLabel(item.municipality_code)}</div>
                                            {/if}
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size:.875rem">{item.identification_type_code} {item.identification_number}{item.verification_digit ? `-${item.verification_digit}` : ''}</div>
                                    <div class="taguara-table-sub">{idTypeLabel(item.identification_type_code)}</div>
                                </td>
                                <td class="text-secondary" style="font-size:.875rem">
                                    {item.regime_type_code === '48' ? 'Resp. IVA' : item.regime_type_code === '49' ? 'No resp. IVA' : '—'}
                                </td>
                                <td>
                                    {#if item.email}
                                        <div style="font-size:.875rem">{item.email}</div>
                                    {/if}
                                    {#if item.phone}
                                        <div class="taguara-table-sub">{item.phone}</div>
                                    {/if}
                                    {#if !item.email && !item.phone}
                                        <span class="text-secondary">—</span>
                                    {/if}
                                </td>
                                <td>
                                    <span class="badge {item.is_active ? 'text-bg-success' : 'text-bg-secondary'}">
                                        {item.is_active ? 'Activo' : 'Inactivo'}
                                    </span>
                                    {#if item.price_list_id}
                                        {@const pl = options.price_lists.find((p) => p.id === item.price_list_id)}
                                        {#if pl}<span class="badge text-bg-info ms-1">{pl.name}</span>{/if}
                                    {/if}
                                </td>
                                <td>
                                    <div class="d-flex gap-1 justify-content-end">
                                        <button
                                            class="btn btn-sm btn-light border taguara-icon-button-sm"
                                            type="button"
                                            aria-label={item.is_active ? 'Desactivar' : 'Activar'}
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
                                <td colspan="6">
                                    <div class="taguara-empty-state">
                                        <Users size={34} />
                                        <h4 class="h6 mb-1">Sin clientes registrados</h4>
                                        <p class="text-secondary mb-0">Agrega clientes con sus datos fiscales para emitir facturas nominativas.</p>
                                    </div>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>

            {#if items.links.length > 3}
                <nav class="taguara-pagination mt-3">
                    {#each items.links as link}
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
        <aside class="taguara-drawer" transition:fly={{ x: 480, duration: 220 }} aria-label={editingItem ? 'Editar cliente' : 'Nuevo cliente'}>
            <div class="taguara-drawer-header">
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <div>
                        <p class="text-uppercase small fw-semibold text-success mb-1">Cliente</p>
                        <h2 class="h5 mb-0">{editingItem ? editingItem.full_name : 'Nuevo cliente'}</h2>
                    </div>
                    <button class="btn btn-light border taguara-icon-button flex-shrink-0" type="button" onclick={closeDrawer} aria-label="Cerrar">
                        <X size={17} />
                    </button>
                </div>
            </div>

            <div class="taguara-drawer-body">
                <form id="customer-form" onsubmit={(e) => { e.preventDefault(); saveForm(); }}>
                    <div class="vstack gap-3">

                        <div class="row g-3">
                            <div class="col-7">
                                <label class="form-label" for="id-type">Tipo de documento <span class="text-danger">*</span></label>
                                <select
                                    id="id-type"
                                    class="form-select"
                                    class:is-invalid={form.errors.identification_type_code}
                                    bind:value={form.identification_type_code}
                                >
                                    <option value="">Seleccionar...</option>
                                    {#each options.identification_types as t}
                                        <option value={t.code}>{t.code} – {t.name}</option>
                                    {/each}
                                </select>
                                {#if form.errors.identification_type_code}
                                    <div class="invalid-feedback">{form.errors.identification_type_code}</div>
                                {/if}
                            </div>
                            <div class="col-5">
                                <label class="form-label" for="id-number">Número <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input
                                        id="id-number"
                                        class="form-control"
                                        class:is-invalid={form.errors.identification_number}
                                        type="text"
                                        placeholder="901234567"
                                        bind:value={form.identification_number}
                                    />
                                    {#if dv !== ''}
                                        <span class="input-group-text fw-bold text-success" title="DV calculado automáticamente">-{dv}</span>
                                    {/if}
                                </div>
                                {#if form.errors.identification_number}
                                    <div class="text-danger small mt-1">{form.errors.identification_number}</div>
                                {/if}
                            </div>
                        </div>

                        {#if form.identification_type_code === '31'}
                            <!-- Registro mercantil solo aplica a empresas (NIT) -->
                            <div>
                                <label class="form-label" for="merchant-reg">Registro mercantil</label>
                                <input
                                    id="merchant-reg"
                                    class="form-control"
                                    type="text"
                                    maxlength="30"
                                    placeholder="0000000-00"
                                    bind:value={form.merchant_registration}
                                />
                                <div class="form-text">Requerido para facturación electrónica a personas jurídicas.</div>
                            </div>

                            <!-- Empresa -->
                            <div>
                                <label class="form-label" for="business-name">Razón social <span class="text-danger">*</span></label>
                                <input
                                    id="business-name"
                                    class="form-control"
                                    class:is-invalid={form.errors.business_name}
                                    type="text"
                                    placeholder="Nombre de la empresa"
                                    bind:value={form.business_name}
                                />
                                {#if form.errors.business_name}
                                    <div class="invalid-feedback">{form.errors.business_name}</div>
                                {/if}
                            </div>
                        {:else}
                            <!-- Persona natural -->
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label" for="first-name">Nombre(s)</label>
                                    <input
                                        id="first-name"
                                        class="form-control"
                                        class:is-invalid={form.errors.first_name}
                                        type="text"
                                        bind:value={form.first_name}
                                    />
                                    {#if form.errors.first_name}
                                        <div class="invalid-feedback">{form.errors.first_name}</div>
                                    {/if}
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="last-name">Apellido(s)</label>
                                    <input
                                        id="last-name"
                                        class="form-control"
                                        class:is-invalid={form.errors.last_name}
                                        type="text"
                                        bind:value={form.last_name}
                                    />
                                    {#if form.errors.last_name}
                                        <div class="invalid-feedback">{form.errors.last_name}</div>
                                    {/if}
                                </div>
                            </div>
                        {/if}

                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label" for="org-type">Tipo organización</label>
                                <select id="org-type" class="form-select" bind:value={form.organization_type_code}>
                                    <option value="">Seleccionar...</option>
                                    {#each options.organization_types as t}
                                        <option value={t.code}>{t.name}</option>
                                    {/each}
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="regime">Régimen</label>
                                <select id="regime" class="form-select" bind:value={form.regime_type_code}>
                                    <option value="">Seleccionar...</option>
                                    {#each options.regime_types as t}
                                        <option value={t.code}>{t.name}</option>
                                    {/each}
                                </select>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label" for="cust-email">Correo electrónico</label>
                                <input
                                    id="cust-email"
                                    class="form-control"
                                    class:is-invalid={form.errors.email}
                                    type="email"
                                    bind:value={form.email}
                                />
                                {#if form.errors.email}
                                    <div class="invalid-feedback">{form.errors.email}</div>
                                {/if}
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="cust-phone">Teléfono</label>
                                <input id="cust-phone" class="form-control" type="text" bind:value={form.phone} />
                            </div>
                        </div>

                        <div>
                            <label class="form-label" for="cust-address">Dirección</label>
                            <input id="cust-address" class="form-control" type="text" bind:value={form.address} />
                        </div>

                        <div>
                            <label class="form-label" for="cust-municipality">Municipio</label>
                            <select id="cust-municipality" class="form-select" bind:value={form.municipality_code}>
                                <option value="">Seleccionar...</option>
                                {#each options.municipalities as m}
                                    <option value={m.code}>{m.department_name} – {m.name}</option>
                                {/each}
                            </select>
                        </div>

                        {#if options.price_lists.length > 0}
                            <div>
                                <label class="form-label" for="cust-price-list">Lista de precio</label>
                                <select id="cust-price-list" class="form-select" bind:value={form.price_list_id}>
                                    <option value="">Precio público (base)</option>
                                    {#each options.price_lists as pl}
                                        <option value={pl.id}>{pl.name}</option>
                                    {/each}
                                </select>
                                <div class="form-text">Define qué precios ve este cliente en el POS.</div>
                            </div>
                        {/if}

                        <div>
                            <label class="form-label" for="cust-credit-limit">Límite de crédito (COP)</label>
                            <input
                                id="cust-credit-limit"
                                class="form-control"
                                class:is-invalid={form.errors.credit_limit}
                                type="number"
                                min="0"
                                step="1000"
                                bind:value={form.credit_limit}
                                placeholder="Sin límite"
                            />
                            {#if form.errors.credit_limit}<div class="invalid-feedback">{form.errors.credit_limit}</div>{/if}
                            <div class="form-text">Máximo saldo pendiente permitido. Vacío = sin restricción.</div>
                        </div>

                    </div>
                </form>
            </div>

            <div class="taguara-drawer-footer">
                <button class="btn btn-taguara w-100" type="submit" form="customer-form" disabled={form.processing}>
                    {form.processing ? 'Guardando...' : (editingItem ? 'Actualizar cliente' : 'Crear cliente')}
                </button>
            </div>
        </aside>
    {/if}
</AppLayout>

<style>
    .taguara-avatar-sm {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: var(--taguara-surface, #f7f9fb);
        border: 1px solid var(--taguara-border, #dbe3ea);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: var(--taguara-text-secondary, #6c757d);
    }
</style>
