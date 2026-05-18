<script>
    import FlashMessages from '../Components/UI/FlashMessages.svelte';
    import LogoMark from '../Components/UI/LogoMark.svelte';

    let { title, subtitle, children } = $props();
</script>

<main class="taguara-shell d-flex align-items-center py-5">
    <FlashMessages />

    <div class="container">
        <div class="mx-auto taguara-auth-panel bg-white p-4 p-sm-5">
            <div class="d-flex align-items-center gap-3 mb-4">
                <LogoMark size={52} />
                <div>
                    <p class="text-uppercase text-success fw-semibold small mb-1">Taguara Sync</p>
                    <h1 class="h4 mb-0">{title}</h1>
                </div>
            </div>

            {#if subtitle}
                <p class="text-secondary mb-4">{subtitle}</p>
            {/if}

            {@render children?.()}
        </div>
    </div>
</main>
