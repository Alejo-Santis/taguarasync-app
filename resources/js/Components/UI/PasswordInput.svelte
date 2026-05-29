<script>
    import { Eye, EyeOff } from '@lucide/svelte';

    let {
        id,
        label,
        value = $bindable(''),
        error = null,
        autocomplete = 'current-password',
        showStrength = false,
    } = $props();

    let isVisible = $state(false);

    const calcStrength = (pwd) => {
        if (!pwd) return 0;
        let score = 0;
        if (pwd.length >= 8) score++;
        if (pwd.length >= 12) score++;
        if (/[A-Z]/.test(pwd) && /[a-z]/.test(pwd)) score++;
        if (/[0-9]/.test(pwd)) score++;
        if (/[^A-Za-z0-9]/.test(pwd)) score++;
        return Math.min(score, 4);
    };

    const strengthMeta = [
        { label: 'Débil',   color: '#dc3545' },
        { label: 'Regular', color: '#fd7e14' },
        { label: 'Buena',   color: '#ffc107' },
        { label: 'Fuerte',  color: 'var(--taguara-primary)' },
    ];

    const strength = $derived(calcStrength(value));
    const meta = $derived(value ? strengthMeta[strength - 1] ?? strengthMeta[0] : null);
</script>

<label class="form-label" for={id}>{label}</label>
<div class="input-group">
    <input
        {id}
        class:is-invalid={error}
        class="form-control"
        type={isVisible ? 'text' : 'password'}
        bind:value
        placeholder="Ingresa tu contraseña"
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

{#if showStrength && value}
    <div class="taguara-pwd-strength mt-1">
        <div class="taguara-pwd-strength-bars">
            {#each [1, 2, 3, 4] as seg}
                <div
                    class="taguara-pwd-strength-bar"
                    style={seg <= strength ? `background:${meta.color}` : ''}
                ></div>
            {/each}
        </div>
        <span class="taguara-pwd-strength-label" style="color:{meta.color}">{meta.label}</span>
    </div>
{/if}

{#if error}
    <div class="invalid-feedback d-block">{error}</div>
{/if}
