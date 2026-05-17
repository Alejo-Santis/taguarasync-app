<script>
    import { Link, useForm } from '@inertiajs/svelte';
    import GuestLayout from '../../Layouts/GuestLayout.svelte';
    import PasswordInput from '../../Components/UI/PasswordInput.svelte';

    const form = useForm({
        tenant_name: '',
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = () => {
        form.post('/register', {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    };
</script>

<GuestLayout title="Crear cuenta" subtitle="Registra el primer usuario administrativo de la farmacia.">
    <form onsubmit={(event) => { event.preventDefault(); submit(); }}>
        <div class="mb-3">
            <label class="form-label" for="tenant_name">Nombre de la farmacia</label>
            <input id="tenant_name" class:is-invalid={form.errors.tenant_name} class="form-control" type="text" bind:value={form.tenant_name} autocomplete="organization">
            {#if form.errors.tenant_name}
                <div class="invalid-feedback">{form.errors.tenant_name}</div>
            {/if}
        </div>

        <div class="mb-3">
            <label class="form-label" for="name">Nombre</label>
            <input id="name" class:is-invalid={form.errors.name} class="form-control" type="text" bind:value={form.name} autocomplete="name">
            {#if form.errors.name}
                <div class="invalid-feedback">{form.errors.name}</div>
            {/if}
        </div>

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
                autocomplete="new-password"
            />
        </div>

        <div class="mb-4">
            <PasswordInput
                id="password_confirmation"
                label="Confirmar contrasena"
                bind:value={form.password_confirmation}
                error={form.errors.password_confirmation}
                autocomplete="new-password"
            />
        </div>

        <button class="btn btn-taguara w-100" type="submit" disabled={form.processing}>
            Crear cuenta
        </button>
    </form>

    <p class="text-center text-secondary small mt-4 mb-0">
        Ya tienes cuenta?
        <Link href="/login">Iniciar sesion</Link>
    </p>
</GuestLayout>
