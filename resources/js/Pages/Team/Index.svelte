<script>
    import { router, useForm } from '@inertiajs/svelte';
    import { fade, fly } from 'svelte/transition';
    import { KeyRound, Pencil, Plus, RefreshCw, ShieldCheck, UserCircle, Users, X } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';

    let { auth, members, availableRoles } = $props();

    let drawerOpen = $state(false);
    let drawerMode = $state('invite'); // 'invite' | 'edit'
    let editingMember = $state(null);
    let resetConfirm = $state(null);

    const inviteForm = useForm({ name: '', email: '', role: availableRoles[0]?.name ?? 'cashier' });
    const editForm = useForm({ name: '', role: 'cashier' });

    $effect(() => {
        document.body.style.overflow = drawerOpen ? 'hidden' : '';
        return () => { document.body.style.overflow = ''; };
    });

    const handleKeydown = (e) => { if (e.key === 'Escape') closeDrawer(); };

    const openInvite = () => {
        drawerMode = 'invite';
        editingMember = null;
        inviteForm.reset();
        drawerOpen = true;
    };

    const openEdit = (member) => {
        drawerMode = 'edit';
        editingMember = member;
        editForm.name = member.name;
        editForm.role = member.role;
        drawerOpen = true;
    };

    const closeDrawer = () => {
        drawerOpen = false;
        editingMember = null;
        inviteForm.reset();
        inviteForm.clearErrors();
        editForm.reset();
        editForm.clearErrors();
    };

    const submitInvite = () => {
        inviteForm.post('/team', { onSuccess: closeDrawer, preserveScroll: true });
    };

    const submitEdit = () => {
        editForm.put(`/team/${editingMember.id}`, { onSuccess: closeDrawer, preserveScroll: true });
    };

    const resetPassword = (member) => {
        resetConfirm = member;
    };

    const confirmReset = () => {
        if (!resetConfirm) return;
        router.post(`/team/${resetConfirm.id}/reset-password`, {}, {
            onSuccess: () => { resetConfirm = null; },
            preserveScroll: true,
        });
    };

    const roleColor = (role) => {
        const map = {
            owner: 'text-bg-success',
            admin: 'text-bg-primary',
            cashier: 'text-bg-info',
            warehouse: 'text-bg-warning',
            accountant: 'text-bg-secondary',
        };
        return map[role] ?? 'text-bg-light';
    };
</script>

<svelte:window onkeydown={handleKeydown} />

