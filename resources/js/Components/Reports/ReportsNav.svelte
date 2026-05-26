<script>
    import { Link } from '@inertiajs/svelte';

    let { active } = $props();

    const tabs = [
        { label: 'Ventas', href: '/reports/sales', key: 'sales' },
        { label: 'Fiscal', href: '/reports/fiscal', key: 'fiscal' },
        { label: 'Cajas', href: '/reports/cash-sessions', key: 'cash-sessions' },
        { label: 'Inventario', href: '/reports/inventory', key: 'inventory' },
        { label: 'Compras', href: '/reports/purchases', key: 'purchases' },
        { label: 'Rentabilidad', href: '/reports/profitability', key: 'profitability' },
    ];
</script>

<nav class="taguara-config-nav" aria-label="Reportes">
    {#each tabs as tab}
        <Link href={tab.href} class={`taguara-config-tab${tab.key === active ? ' active' : ''}`}>
            {tab.label}
        </Link>
    {/each}
</nav>
