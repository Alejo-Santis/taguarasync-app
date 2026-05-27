<script>
    import { router, useForm } from '@inertiajs/svelte';
    import { fade, fly } from 'svelte/transition';
    import {
        AlertCircle,
        Building2,
        CheckCircle2,
        Lock,
        Plus,
        ShieldCheck,
        Unlock,
        Users,
        X,
    } from '@lucide/svelte';
    import AppLayout from '../../../Layouts/AppLayout.svelte';

    let { auth, tenants } = $props();

    // ── Formulario crear tenant ───────────────────────────────────────────────
    let drawerOpen = $state(false);

    const form = useForm(() => ({
        tenant_name: '',
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    }));

    const openDrawer = () => {
        form.reset();
        form.clearErrors();
        drawerOpen = true;
    };

    const closeDrawer = () => { drawerOpen = false; };

    const submit = () => {
        form.post('/admin/tenants', {
            onSuccess: () => { drawerOpen = false; form.reset(); },
        });
    };

    // ── Activar / Suspender ───────────────────────────────────────────────────
    const toggleStatus = (tenant) => {
        router.patch(`/admin/tenants/${tenant.uuid}/toggle-status`, {}, { preserveScroll: true });
    };

    const statusClass = (value) => {
        if (value === 'active') return 'text-bg-success';
        if (value === 'suspended') return 'text-bg-warning text-dark';
        return 'text-bg-secondary';
    };
</script>

