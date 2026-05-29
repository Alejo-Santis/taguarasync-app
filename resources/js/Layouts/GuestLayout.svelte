<script>
    import FlashMessages from '../Components/UI/FlashMessages.svelte';
    import LogoMark from '../Components/UI/LogoMark.svelte';

    let { title, subtitle, children } = $props();
</script>

<div class="taguara-auth-root">
    <!-- Panel izquierdo — branding -->
    <aside class="taguara-auth-brand" aria-hidden="true">
        <div class="taguara-auth-brand-orb taguara-auth-brand-orb--1"></div>
        <div class="taguara-auth-brand-orb taguara-auth-brand-orb--2"></div>

        <div class="taguara-auth-brand-content">
            <LogoMark size={64} variant="white" />
            <p class="taguara-auth-brand-name">Taguara Sync</p>
            <h2 class="taguara-auth-brand-headline">Gestión farmacéutica<br>sincronizada.</h2>
            <p class="taguara-auth-brand-sub">Control de inventario, ventas y facturación<br>electrónica en una sola plataforma.</p>
        </div>

        <p class="taguara-auth-brand-footer">© {new Date().getFullYear()} Taguara Sync</p>
    </aside>

    <!-- Panel derecho — formulario -->
    <main class="taguara-auth-form-panel">
        <FlashMessages />
        <div class="taguara-auth-form-wrap">
            <div class="mb-4 d-lg-none d-flex align-items-center gap-2">
                <LogoMark size={36} />
                <span class="fw-semibold" style="color: var(--taguara-primary)">Taguara Sync</span>
            </div>

            <h1 class="h4 mb-1">{title}</h1>
            {#if subtitle}
                <p class="text-secondary mb-4">{subtitle}</p>
            {/if}

            {@render children?.()}
        </div>
    </main>
</div>
