<script>
    import { router, useForm } from '@inertiajs/svelte';
    import { fade, fly } from 'svelte/transition';
    import {
        AlertCircle,
        Building2,
        CheckCircle2,
        CircleDollarSign,
        Clock,
        Lock,
        Plus,
        ShieldCheck,
        Unlock,
        Users,
        X,
    } from '@lucide/svelte';
    import AppLayout from '../../../Layouts/AppLayout.svelte';

    let { auth, tenants, plans } = $props();

    // ── Crear tenant ──────────────────────────────────────────────────────────
    let drawerOpen = $state(false);

    const form = useForm(() => ({
        tenant_name: '',
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    }));

    const openDrawer = () => { form.reset(); form.clearErrors(); drawerOpen = true; };
    const closeDrawer = () => { drawerOpen = false; };
    const submit = () => {
        form.post('/admin/tenants', { onSuccess: () => { drawerOpen = false; form.reset(); } });
    };

    // ── Activar / Suspender ───────────────────────────────────────────────────
    const toggleStatus = (tenant) => {
        router.patch(`/admin/tenants/${tenant.uuid}/toggle-status`, {}, { preserveScroll: true });
    };

    // ── Registrar pago ────────────────────────────────────────────────────────
    let paymentDrawerOpen = $state(false);
    let selectedTenant = $state(null);

    const paymentForm = useForm(() => ({
        plan: selectedTenant?.plan?.value ?? plans[1]?.value ?? '',
        billing_cycle: selectedTenant?.billing_cycle ?? 'monthly',
    }));

    const openPaymentDrawer = (tenant) => {
        selectedTenant = tenant;
        paymentForm.plan = tenant.plan?.value ?? plans[1]?.value ?? '';
        paymentForm.billing_cycle = tenant.billing_cycle ?? 'monthly';
        paymentForm.clearErrors();
        paymentDrawerOpen = true;
    };

    const closePaymentDrawer = () => { paymentDrawerOpen = false; selectedTenant = null; };

    const submitPayment = () => {
        paymentForm.post(`/admin/tenants/${selectedTenant.uuid}/record-payment`, {
            preserveScroll: true,
            onSuccess: () => closePaymentDrawer(),
        });
    };

    const selectedPlanInfo = $derived(plans.find(p => p.value === paymentForm.plan));
    const estimatedAmount = $derived(
        selectedPlanInfo
            ? paymentForm.billing_cycle === 'annual'
                ? Math.round(selectedPlanInfo.monthly_price * 12 * 0.85)
                : selectedPlanInfo.monthly_price
            : 0
    );

    // ── Helpers de UI ─────────────────────────────────────────────────────────
    const statusClass = (value) => {
        if (value === 'active') return 'text-bg-success';
        if (value === 'suspended') return 'text-bg-warning text-dark';
        return 'text-bg-secondary';
    };

    const billingBadge = (bs) => ({
        active:        { cls: 'text-bg-success',           label: 'Al día' },
        expiring_soon: { cls: 'text-bg-warning text-dark', label: 'Por vencer' },
        grace:         { cls: 'text-bg-danger',            label: 'Gracia' },
        expired:       { cls: 'text-bg-danger',            label: 'Vencido' },
        trial:         { cls: 'text-bg-info text-dark',    label: 'Prueba' },
        suspended:     { cls: 'text-bg-secondary',         label: 'Suspendido' },
        no_subscription: { cls: 'text-bg-light border text-secondary', label: 'Sin plan' },
    }[bs] ?? { cls: 'text-bg-secondary', label: bs });

    const formatCOP = (n) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(n);

    // ── Stats ─────────────────────────────────────────────────────────────────
    const statsAlDia      = $derived(tenants.filter(t => t.billing_status === 'active').length);
    const statsPorVencer  = $derived(tenants.filter(t => t.billing_status === 'expiring_soon').length);
    const statsVencidos   = $derived(tenants.filter(t => ['expired', 'grace'].includes(t.billing_status)).length);
    const statsSuspendidos = $derived(tenants.filter(t => t.status.value === 'suspended').length);
</script>

