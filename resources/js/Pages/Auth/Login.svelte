<script>
    import { Link, useForm } from '@inertiajs/svelte';
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

<GuestLayout title="Iniciar sesion" subtitle="Ingresa con tu cuenta para continuar.">
    <form onsubmit={(event) => { event.preventDefault(); submit(); }}>
        <div class="mb-3">
            <label class="form-label" for="email">Correo electronico</label>
            <input id="email" class:is-invalid={form.errors.email} class="form-control" type="email" bind:value={form.email} autocomplete="username">
            {#if form.errors.email}
                <div class="invalid-feedback">{form.errors.email}</div>
            {/if}
        </div>

        <div class="mb-3">
            <PasswordInput
                id="password"
                label="Contrasena"
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

            <Link class="small" href="/forgot-password">Olvide mi contrasena</Link>
        </div>

        <button class="btn btn-taguara w-100" type="submit" disabled={form.processing}>
            Entrar
        </button>
    </form>

    <!-- <p class="text-center text-secondary small mt-4 mb-0">
        Aun no tienes cuenta?
        <Link href="/register">Crear cuenta</Link>
    </p> -->
</GuestLayout>
