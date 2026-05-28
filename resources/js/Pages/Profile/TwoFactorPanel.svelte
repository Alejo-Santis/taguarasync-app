<script>
    import { useForm } from '@inertiajs/svelte';
    import { Eye, EyeOff, RefreshCw, Shield, ShieldCheck, ShieldOff } from '@lucide/svelte';

    let { twoFactorEnabled, twoFactorConfirmed } = $props();

    const enableForm = useForm({});
    const confirmForm = useForm({ code: '' });
    const disableForm = useForm({});
    const regenerateForm = useForm({});

    let qrCode = $state('');
    let secretKey = $state('');
    let recoveryCodes = $state([]);
    let showingRecoveryCodes = $state(false);
    let fetchingQr = $state(false);
    let fetchingCodes = $state(false);

    $effect(() => {
        if (twoFactorEnabled && !twoFactorConfirmed) {
            qrCode = '';
            secretKey = '';
            fetchQrCode();
        }
    });

    async function fetchQrCode() {
        fetchingQr = true;
        try {
            const [qrRes, secretRes] = await Promise.all([
                fetch('/user/two-factor-qr-code'),
                fetch('/user/two-factor-secret-key'),
            ]);
            const qrData = await qrRes.json();
            const secretData = await secretRes.json();
            qrCode = qrData.svg;
            secretKey = secretData.secretKey;
        } finally {
            fetchingQr = false;
        }
    }

    async function fetchRecoveryCodes() {
        fetchingCodes = true;
        try {
            const res = await fetch('/user/two-factor-recovery-codes');
            recoveryCodes = await res.json();
            showingRecoveryCodes = true;
        } finally {
            fetchingCodes = false;
        }
    }

    function enable() {
        enableForm.post('/user/two-factor-authentication', { preserveScroll: true });
    }

    function confirm() {
        confirmForm.post('/user/confirmed-two-factor-authentication', {
            preserveScroll: true,
            onSuccess: () => {
                confirmForm.reset();
                fetchRecoveryCodes();
            },
        });
    }

    function disable() {
        disableForm.delete('/user/two-factor-authentication', { preserveScroll: true });
    }

    function regenerate() {
        regenerateForm.post('/user/two-factor-recovery-codes', {
            preserveScroll: true,
            onSuccess: () => fetchRecoveryCodes(),
        });
    }
</script>

