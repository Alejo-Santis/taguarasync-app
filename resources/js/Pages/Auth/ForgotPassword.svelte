<script>
    import { Link, useForm } from '@inertiajs/svelte';
    import GuestLayout from '../../Layouts/GuestLayout.svelte';

    const form = useForm({
        email: '',
    });

    const submit = () => {
        form.post('/forgot-password');
    };
</script>

<GuestLayout title="Recuperar acceso" subtitle="Te enviaremos un enlace para restablecer la contrasena.">
    <form onsubmit={(event) => { event.preventDefault(); submit(); }}>
        <div class="mb-4">
            <label class="form-label" for="email">Correo electronico</label>
            <input id="email" class:is-invalid={form.errors.email} class="form-control" type="email" bind:value={form.email} autocomplete="username">
            {#if form.errors.email}
                <div class="invalid-feedback">{form.errors.email}</div>
            {/if}
        </div>

        <button class="btn btn-taguara w-100" type="submit" disabled={form.processing}>
            Enviar enlace
        </button>
    </form>

    <p class="text-center small mt-4 mb-0">
        <Link href="/login">Volver al inicio de sesion</Link>
    </p>
</GuestLayout>
