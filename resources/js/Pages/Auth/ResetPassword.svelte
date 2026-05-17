<script>
    import { useForm } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import GuestLayout from '../../Layouts/GuestLayout.svelte';
    import PasswordInput from '../../Components/UI/PasswordInput.svelte';

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
            Guardar contrasena
        </button>
    </form>
</GuestLayout>
