<script>
    import { useForm } from '@inertiajs/svelte';
    import { CheckCircle2, KeyRound, UserCircle } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';
    import PasswordInput from '../../Components/UI/PasswordInput.svelte';

    let { auth, user } = $props();

    const profileForm = useForm({
        name: user.name,
        email: user.email,
    });

    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    let profileSaved = $state(false);
    let passwordSaved = $state(false);

    const submitProfile = () => {
        profileForm.put('/user/profile-information', {
            onSuccess: () => {
                profileSaved = true;
                setTimeout(() => { profileSaved = false; }, 3000);
            },
            preserveScroll: true,
        });
    };

    const submitPassword = () => {
        passwordForm.put('/user/password', {
            onSuccess: () => {
                passwordSaved = true;
                passwordForm.reset();
                setTimeout(() => { passwordSaved = false; }, 3000);
            },
            onError: () => {},
            preserveScroll: true,
        });
    };
</script>

<AppLayout title="Mi perfil" activeSection="profile" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div class="d-flex align-items-center gap-3">
                <span class="taguara-kpi-icon text-bg-success" style="width:52px;height:52px">
                    <UserCircle size={28} />
                </span>
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Mi cuenta</p>
                    <h2 class="h3 mb-1">{auth?.user?.name ?? 'Usuario'}</h2>
                    <p class="text-secondary mb-0">{auth?.user?.email}</p>
                </div>
            </div>
        </section>

        <div class="row g-3">
            <!-- Información personal -->
            <div class="col-12 col-xl-6">
                <div class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Informacion</p>
                            <h3 class="h5 mb-0">Datos personales</h3>
                        </div>
                        <UserCircle class="text-success" size={22} />
                    </div>

                    <form class="vstack gap-3" onsubmit={(e) => { e.preventDefault(); submitProfile(); }}>
                        <div>
                            <label class="form-label" for="p-name">Nombre completo</label>
                            <input
                                id="p-name"
                                class="form-control"
                                class:is-invalid={profileForm.errors.name}
                                type="text"
                                bind:value={profileForm.name}
                            />
                            {#if profileForm.errors.name}
                                <div class="invalid-feedback">{profileForm.errors.name}</div>
                            {/if}
                        </div>

                        <div>
                            <label class="form-label" for="p-email">Correo electronico</label>
                            <input
                                id="p-email"
                                class="form-control"
                                class:is-invalid={profileForm.errors.email}
                                type="email"
                                bind:value={profileForm.email}
                            />
                            {#if profileForm.errors.email}
                                <div class="invalid-feedback">{profileForm.errors.email}</div>
                            {/if}
                        </div>

                        <div class="d-flex align-items-center gap-3">
                            <button
                                class="btn btn-taguara d-inline-flex align-items-center gap-2"
                                type="submit"
                                disabled={profileForm.processing}
                            >
                                {profileForm.processing ? 'Guardando...' : 'Actualizar perfil'}
                            </button>
                            {#if profileSaved}
                                <span class="d-inline-flex align-items-center gap-1 text-success small">
                                    <CheckCircle2 size={15} />
                                    Guardado
                                </span>
                            {/if}
                        </div>
                    </form>
                </div>
            </div>

            <!-- Cambio de contraseña -->
            <div class="col-12 col-xl-6">
                <div class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Seguridad</p>
                            <h3 class="h5 mb-0">Cambiar contrasena</h3>
                        </div>
                        <KeyRound class="text-success" size={22} />
                    </div>

                    <form class="vstack gap-3" onsubmit={(e) => { e.preventDefault(); submitPassword(); }}>
                        <div>
                            <PasswordInput
                                id="pw-current"
                                label="Contrasena actual"
                                bind:value={passwordForm.current_password}
                                error={passwordForm.errors.current_password}
                                autocomplete="current-password"
                            />
                        </div>

                        <div>
                            <PasswordInput
                                id="pw-new"
                                label="Nueva contrasena"
                                bind:value={passwordForm.password}
                                error={passwordForm.errors.password}
                                autocomplete="new-password"
                                showStrength
                            />
                        </div>

                        <div>
                            <PasswordInput
                                id="pw-confirm"
                                label="Confirmar contrasena"
                                bind:value={passwordForm.password_confirmation}
                                error={passwordForm.errors.password_confirmation}
                                autocomplete="new-password"
                            />
                        </div>

                        <div class="d-flex align-items-center gap-3">
                            <button
                                class="btn btn-taguara d-inline-flex align-items-center gap-2"
                                type="submit"
                                disabled={passwordForm.processing}
                            >
                                {passwordForm.processing ? 'Actualizando...' : 'Cambiar contrasena'}
                            </button>
                            {#if passwordSaved}
                                <span class="d-inline-flex align-items-center gap-1 text-success small">
                                    <CheckCircle2 size={15} />
                                    Contrasena actualizada
                                </span>
                            {/if}
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</AppLayout>