<AppLayout title="Equipo" activeSection="team" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Equipo</p>
                <h2 class="h3 mb-2">Usuarios del establecimiento</h2>
                <p class="text-secondary mb-0">Administra los accesos y roles de todos los usuarios de la farmacia.</p>
            </div>
            <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="button" onclick={openInvite}>
                <Plus size={18} />
                Agregar usuario
            </button>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Miembros</p>
                    <h3 class="h5 mb-0">{members.length} usuarios registrados</h3>
                </div>
                <Users class="text-secondary" size={22} />
            </div>

            <div class="taguara-table-wrapper">
                <table class="taguara-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Correo</th>
                            <th>Rol</th>
                            <th>Verificado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each members as member}
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="taguara-kpi-icon text-bg-light border" style="width:36px;height:36px">
                                            <UserCircle size={18} class="text-secondary" />
                                        </span>
                                        <div>
                                            <div class="taguara-table-name">
                                                {member.name}
                                                {#if member.is_current}
                                                    <span class="badge text-bg-success ms-1" style="font-size:.65rem">Tu</span>
                                                {/if}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-secondary" style="font-size:.875rem">{member.email}</td>
                                <td>
                                    <span class={`badge ${roleColor(member.role)}`}>{member.role_label}</span>
                                </td>
                                <td class="text-secondary" style="font-size:.875rem">
                                    {member.email_verified_at ?? 'Pendiente'}
                                </td>
                                <td>
                                    <div class="d-flex gap-1 justify-content-end">
                                        <button
                                            class="btn btn-sm btn-light border taguara-icon-button-sm"
                                            type="button"
                                            title="Editar usuario"
                                            onclick={() => openEdit(member)}
                                        >
                                            <Pencil size={15} />
                                        </button>
                                        {#if !member.is_current}
                                            <button
                                                class="btn btn-sm btn-light border taguara-icon-button-sm"
                                                type="button"
                                                title="Restablecer contrasena"
                                                onclick={() => resetPassword(member)}
                                            >
                                                <KeyRound size={15} />
                                            </button>
                                        {/if}
                                    </div>
                                </td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="5">
                                    <div class="taguara-empty-state">
                                        <Users size={34} />
                                        <h4 class="h6 mb-1">Sin usuarios registrados</h4>
                                        <p class="text-secondary mb-0">Agrega los usuarios que gestionaran la farmacia.</p>
                                    </div>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Info de roles -->
        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Roles disponibles</p>
                    <h3 class="h5 mb-0">Niveles de acceso</h3>
                </div>
                <ShieldCheck class="text-success" size={22} />
            </div>
            <div class="row g-2">
                {#each [
                    { role: 'owner', label: 'Propietario', desc: 'Acceso total al sistema incluida configuracion y equipo.' },
                    { role: 'admin', label: 'Administrador', desc: 'Gestion operativa completa excepto configuracion del tenant.' },
                    { role: 'cashier', label: 'Cajero', desc: 'POS, apertura/cierre de caja y consulta de inventario.' },
                    { role: 'warehouse', label: 'Bodeguero', desc: 'Recepciones de compra, inventario y catalogo de productos.' },
                    { role: 'accountant', label: 'Contador', desc: 'Acceso de solo lectura a compras, ventas y reportes.' },
                ] as r}
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="d-flex gap-2 p-2 border rounded">
                            <span class={`badge ${roleColor(r.role)} align-self-start mt-1`}>{r.label}</span>
                            <p class="small text-secondary mb-0">{r.desc}</p>
                        </div>
                    </div>
                {/each}
            </div>
        </section>
    </div>

    <!-- Drawer crear/editar -->
    {#if drawerOpen}
        <div class="taguara-drawer-backdrop" transition:fade={{ duration: 150 }} onclick={closeDrawer} role="presentation"></div>
        <aside class="taguara-drawer" transition:fly={{ x: 480, duration: 220 }}>
            <div class="taguara-drawer-header">
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <div>
                        <p class="text-uppercase small fw-semibold text-success mb-1">Equipo</p>
                        <h2 class="h5 mb-0">{drawerMode === 'invite' ? 'Nuevo usuario' : editingMember?.name}</h2>
                    </div>
                    <button class="btn btn-light border taguara-icon-button flex-shrink-0" type="button" onclick={closeDrawer}>
                        <X size={17} />
                    </button>
                </div>
            </div>

            <div class="taguara-drawer-body">
                {#if drawerMode === 'invite'}
                    <form id="team-form" onsubmit={(e) => { e.preventDefault(); submitInvite(); }}>
                        <div class="vstack gap-3">
                            <div>
                                <label class="form-label" for="t-name">Nombre completo <span class="text-danger">*</span></label>
                                <input id="t-name" class="form-control" class:is-invalid={inviteForm.errors.name} type="text" bind:value={inviteForm.name} placeholder="Maria Garcia">
                                {#if inviteForm.errors.name}<div class="invalid-feedback">{inviteForm.errors.name}</div>{/if}
                            </div>
                            <div>
                                <label class="form-label" for="t-email">Correo electronico <span class="text-danger">*</span></label>
                                <input id="t-email" class="form-control" class:is-invalid={inviteForm.errors.email} type="email" bind:value={inviteForm.email} placeholder="maria@farmacia.com">
                                {#if inviteForm.errors.email}<div class="invalid-feedback">{inviteForm.errors.email}</div>{/if}
                                <div class="form-text">Se generara una contrasena temporal que deberas compartir.</div>
                            </div>
                            <div>
                                <label class="form-label" for="t-role">Rol <span class="text-danger">*</span></label>
                                <select id="t-role" class="form-select" class:is-invalid={inviteForm.errors.role} bind:value={inviteForm.role}>
                                    {#each availableRoles as r}
                                        <option value={r.name}>{r.label}</option>
                                    {/each}
                                </select>
                                {#if inviteForm.errors.role}<div class="invalid-feedback">{inviteForm.errors.role}</div>{/if}
                            </div>
                        </div>
                    </form>
                {:else}
                    <form id="team-form" onsubmit={(e) => { e.preventDefault(); submitEdit(); }}>
                        <div class="vstack gap-3">
                            <div>
                                <label class="form-label" for="te-name">Nombre completo</label>
                                <input id="te-name" class="form-control" class:is-invalid={editForm.errors.name} type="text" bind:value={editForm.name}>
                                {#if editForm.errors.name}<div class="invalid-feedback">{editForm.errors.name}</div>{/if}
                            </div>
                            <div>
                                <label class="form-label" for="te-role">Rol</label>
                                <select id="te-role" class="form-select" bind:value={editForm.role}>
                                    {#each availableRoles as r}
                                        <option value={r.name}>{r.label}</option>
                                    {/each}
                                </select>
                            </div>
                        </div>
                    </form>
                {/if}
            </div>

            <div class="taguara-drawer-footer">
                <button
                    class="btn btn-taguara w-100"
                    type="submit"
                    form="team-form"
                    disabled={drawerMode === 'invite' ? inviteForm.processing : editForm.processing}
                >
                    {drawerMode === 'invite'
                        ? (inviteForm.processing ? 'Creando...' : 'Crear usuario')
                        : (editForm.processing ? 'Guardando...' : 'Guardar cambios')}
                </button>
            </div>
        </aside>
    {/if}

    <!-- Modal confirmación reset password -->
    {#if resetConfirm}
        <div class="taguara-drawer-backdrop" transition:fade={{ duration: 150 }} onclick={() => resetConfirm = null} role="presentation"></div>
        <div class="taguara-pos-modal" transition:fly={{ y: -20, duration: 180 }}>
            <div class="taguara-pos-modal-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-warning mb-1">Contrasena</p>
                    <h2 class="h5 mb-0">Restablecer acceso de {resetConfirm.name}</h2>
                </div>
                <button class="btn btn-light border taguara-icon-button" type="button" onclick={() => resetConfirm = null}><X size={17} /></button>
            </div>
            <div class="taguara-pos-modal-body">
                <p class="text-secondary small mb-4">
                    Se generara una nueva contrasena aleatoria. La contrasena actual quedara invalida de inmediato.
                    Copia la nueva contrasena del mensaje de confirmacion y compartela con el usuario.
                </p>
                <div class="d-flex gap-2">
                    <button class="btn btn-light border flex-fill" type="button" onclick={() => resetConfirm = null}>Cancelar</button>
                    <button class="btn btn-warning flex-fill d-inline-flex align-items-center justify-content-center gap-2" type="button" onclick={confirmReset}>
                        <RefreshCw size={16} />
                        Confirmar reset
                    </button>
                </div>
            </div>
        </div>
    {/if}
</AppLayout>
