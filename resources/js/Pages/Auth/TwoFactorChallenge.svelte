<script>
    import { useForm } from '@inertiajs/svelte';
    import GuestLayout from '../../Layouts/GuestLayout.svelte';

    const form = useForm({
        code: '',
        recovery_code: '',
    });

    let usingRecoveryCode = $state(false);

    const submit = () => {
        form.post('/two-factor-challenge');
    };
</script>

<GuestLayout title="Verificacion en dos pasos" subtitle="Confirma el codigo de tu aplicacion autenticadora o usa un codigo de recuperacion.">
    <form onsubmit={(event) => { event.preventDefault(); submit(); }}>
        {#if usingRecoveryCode}
            <div class="mb-4">
                <label class="form-label" for="recovery_code">Codigo de recuperacion</label>
                <input id="recovery_code" class:is-invalid={$form.errors.recovery_code} class="form-control" type="text" bind:value={$form.recovery_code} autocomplete="one-time-code">
                {#if $form.errors.recovery_code}
                    <div class="invalid-feedback">{$form.errors.recovery_code}</div>
                {/if}
            </div>
        {:else}
            <div class="mb-4">
                <label class="form-label" for="code">Codigo de autenticacion</label>
                <input id="code" class:is-invalid={$form.errors.code} class="form-control" type="text" inputmode="numeric" bind:value={$form.code} autocomplete="one-time-code">
                {#if $form.errors.code}
                    <div class="invalid-feedback">{$form.errors.code}</div>
                {/if}
            </div>
        {/if}

        <button class="btn btn-taguara w-100" type="submit" disabled={$form.processing}>
            Confirmar
        </button>

        <button class="btn btn-link w-100 mt-3" type="button" onclick={() => { usingRecoveryCode = !usingRecoveryCode; }}>
            {usingRecoveryCode ? 'Usar codigo de autenticacion' : 'Usar codigo de recuperacion'}
        </button>
    </form>
</GuestLayout>
