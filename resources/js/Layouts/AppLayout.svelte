<script>
    import { Link, router } from '@inertiajs/svelte';
    import FlashMessages from '../Components/UI/FlashMessages.svelte';

    let { title = 'Panel', auth, children } = $props();

    const logout = () => {
        router.post('/logout');
    };
</script>

<main class="taguara-shell">
    <FlashMessages />

    <nav class="navbar navbar-expand-lg bg-white border-bottom">
        <div class="container-fluid px-4">
            <Link class="navbar-brand d-flex align-items-center gap-2 fw-semibold" href="/dashboard">
                <span class="taguara-brand-mark" style="width: 34px; height: 34px;">TS</span>
                <span>Taguara Sync</span>
            </Link>

            <div class="d-flex align-items-center gap-3">
                {#if auth?.user}
                    <span class="text-secondary small">{auth.user.name}</span>
                {/if}

                <button class="btn btn-outline-secondary btn-sm" type="button" onclick={logout}>
                    Salir
                </button>
            </div>
        </div>
    </nav>

    <section class="container-fluid px-4 py-4">
        <div class="mb-4">
            <h1 class="h3 mb-1">{title}</h1>
            <p class="text-secondary mb-0">Base inicial para operar farmacias multi-tenant con inventario, compras, POS y sincronizacion.</p>
        </div>

        {@render children?.()}
    </section>
</main>
