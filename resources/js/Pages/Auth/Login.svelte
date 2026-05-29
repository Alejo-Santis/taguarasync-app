<script>
    import { Link, useForm } from '@inertiajs/svelte';
    import { Mail, LogIn } from '@lucide/svelte';
    import GuestLayout from '../../Layouts/GuestLayout.svelte';
    import PasswordInput from '../../Components/UI/PasswordInput.svelte';

    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = () => {
        form.post('/login', {
            onFinish: () => form.reset('password'),
        });
    };
</script>

<GuestLayout title="Iniciar sesión" subtitle="Ingresa con tu cuenta para continuar.">
    <form onsubmit={(event) => { event.preventDefault(); submit(); }}>
        <div class="mb-3">
            <label class="form-label" for="email">Correo electrónico</label>
            <div class="input-group">
                <span class="input-group-text taguara-input-icon">
                    <Mail size={16} />
                </span>
                <input
                    id="email"
                    class:is-invalid={form.errors.email}
                    class="form-control"
                    type="email"
                    bind:value={form.email}
                    autocomplete="username"
                    placeholder="correo@empresa.com"
                >
            </div>
            {#if form.errors.email}
                <div class="invalid-feedback d-block">{form.errors.email}</div>
            {/if}
        </div>

        <div class="mb-3">
            <PasswordInput
                id="password"
                label="Contraseña"
                bind:value={form.password}
                error={form.errors.password}
                autocomplete="current-password"
            />
        </div>

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="form-check">
                <input id="remember" class="form-check-input" type="checkbox" bind:checked={form.remember}>
                <label class="form-check-label" for="remember">Recordarme</label>
            </div>

            <Link class="small" href="/forgot-password">Olvidé mi contraseña</Link>
        </div>

        <button class="btn btn-taguara w-100 d-flex align-items-center justify-content-center gap-2" type="submit" disabled={form.processing}>
            <LogIn size={17} />
            {form.processing ? 'Entrando…' : 'Iniciar sesión'}
        </button>
    </form>

    <!-- <p class="text-center text-secondary small mt-4 mb-0">
        Aun no tienes cuenta?
        <Link href="/register">Crear cuenta</Link>
    </p> -->
</GuestLayout>