<AppLayout title="Administración de tenants" activeSection="admin" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-danger mb-2">Super admin</p>
                <h2 class="h3 mb-2">Farmacias registradas</h2>
                <p class="text-secondary mb-0">
                    Gestiona los tenants, planes y cobros del sistema.
                </p>
            </div>
            <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="button" onclick={openDrawer}>
                <Plus size={18} />
                Nueva farmacia
            </button>
        </section>

        <!-- Stats -->
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
                        <p class="text-secondary small mb-1">Al día</p>
                        <p class="h3 mb-0">{statsAlDia}</p>
                    </div>
                </article>
            </div>
            <div class="col-6 col-md-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon {statsPorVencer > 0 ? 'text-bg-warning' : 'text-bg-light border'}">
                        <Clock size={20} />
                    </span>
                    <div>
                        <p class="text-secondary small mb-1">Por vencer</p>
                        <p class="h3 mb-0 {statsPorVencer > 0 ? 'text-warning' : ''}">{statsPorVencer}</p>
                    </div>
                </article>
            </div>
            <div class="col-6 col-md-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon {statsVencidos > 0 ? 'text-bg-danger' : 'text-bg-light border'}">
                        <Lock size={20} />
                    </span>
                    <div>
                        <p class="text-secondary small mb-1">Vencidos / Suspendidos</p>
                        <p class="h3 mb-0 {statsVencidos > 0 ? 'text-danger' : ''}">{statsVencidos + statsSuspendidos}</p>
                    </div>
                </article>
            </div>
        </section>

        <!-- Tabla -->
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
                                <th>Plan</th>
                                <th>Suscripción</th>
                                <th>Último pago</th>
                                <th class="text-center">Usuarios</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each tenants as tenant}
                                {@const billing = billingBadge(tenant.billing_status)}
                                <tr class={['expired', 'grace'].includes(tenant.billing_status) ? 'table-danger' : tenant.billing_status === 'expiring_soon' ? 'table-warning' : ''}>
                                    <td>
                                        <div class="taguara-table-name">{tenant.name}</div>
                                        <div class="taguara-table-sub">{tenant.email ?? '—'}</div>
                                    </td>
                                    <td>
                                        {#if tenant.plan}
                                            <span class="badge text-bg-light border text-secondary">{tenant.plan.label}</span>
                                            {#if tenant.billing_cycle === 'annual'}
                                                <span class="badge text-bg-info text-dark ms-1">Anual</span>
                                            {/if}
                                        {:else}
                                            <span class="text-secondary small">—</span>
                                        {/if}
                                    </td>
                                    <td>
                                        <span class="badge {billing.cls}">{billing.label}</span>
                                        {#if tenant.subscribed_until}
                                            <div class="taguara-table-sub mt-1">hasta {tenant.subscribed_until}</div>
                                        {:else if tenant.trial_ends_at}
                                            <div class="taguara-table-sub mt-1">prueba hasta {tenant.trial_ends_at}</div>
                                        {/if}
                                    </td>
                                    <td class="taguara-table-sub">
                                        {tenant.last_payment_at ?? '—'}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge text-bg-light border text-secondary">{tenant.users_count}</span>
                                    </td>
                                    <td>
                                        <span class="badge {statusClass(tenant.status.value)}">{tenant.status.label}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <button
                                                class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-1"
                                                type="button"
                                                onclick={() => openPaymentDrawer(tenant)}
                                                title="Registrar pago"
                                            >
                                                <CircleDollarSign size={13} /> Pago
                                            </button>
                                            <button
                                                class="btn btn-sm {tenant.status.value === 'active' ? 'btn-outline-warning' : 'btn-outline-success'} d-inline-flex align-items-center gap-1"
                                                type="button"
                                                onclick={() => toggleStatus(tenant)}
                                                title={tenant.status.value === 'active' ? 'Suspender acceso' : 'Reactivar acceso'}
                                            >
                                                {#if tenant.status.value === 'active'}
                                                    <Lock size={13} />
                                                {:else}
                                                    <Unlock size={13} />
                                                {/if}
                                            </button>
                                        </div>
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

    <!-- Drawer: registrar pago -->
    {#if paymentDrawerOpen}
        <div class="taguara-drawer-backdrop" transition:fade={{ duration: 150 }} onclick={closePaymentDrawer} role="presentation"></div>
        <aside class="taguara-drawer" transition:fly={{ x: 480, duration: 220 }}>
            <div class="taguara-drawer-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="taguara-kpi-icon text-bg-success"><CircleDollarSign size={18} /></span>
                    <div>
                        <p class="text-uppercase small fw-semibold text-success mb-0">Cobro</p>
                        <h2 class="h5 mb-0">Registrar pago</h2>
                    </div>
                </div>
                <button class="btn btn-light border taguara-icon-button" type="button" onclick={closePaymentDrawer}><X size={17} /></button>
            </div>

            <div class="taguara-drawer-body">
                {#if selectedTenant}
                    <div class="alert alert-light border mb-4 d-flex align-items-center gap-2">
                        <Building2 size={16} class="flex-shrink-0 text-success" />
                        <div>
                            <div class="fw-semibold">{selectedTenant.name}</div>
                            {#if selectedTenant.subscribed_until}
                                <div class="small text-secondary">Actualmente activo hasta {selectedTenant.subscribed_until}</div>
                            {:else}
                                <div class="small text-secondary">Sin suscripción activa</div>
                            {/if}
                        </div>
                    </div>
                {/if}

                <div class="mb-4">
                    <p class="form-label fw-semibold mb-2">Plan <span class="text-danger">*</span></p>
                    <div class="vstack gap-2">
                        {#each plans as plan}
                            <label class="d-flex align-items-center gap-3 border rounded p-3 cursor-pointer {paymentForm.plan === plan.value ? 'border-success bg-light' : ''}">
                                <input
                                    class="form-check-input flex-shrink-0 mt-0"
                                    type="radio"
                                    name="plan"
                                    value={plan.value}
                                    bind:group={paymentForm.plan}
                                />
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">{plan.label}</div>
                                    <div class="small text-secondary">{formatCOP(plan.monthly_price)}/mes</div>
                                </div>
                            </label>
                        {/each}
                    </div>
                    {#if paymentForm.errors.plan}
                        <div class="text-danger small mt-1">{paymentForm.errors.plan}</div>
                    {/if}
                </div>

                <div class="mb-4">
                    <p class="form-label fw-semibold mb-2">Ciclo de facturación <span class="text-danger">*</span></p>
                    <div class="d-flex gap-2">
                        <label class="flex-fill d-flex align-items-center gap-2 border rounded p-3 cursor-pointer {paymentForm.billing_cycle === 'monthly' ? 'border-success bg-light' : ''}">
                            <input class="form-check-input mt-0" type="radio" name="billing_cycle" value="monthly" bind:group={paymentForm.billing_cycle} />
                            <div>
                                <div class="fw-semibold small">Mensual</div>
                                <div class="text-secondary" style="font-size:.75rem">1 mes</div>
                            </div>
                        </label>
                        <label class="flex-fill d-flex align-items-center gap-2 border rounded p-3 cursor-pointer {paymentForm.billing_cycle === 'annual' ? 'border-success bg-light' : ''}">
                            <input class="form-check-input mt-0" type="radio" name="billing_cycle" value="annual" bind:group={paymentForm.billing_cycle} />
                            <div>
                                <div class="fw-semibold small">Anual <span class="badge text-bg-success ms-1" style="font-size:.65rem">-15%</span></div>
                                <div class="text-secondary" style="font-size:.75rem">12 meses</div>
                            </div>
                        </label>
                    </div>
                </div>

                {#if selectedPlanInfo}
                    <div class="border rounded p-3 bg-light mb-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-secondary small">Valor a cobrar</span>
                            <span class="h5 mb-0 text-success fw-bold">{formatCOP(estimatedAmount)}</span>
                        </div>
                        {#if paymentForm.billing_cycle === 'annual'}
                            <div class="small text-secondary mt-1">12 meses · {formatCOP(selectedPlanInfo.monthly_price * 12)} → {formatCOP(estimatedAmount)} (15% descuento)</div>
                        {:else}
                            <div class="small text-secondary mt-1">+ IVA 19%</div>
                        {/if}
                    </div>
                {/if}
            </div>

            <div class="taguara-drawer-footer">
                <button
                    class="btn btn-taguara w-100 d-inline-flex align-items-center justify-content-center gap-2"
                    type="button"
                    onclick={submitPayment}
                    disabled={paymentForm.processing}
                >
                    {#if paymentForm.processing}
                        <span class="spinner-border spinner-border-sm"></span>
                        Registrando...
                    {:else}
                        <CircleDollarSign size={17} />
                        Confirmar pago recibido
                    {/if}
                </button>
            </div>
        </aside>
    {/if}

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
