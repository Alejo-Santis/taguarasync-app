<script>
    import { page } from '@inertiajs/svelte';

    const labels = {
        success: 'Listo',
        error: 'Error',
        warning: 'Atencion',
        info: 'Informacion',
        status: 'Listo',
        message: 'Mensaje',
    };

    const classes = {
        success: 'text-bg-success',
        error: 'text-bg-danger',
        warning: 'text-bg-warning',
        info: 'text-bg-info',
        status: 'text-bg-success',
        message: 'text-bg-primary',
    };

    let messages = $state([]);
    let lastSignature = $state('');

    const normalizeFlash = (flash = {}) => Object.entries(flash)
        .filter(([, message]) => Boolean(message))
        .map(([type, message]) => ({
            id: `${type}-${Date.now()}-${Math.random().toString(16).slice(2)}`,
            type,
            title: labels[type] ?? 'Mensaje',
            message,
        }));

    const dismiss = (id) => {
        messages = messages.filter((message) => message.id !== id);
    };

    $effect(() => {
        const flash = page.props.flash ?? {};
        const signature = JSON.stringify(flash);

        if (!signature || signature === '{}' || signature === lastSignature) {
            return;
        }

        lastSignature = signature;
        const nextMessages = normalizeFlash(flash);

        if (nextMessages.length === 0) {
            return;
        }

        messages = [...messages, ...nextMessages];

        nextMessages.forEach((message) => {
            setTimeout(() => dismiss(message.id), 4500);
        });
    });
</script>

{#if messages.length > 0}
    <div class="taguara-toast-stack" aria-live="polite" aria-atomic="true">
        {#each messages as message (message.id)}
            <div class={`toast show border-0 shadow-sm ${classes[message.type] ?? 'text-bg-primary'}`} role="status">
                <div class="d-flex">
                    <div class="toast-body">
                        <strong class="d-block">{message.title}</strong>
                        <span>{message.message}</span>
                    </div>
                    <button
                        class="btn-close btn-close-white me-2 m-auto"
                        type="button"
                        aria-label="Cerrar"
                        onclick={() => dismiss(message.id)}
                    ></button>
                </div>
            </div>
        {/each}
    </div>
{/if}
