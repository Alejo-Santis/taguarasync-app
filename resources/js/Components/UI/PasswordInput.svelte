<script>
    import { Eye, EyeOff } from '@lucide/svelte';

    let {
        id,
        label,
        value = $bindable(''),
        error = null,
        autocomplete = 'current-password',
    } = $props();

    let isVisible = $state(false);
</script>

<label class="form-label" for={id}>{label}</label>
<div class="input-group">
    <input
        {id}
        class:is-invalid={error}
        class="form-control"
        type={isVisible ? 'text' : 'password'}
        bind:value
        {autocomplete}
    >
    <button
        class="btn btn-outline-secondary taguara-password-toggle"
        type="button"
        aria-label={isVisible ? 'Ocultar contrasena' : 'Mostrar contrasena'}
        aria-pressed={isVisible}
        onclick={() => { isVisible = !isVisible; }}
    >
        {#if isVisible}
            <EyeOff size={18} />
        {:else}
            <Eye size={18} />
        {/if}
    </button>
</div>
{#if error}
    <div class="invalid-feedback d-block">{error}</div>
{/if}
