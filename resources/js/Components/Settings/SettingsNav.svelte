<script>
    import { Link } from '@inertiajs/svelte';
    import { page } from '@inertiajs/svelte';

    let { active } = $props();

    const perms = $derived(page.props.auth?.permissions ?? []);
    const can = (p) => perms.includes(p);

    const allTabs = [
        { label: 'Laboratorios', href: '/settings/laboratories', key: 'laboratorios' },
        { label: 'Categorias', href: '/settings/categories', key: 'categorias' },
        { label: 'Unidades', href: '/settings/units', key: 'unidades' },
        { label: 'Principios activos', href: '/settings/active-ingredients', key: 'principios-activos' },
        { label: 'Proveedores', href: '/settings/suppliers', key: 'proveedores' },
        { label: 'Cajas', href: '/settings/registers', key: 'registers' },
        { label: 'Bancos', href: '/settings/banks', key: 'banks' },
        { label: 'Listas de precio', href: '/settings/price-lists', key: 'price-lists' },
        { label: 'Facturación electrónica', href: '/settings/fe', key: 'fe', perm: 'billing.configure' },
    ];

    const tabs = $derived(allTabs.filter((t) => !t.perm || can(t.perm)));
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
