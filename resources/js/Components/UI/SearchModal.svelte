<script>
    import { router } from '@inertiajs/svelte';
    import { Search } from '@lucide/svelte';

    let { open = $bindable(false) } = $props();

    let query = $state('');
    let results = $state([]);
    let selectedIndex = $state(0);
    let inputEl = $state(null);
    let searchTimeout = null;

    const typeConfig = {
        product:  { label: 'Producto',  icon: '💊' },
        lot:      { label: 'Lote',      icon: '📦' },
        sale:     { label: 'Venta',     icon: '🧾' },
        receipt:  { label: 'Compra',    icon: '🚚' },
        customer: { label: 'Cliente',   icon: '👤' },
        supplier: { label: 'Proveedor', icon: '🏭' },
        order:    { label: 'Orden',     icon: '📋' },
    };

    const quickActions = [
        { label: 'Nueva venta',      sub: 'Abrir el POS',            icon: '🛒', href: '/pos' },
        { label: 'Nuevo cliente',    sub: 'Registrar cliente',       icon: '👤', href: '/customers' },
        { label: 'Recibir compra',   sub: 'Registrar una compra',    icon: '🚚', href: '/purchases/create' },
        { label: 'Cartera',          sub: 'Saldos pendientes',       icon: '💰', href: '/sales/receivables' },
        { label: 'Kardex',           sub: 'Movimientos de inventario', icon: '📦', href: '/inventory/kardex' },
        { label: 'Facturación FE',   sub: 'Envíos a la DIAN',        icon: '🧾', href: '/fe/submissions' },
    ];

    $effect(() => {
        if (open) {
            query = '';
            results = [];
            selectedIndex = 0;
            setTimeout(() => inputEl?.focus(), 40);
        }
    });

    const search = (q) => {
        clearTimeout(searchTimeout);
        if (q.trim().length < 2) { results = []; return; }
        searchTimeout = setTimeout(async () => {
            try {
                const res = await fetch(`/search?q=${encodeURIComponent(q)}`);
                const data = await res.json();
                results = data.results ?? [];
                selectedIndex = 0;
            } catch {
                results = [];
            }
        }, 220);
    };

    const go = (href) => {
        open = false;
        query = '';
        results = [];
        router.visit(href);
    };

    const handleKeydown = (e) => {
        const list = results.length > 0 ? results : (query.length < 2 ? quickActions : []);
        if (e.key === 'Escape') { open = false; return; }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, list.length - 1);
        }
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, 0);
        }
        if (e.key === 'Enter' && list[selectedIndex]) {
            go(list[selectedIndex].href);
        }
    };
</script>

{#if open}
    <div class="tsm-backdrop" onclick={() => (open = false)} aria-hidden="true"></div>

    <div class="tsm" role="dialog" aria-modal="true" aria-label="Búsqueda global">
        <div class="tsm-input-row">
            <Search size={18} class="tsm-icon" />
            <input
                bind:this={inputEl}
                type="search"
                placeholder="Buscar producto, lote, venta o compra..."
                bind:value={query}
                oninput={(e) => search(e.target.value)}
                onkeydown={handleKeydown}
            />
            <kbd class="tsm-esc-badge">Esc</kbd>
        </div>

        {#if results.length > 0}
            <ul class="tsm-results" role="listbox">
                {#each results as result, i (result.href)}
                    <li role="option" aria-selected={i === selectedIndex}>
                        <button
                            class={`tsm-item ${i === selectedIndex ? 'tsm-item--selected' : ''}`}
                            type="button"
                            onclick={() => go(result.href)}
                            onmouseenter={() => (selectedIndex = i)}
                        >
                            <span class="tsm-item-icon">{typeConfig[result.type]?.icon ?? '🔍'}</span>
                            <span class="tsm-item-body">
                                <span class="tsm-item-label">{result.label}</span>
                                <span class="tsm-item-sub">{result.sub}</span>
                            </span>
                            <span class="tsm-item-badge">{typeConfig[result.type]?.label ?? result.type}</span>
                        </button>
                    </li>
                {/each}
            </ul>
        {:else if query.length >= 2}
            <div class="tsm-empty">
                <Search size={30} />
                <p>Sin resultados para <strong>"{query}"</strong></p>
                <span>Intenta con otro término</span>
            </div>
        {:else}
            <div class="tsm-quick-actions">
                <p class="tsm-quick-actions-label">Accesos rápidos</p>
                <ul class="tsm-results" role="listbox">
                    {#each quickActions as action, i (action.href)}
                        <li role="option" aria-selected={i === selectedIndex}>
                            <button
                                class={`tsm-item ${i === selectedIndex ? 'tsm-item--selected' : ''}`}
                                type="button"
                                onclick={() => go(action.href)}
                                onmouseenter={() => (selectedIndex = i)}
                            >
                                <span class="tsm-item-icon">{action.icon}</span>
                                <span class="tsm-item-body">
                                    <span class="tsm-item-label">{action.label}</span>
                                    <span class="tsm-item-sub">{action.sub}</span>
                                </span>
                            </button>
                        </li>
                    {/each}
                </ul>
            </div>
        {/if}

        <div class="tsm-footer">
            <span><kbd>↑</kbd><kbd>↓</kbd> navegar</span>
            <span><kbd>↵</kbd> abrir</span>
            <span><kbd>Esc</kbd> cerrar</span>
        </div>
    </div>
{/if}
