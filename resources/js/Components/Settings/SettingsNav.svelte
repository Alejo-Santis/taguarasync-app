<script>
    import { Link } from '@inertiajs/svelte';

    let { active } = $props();

    const tabs = [
        { label: 'Laboratorios', href: '/settings/laboratories', key: 'laboratorios' },
        { label: 'Categorias', href: '/settings/categories', key: 'categorias' },
        { label: 'Unidades', href: '/settings/units', key: 'unidades' },
        { label: 'Principios activos', href: '/settings/active-ingredients', key: 'principios-activos' },
        { label: 'Proveedores', href: '/settings/suppliers', key: 'proveedores' },
        { label: 'Cajas', href: '/settings/registers', key: 'registers' },
    ];
</script>

<nav class="taguara-config-nav" aria-label="Configuracion">
    {#each tabs as tab}
        <Link
            href={tab.href}
            class={`taguara-config-tab${tab.key === active ? ' active' : ''}`}
        >
            {tab.label}
        </Link>
    {/each}
</nav>