<AppLayout title="Administración de tenants" activeSection="admin" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-danger mb-2">Super admin</p>
                <h2 class="h3 mb-2">Farmacias registradas</h2>
                <p class="text-secondary mb-0">
                    Gestiona los tenants del sistema. El registro público está deshabilitado — las cuentas se crean desde aquí.
                </p>
            </div>
            <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="button" onclick={openDrawer}>
                <Plus size={18} />
                Nueva farmacia
            </button>
        </section>

        <!-- Stats rápidos -->
        <section class="row g-3">
            <div class="col-6 col-md-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-success"><Building2 size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Total farmacias</p>
                        <p class="h3 mb-0">{tenants.length}</p>
                    </div>
                </article>
            </div>
            <div class="col-6 col-md-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-success"><CheckCircle2 size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Activas</p>
                        <p class="h3 mb-0">{tenants.filter(t => t.status.value === 'active').length}</p>
                    </div>
                </article>
            </div>
            <div class="col-6 col-md-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-warning"><Lock size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Suspendidas</p>
                        <p class="h3 mb-0">{tenants.filter(t => t.status.value === 'suspended').length}</p>
                    </div>
                </article>
            </div>
            <div class="col-6 col-md-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-primary"><Users size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Usuarios totales</p>
                        <p class="h3 mb-0">{tenants.reduce((s, t) => s + t.users_count, 0)}</p>
                    </div>
                </article>
            </div>
        </section>

        <!-- Tabla de tenants -->
        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Tenants</p>
                    <h3 class="h5 mb-0">{tenants.length} farmacias en el sistema</h3>
                </div>
                <ShieldCheck class="text-success" size={22} />
            </div>

            {#if tenants.length > 0}
                <div class="taguara-table-wrapper">
                    <table class="taguara-table">
                        <thead>
                            <tr>
                                <th>Farmacia</th>
                                <th>Email</th>
                                <th>NIT</th>
                                <th class="text-center">Usuarios</th>
                                <th>Estado</th>
                                <th>Creada</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each tenants as tenant}
                                <tr>
                                    <td>
                                        <div class="taguara-table-name">{tenant.name}</div>
                                    </td>
                                    <td class="taguara-table-sub">{tenant.email ?? '—'}</td>
                                    <td class="taguara-table-sub">{tenant.nit ?? '—'}</td>
                                    <td class="text-center">
                                        <span class="badge text-bg-light border text-secondary">{tenant.users_count}</span>
                                    </td>
                                    <td>
                                        <span class={`badge ${statusClass(tenant.status.value)}`}>{tenant.status.label}</span>
                                    </td>
                                    <td class="taguara-table-sub">{tenant.created_at}</td>
                                    <td>
                                        <button
                                            class="btn btn-sm {tenant.status.value === 'active' ? 'btn-outline-warning' : 'btn-outline-success'} d-inline-flex align-items-center gap-1"
                                            type="button"
                                            onclick={() => toggleStatus(tenant)}
                                            title={tenant.status.value === 'active' ? 'Suspender acceso' : 'Reactivar acceso'}
                                        >
                                            {#if tenant.status.value === 'active'}
                                                <Lock size={13} /> Suspender
                                            {:else}
                                                <Unlock size={13} /> Activar
                                            {/if}
                                        </button>
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            {:else}
                <div class="taguara-empty-state">
                    <Building2 size={34} />
                    <h4 class="h6 mb-1">Sin farmacias registradas</h4>
                    <p class="text-secondary mb-3">Crea la primera farmacia para empezar.</p>
                    <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="button" onclick={openDrawer}>
                        <Plus size={17} />
                        Nueva farmacia
                    </button>
                </div>
            {/if}
        </section>
    </div>

    <!-- Drawer: crear nuevo tenant -->
    {#if drawerOpen}
        <div class="taguara-drawer-backdrop" transition:fade={{ duration: 150 }} onclick={closeDrawer} role="presentation"></div>
        <aside class="taguara-drawer" transition:fly={{ x: 480, duration: 220 }}>
            <div class="taguara-drawer-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="taguara-kpi-icon text-bg-success"><Building2 size={18} /></span>
                    <div>
                        <p class="text-uppercase small fw-semibold text-success mb-0">Nueva cuenta</p>
                        <h2 class="h5 mb-0">Registrar farmacia</h2>
                    </div>
                </div>
                <button class="btn btn-light border taguara-icon-button" type="button" onclick={closeDrawer}><X size={17} /></button>
            </div>

            <div class="taguara-drawer-body">
                {#if form.errors._general}
                    <div class="alert alert-danger small py-2 d-flex gap-2 mb-3">
                        <AlertCircle size={15} class="flex-shrink-0 mt-1" />
                        {form.errors._general}
                    </div>
                {/if}

                <div class="mb-4">
                    <p class="small fw-semibold text-secondary text-uppercase mb-3">Datos de la farmacia</p>

                    <div class="mb-3">
                        <label class="form-label" for="tenant-name">Nombre de la farmacia <span class="text-danger">*</span></label>
                        <input
                            id="tenant-name"
                            class="form-control {form.errors.tenant_name ? 'is-invalid' : ''}"
                            type="text"
                            bind:value={form.tenant_name}
                            placeholder="Ej. Farmacia Salud Total"
                            autocomplete="off"
                        />
                        {#if form.errors.tenant_name}<div class="invalid-feedback">{form.errors.tenant_name}</div>{/if}
                    </div>
                </div>

                <div>
                    <p class="small fw-semibold text-secondary text-uppercase mb-3">Cuenta del propietario (owner)</p>

                    <div class="mb-3">
                        <label class="form-label" for="owner-name">Nombre completo <span class="text-danger">*</span></label>
                        <input
                            id="owner-name"
                            class="form-control {form.errors.name ? 'is-invalid' : ''}"
                            type="text"
                            bind:value={form.name}
                            placeholder="Ej. Carlos Martínez"
                            autocomplete="off"
                        />
                        {#if form.errors.name}<div class="invalid-feedback">{form.errors.name}</div>{/if}
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="owner-email">Correo electrónico <span class="text-danger">*</span></label>
                        <input
                            id="owner-email"
                            class="form-control {form.errors.email ? 'is-invalid' : ''}"
                            type="email"
                            bind:value={form.email}
                            placeholder="owner@farmacia.com"
                            autocomplete="off"
                        />
                        {#if form.errors.email}<div class="invalid-feedback">{form.errors.email}</div>{/if}
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="owner-password">Contraseña temporal <span class="text-danger">*</span></label>
                        <input
                            id="owner-password"
                            class="form-control {form.errors.password ? 'is-invalid' : ''}"
                            type="password"
                            bind:value={form.password}
                            autocomplete="new-password"
                        />
                        {#if form.errors.password}<div class="invalid-feedback">{form.errors.password}</div>{/if}
                        <div class="form-text">Mínimo 8 caracteres. El usuario puede cambiarla desde su perfil.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="owner-password-confirm">Confirmar contraseña <span class="text-danger">*</span></label>
                        <input
                            id="owner-password-confirm"
                            class="form-control {form.errors.password_confirmation ? 'is-invalid' : ''}"
                            type="password"
                            bind:value={form.password_confirmation}
                            autocomplete="new-password"
                        />
                        {#if form.errors.password_confirmation}<div class="invalid-feedback">{form.errors.password_confirmation}</div>{/if}
                    </div>
                </div>
            </div>

            <div class="taguara-drawer-footer">
                <button
                    class="btn btn-taguara w-100 d-inline-flex align-items-center justify-content-center gap-2"
                    type="button"
                    onclick={submit}
                    disabled={form.processing}
                >
                    {#if form.processing}
                        <span class="spinner-border spinner-border-sm"></span>
                        Creando...
                    {:else}
                        <Building2 size={17} />
                        Crear farmacia y cuenta owner
                    {/if}
                </button>
            </div>
        </aside>
    {/if}
</AppLayout>