<div class="col-12">
    <div class="taguara-panel">
        <div class="taguara-panel-header">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-1">Seguridad avanzada</p>
                <h3 class="h5 mb-0">Autenticacion de dos factores</h3>
            </div>
            {#if twoFactorConfirmed}
                <ShieldCheck class="text-success" size={22} />
            {:else if twoFactorEnabled}
                <Shield class="text-warning" size={22} />
            {:else}
                <ShieldOff class="text-secondary" size={22} />
            {/if}
        </div>

        <div class="mb-4">
            {#if twoFactorConfirmed}
                <span class="badge text-bg-success">Habilitado</span>
                <p class="text-secondary small mt-2 mb-0">
                    Tu cuenta esta protegida con autenticacion de dos factores.
                </p>
            {:else if twoFactorEnabled}
                <span class="badge text-bg-warning text-dark">Pendiente de confirmacion</span>
                <p class="text-secondary small mt-2 mb-0">
                    Escanea el codigo QR con tu aplicacion autenticadora y confirma con el codigo generado.
                </p>
            {:else}
                <span class="badge text-bg-secondary">Deshabilitado</span>
                <p class="text-secondary small mt-2 mb-0">
                    Agrega una capa extra de seguridad a tu cuenta activando la autenticacion de dos factores.
                </p>
            {/if}
        </div>

        <!-- QR de configuracion -->
        {#if twoFactorEnabled && !twoFactorConfirmed}
            <div class="mb-4">
                {#if fetchingQr}
                    <div class="d-flex align-items-center gap-2 text-secondary">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        Cargando codigo QR...
                    </div>
                {:else if qrCode}
                    <div class="d-flex flex-column align-items-start gap-3">
                        <div class="border rounded p-3 bg-white" style="display:inline-block">
                            {@html qrCode}
                        </div>
                        {#if secretKey}
                            <div>
                                <p class="small text-secondary mb-1">O ingresa esta clave manualmente en tu aplicacion:</p>
                                <code class="d-block p-2 bg-light rounded border small user-select-all">{secretKey}</code>
                            </div>
                        {/if}
                    </div>
                {/if}
            </div>

            <form
                class="d-flex align-items-start gap-2 flex-wrap"
                onsubmit={(e) => { e.preventDefault(); confirm(); }}
            >
                <div style="min-width:200px">
                    <input
                        class="form-control"
                        class:is-invalid={confirmForm.errors.code}
                        type="text"
                        inputmode="numeric"
                        placeholder="Codigo de 6 digitos"
                        bind:value={confirmForm.code}
                        autocomplete="one-time-code"
                    />
                    {#if confirmForm.errors.code}
                        <div class="invalid-feedback">{confirmForm.errors.code}</div>
                    {/if}
                </div>
                <button class="btn btn-taguara" type="submit" disabled={confirmForm.processing}>
                    {confirmForm.processing ? 'Confirmando...' : 'Confirmar'}
                </button>
                <button
                    class="btn btn-outline-secondary"
                    type="button"
                    onclick={disable}
                    disabled={disableForm.processing}
                >
                    Cancelar
                </button>
            </form>
        {/if}

        <!-- Codigos de recuperacion -->
        {#if twoFactorConfirmed && showingRecoveryCodes && recoveryCodes.length > 0}
            <div class="mb-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <p class="small fw-semibold mb-0">Codigos de recuperacion</p>
                    <button
                        class="btn btn-sm btn-link p-0 text-secondary"
                        type="button"
                        onclick={() => { showingRecoveryCodes = false; }}
                    >
                        <EyeOff size={14} class="me-1" /> Ocultar
                    </button>
                </div>
                <p class="text-secondary small mb-3">
                    Guarda estos codigos en un lugar seguro. Cada codigo solo puede usarse una vez para acceder a tu cuenta si pierdes tu dispositivo autenticador.
                </p>
                <div class="border rounded p-3 bg-light font-monospace small lh-lg">
                    {#each recoveryCodes as code}
                        <div class="user-select-all">{code}</div>
                    {/each}
                </div>
            </div>
        {/if}

        <!-- Acciones segun estado -->
        {#if twoFactorConfirmed}
            <div class="d-flex align-items-center flex-wrap gap-2">
                {#if !showingRecoveryCodes}
                    <button
                        class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1"
                        type="button"
                        onclick={fetchRecoveryCodes}
                        disabled={fetchingCodes}
                    >
                        <Eye size={14} />
                        {fetchingCodes ? 'Cargando...' : 'Ver codigos de recuperacion'}
                    </button>
                {/if}
                <button
                    class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1"
                    type="button"
                    onclick={regenerate}
                    disabled={regenerateForm.processing}
                >
                    <RefreshCw size={14} />
                    {regenerateForm.processing ? 'Regenerando...' : 'Regenerar codigos'}
                </button>
                <button
                    class="btn btn-outline-danger btn-sm"
                    type="button"
                    onclick={disable}
                    disabled={disableForm.processing}
                >
                    {disableForm.processing ? 'Deshabilitando...' : 'Deshabilitar 2FA'}
                </button>
            </div>
        {:else if !twoFactorEnabled}
            <button
                class="btn btn-taguara d-inline-flex align-items-center gap-2"
                type="button"
                onclick={enable}
                disabled={enableForm.processing}
            >
                <Shield size={16} />
                {enableForm.processing ? 'Habilitando...' : 'Habilitar 2FA'}
            </button>
        {/if}
    </div>
</div>
