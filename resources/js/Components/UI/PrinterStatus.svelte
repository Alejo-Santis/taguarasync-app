<script>
    import { onDestroy, onMount } from 'svelte';
    import { Printer, PrinterCheck, PrinterX, Loader } from '@lucide/svelte';
    import QzPrinter from '../../Services/QzPrinter.js';

    /**
     * Indicador de conexión QZ Tray para la barra lateral o el POS.
     * Props:
     *   compact — modo icono pequeño (para sidebar)
     *   showLabel — mostrar texto junto al ícono
     */
    let { compact = false, showLabel = true } = $props();

    let status = $state('idle');    // 'idle' | 'connecting' | 'connected' | 'error'
    let errorMsg = $state('');

    let unsub;

    onMount(() => {
        unsub = QzPrinter.onStatus(({ connected, error }) => {
            if (error) {
                status   = 'error';
                errorMsg = error;
            } else {
                status   = connected ? 'connected' : 'idle';
                errorMsg = '';
            }
        });

        // Refleja estado actual al montar
        status = QzPrinter.connected ? 'connected' : 'idle';
    });

    onDestroy(() => unsub?.());

    async function toggle() {
        if (status === 'connected') {
            await QzPrinter.disconnect();
        } else {
            status   = 'connecting';
            errorMsg = '';
            try {
                await QzPrinter.connect();
            } catch {
                // el listener onStatus ya actualiza el estado
            }
        }
    }

    const iconSize = $derived(compact ? 15 : 16);
</script>

<button
    class="taguara-printer-status {status} {compact ? 'compact' : ''}"
    type="button"
    onclick={toggle}
    title={
        status === 'connected'  ? 'QZ Tray conectado — clic para desconectar' :
        status === 'connecting' ? 'Conectando a QZ Tray...' :
        status === 'error'      ? (errorMsg || 'Error de conexión — clic para reintentar') :
                                  'Conectar a QZ Tray'
    }
>
    {#if status === 'connecting'}
        <Loader size={iconSize} class="spin" />
    {:else if status === 'connected'}
        <PrinterCheck size={iconSize} />
    {:else if status === 'error'}
        <PrinterX size={iconSize} />
    {:else}
        <Printer size={iconSize} />
    {/if}

    {#if showLabel && !compact}
        <span class="taguara-printer-status-label">
            {#if status === 'connecting'}Conectando...
            {:else if status === 'connected'}Impresora lista
            {:else if status === 'error'}Sin impresora
            {:else}Conectar impresora
            {/if}
        </span>
    {/if}
</button>

<style>
    .taguara-printer-status {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.3rem 0.6rem;
        border-radius: 6px;
        border: 1px solid var(--taguara-border);
        background: transparent;
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--taguara-muted);
        cursor: pointer;
        transition: all 0.15s ease;
        white-space: nowrap;
    }

    .taguara-printer-status:hover { background: #f1f5f9; }

    .taguara-printer-status.connected  { color: #16a34a; border-color: #bbf7d0; background: #f0fdf4; }
    .taguara-printer-status.error      { color: #dc2626; border-color: #fecaca; background: #fef2f2; }
    .taguara-printer-status.connecting { color: #d97706; border-color: #fde68a; background: #fffbeb; }

    .taguara-printer-status.compact {
        padding: 0.25rem;
        border-radius: 5px;
    }

    :global(.spin) {
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }
</style>
