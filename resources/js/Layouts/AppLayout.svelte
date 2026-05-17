<script>
    import { Link, router } from '@inertiajs/svelte';
    import {
        BarChart3,
        Boxes,
        Building2,
        ChevronDown,
        FileText,
        LayoutDashboard,
        LogOut,
        Menu,
        Package,
        ReceiptText,
        Search,
        Settings,
        ShoppingCart,
        Store,
        Users,
        Wifi,
    } from '@lucide/svelte';
    import FlashMessages from '../Components/UI/FlashMessages.svelte';

    let { title = 'Panel', auth, children } = $props();
    let isMobileNavOpen = $state(false);

    const navItems = [
        { label: 'Panel', href: '/dashboard', icon: LayoutDashboard, active: true },
        { label: 'POS', href: '#', icon: ShoppingCart },
        { label: 'Productos', href: '#', icon: Package },
        { label: 'Inventario', href: '#', icon: Boxes },
        { label: 'Compras', href: '#', icon: ReceiptText },
        { label: 'Facturacion', href: '#', icon: FileText },
        { label: 'Reportes', href: '#', icon: BarChart3 },
        { label: 'Equipo', href: '#', icon: Users },
        { label: 'Configuracion', href: '#', icon: Settings },
    ];

    const logout = () => {
        router.post('/logout');
    };
</script>

<main class="taguara-app">
    <FlashMessages />

    <aside class="taguara-sidebar">
        <div class="taguara-sidebar-brand">
            <Link class="d-flex align-items-center gap-2 text-decoration-none text-reset" href="/dashboard">
                <span class="taguara-brand-mark taguara-brand-mark-sm">TS</span>
                <span>
                    <span class="d-block fw-semibold lh-sm">Taguara Sync</span>
                    <span class="d-block text-secondary small">Farmacia hibrida</span>
                </span>
            </Link>
        </div>

        <nav class="taguara-sidebar-nav" aria-label="Principal">
            {#each navItems as item}
                {@const Icon = item.icon}
                <Link class={`taguara-nav-link ${item.active ? 'active' : ''}`} href={item.href}>
                    <Icon size={18} strokeWidth={2} />
                    <span>{item.label}</span>
                </Link>
            {/each}
        </nav>

        <div class="taguara-sidebar-footer">
            <div class="taguara-tenant-chip">
                <Building2 size={18} />
                <span class="min-w-0">
                    <span class="d-block fw-semibold text-truncate">{auth?.tenant?.name ?? 'Sin farmacia'}</span>
                    <span class="d-block text-secondary small">Tenant activo</span>
                </span>
            </div>
        </div>
    </aside>

    <section class="taguara-main">
        <header class="taguara-topbar">
            <div class="d-flex align-items-center gap-2">
                <button
                    class="btn btn-light taguara-icon-button d-lg-none"
                    type="button"
                    aria-label="Menu"
                    aria-expanded={isMobileNavOpen}
                    onclick={() => { isMobileNavOpen = !isMobileNavOpen; }}
                >
                    <Menu size={20} />
                </button>
                <div>
                    <p class="text-uppercase text-success fw-semibold small mb-1">Operacion de farmacia</p>
                    <h1 class="h4 mb-0">{title}</h1>
                </div>
            </div>

            <div class="taguara-topbar-actions">
                <div class="taguara-search">
                    <Search size={17} />
                    <input type="search" placeholder="Buscar producto, lote o factura">
                </div>

                <span class="taguara-status-pill">
                    <Wifi size={16} />
                    Online
                </span>

                <div class="dropdown">
                    <button class="btn btn-light border d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <Store size={18} />
                        <span class="d-none d-sm-inline">{auth?.user?.name ?? 'Usuario'}</span>
                        <ChevronDown size={16} />
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                        <button class="dropdown-item d-flex align-items-center gap-2" type="button" onclick={logout}>
                            <LogOut size={16} />
                            Cerrar sesion
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <div class={`taguara-mobile-nav ${isMobileNavOpen ? 'open' : ''}`}>
            {#each navItems.slice(0, 6) as item}
                {@const Icon = item.icon}
                <Link class={`taguara-mobile-link ${item.active ? 'active' : ''}`} href={item.href}>
                    <Icon size={17} />
                    <span>{item.label}</span>
                </Link>
            {/each}
        </div>

        <section class="taguara-content">
            {@render children?.()}
        </section>
    </section>
</main>
