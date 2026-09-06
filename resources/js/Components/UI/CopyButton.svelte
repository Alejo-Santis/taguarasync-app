<script>
    import { Copy, Check } from '@lucide/svelte';

    let { text = '', label = 'Copiar', size = 13, class: className = '' } = $props();

    let copied = $state(false);
    let timeout = null;

    const copy = async (e) => {
        e.stopPropagation();
        try {
            await navigator.clipboard.writeText(text);
        } catch {
            return;
        }
        copied = true;
        clearTimeout(timeout);
        timeout = setTimeout(() => { copied = false; }, 1500);
    };
</script>

<button
    type="button"
    class="taguara-copy-btn {className}"
    class:taguara-copy-btn--copied={copied}
    onclick={copy}
    disabled={!text}
    title={copied ? 'Copiado' : label}
    aria-label={copied ? 'Copiado' : label}
>
    {#if copied}
        <Check size={size} />
    {:else}
        <Copy size={size} />
    {/if}
</button>
