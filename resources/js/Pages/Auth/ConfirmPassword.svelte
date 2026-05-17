<script>
    import { useForm } from '@inertiajs/svelte';
    import GuestLayout from '../../Layouts/GuestLayout.svelte';
    import PasswordInput from '../../Components/UI/PasswordInput.svelte';

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
            <PasswordInput
                id="password"
                label="Contrasena"
                bind:value={form.password}
                error={form.errors.password}
                autocomplete="current-password"
            />
        </div>

        <button class="btn btn-taguara w-100" type="submit" disabled={form.processing}>
            Confirmar
        </button>
    </form>
</GuestLayout>
