<script>
    import { useForm } from '@inertiajs/svelte';
    import GuestLayout from '../../Layouts/GuestLayout.svelte';

    const form = useForm({
        password: '',
    });

    const submit = () => {
        form.post('/user/confirm-password', {
            onFinish: () => form.reset('password'),
        });
    };
</script>

<GuestLayout title="Confirmar contrasena" subtitle="Por seguridad, confirma tu contrasena antes de continuar.">
    <form onsubmit={(event) => { event.preventDefault(); submit(); }}>
        <div class="mb-4">
            <label class="form-label" for="password">Contrasena</label>
            <input id="password" class:is-invalid={form.errors.password} class="form-control" type="password" bind:value={form.password} autocomplete="current-password">
            {#if form.errors.password}
                <div class="invalid-feedback">{form.errors.password}</div>
            {/if}
        </div>

        <button class="btn btn-taguara w-100" type="submit" disabled={form.processing}>
            Confirmar
        </button>
    </form>
</GuestLayout>
