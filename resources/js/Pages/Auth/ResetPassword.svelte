<script>
    import { useForm } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import GuestLayout from '../../Layouts/GuestLayout.svelte';

    const props = $props();

    const form = untrack(() => useForm({
        token: props.token ?? '',
        email: props.email ?? '',
        password: '',
        password_confirmation: '',
    }));

    const submit = () => {
        form.post('/reset-password', {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    };
</script>

<GuestLayout title="Nueva contrasena" subtitle="Define una contrasena segura para continuar.">
    <form onsubmit={(event) => { event.preventDefault(); submit(); }}>
        <div class="mb-3">
            <label class="form-label" for="email">Correo electronico</label>
            <input id="email" class:is-invalid={form.errors.email} class="form-control" type="email" bind:value={form.email} autocomplete="username">
            {#if form.errors.email}
                <div class="invalid-feedback">{form.errors.email}</div>
            {/if}
        </div>

        <div class="mb-3">
            <label class="form-label" for="password">Contrasena</label>
            <input id="password" class:is-invalid={form.errors.password} class="form-control" type="password" bind:value={form.password} autocomplete="new-password">
            {#if form.errors.password}
                <div class="invalid-feedback">{form.errors.password}</div>
            {/if}
        </div>

        <div class="mb-4">
            <label class="form-label" for="password_confirmation">Confirmar contrasena</label>
            <input id="password_confirmation" class:is-invalid={form.errors.password_confirmation} class="form-control" type="password" bind:value={form.password_confirmation} autocomplete="new-password">
            {#if form.errors.password_confirmation}
                <div class="invalid-feedback">{form.errors.password_confirmation}</div>
            {/if}
        </div>

        <button class="btn btn-taguara w-100" type="submit" disabled={form.processing}>
            Guardar contrasena
        </button>
    </form>
</GuestLayout>
