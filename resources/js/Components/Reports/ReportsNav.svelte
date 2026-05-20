<script>
    import { Link } from '@inertiajs/svelte';

    let { active } = $props();

    const tabs = [
        { label: 'Ventas', href: '/reports/sales', key: 'sales' },
        { label: 'Inventario', href: '/reports/inventory', key: 'inventory' },
        { label: 'Compras', href: '/reports/purchases', key: 'purchases' },
    ];
</script>

<nav class="taguara-config-nav" aria-label="Reportes">
    {#each tabs as tab}
        <Link href={tab.href} class={`taguara-config-tab${tab.key === active ? ' active' : ''}`}>
            {tab.label}
        </Link>
    {/each}
</nav>
