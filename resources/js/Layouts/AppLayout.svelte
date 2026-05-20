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
    import LogoMark from '../Components/UI/LogoMark.svelte';

    let { title = 'Panel', activeSection = 'dashboard', auth, children } = $props();
    let isMobileNavOpen = $state(false);

    const navItems = [
        { label: 'Panel', href: '/dashboard', icon: LayoutDashboard, section: 'dashboard' },
        { label: 'POS', href: '/pos', icon: ShoppingCart, section: 'pos' },
        { label: 'Ventas', href: '/sales', icon: ReceiptText, section: 'sales' },
        { label: 'Productos', href: '/products', icon: Package, section: 'products' },
        { label: 'Inventario', href: '/inventory', icon: Boxes, section: 'inventory' },
        { label: 'Compras', href: '/purchases', icon: ReceiptText, section: 'purchases' },
        { label: 'Reportes', href: '/reports/sales', icon: BarChart3, section: 'reports' },
        { label: 'Equipo', href: '/team', icon: Users, section: 'team' },
        { label: 'Facturacion', href: null, icon: FileText, soon: true },
        { label: 'Configuracion', href: '/settings/laboratories', icon: Settings, section: 'configuracion' },
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
                <LogoMark size={38} />
                <span>
                    <span class="d-block fw-semibold lh-sm">Taguara Sync</span>
                    <span class="d-block text-secondary small">Farmacia hibrida</span>
                </span>
            </Link>
        </div>

        <nav class="taguara-sidebar-nav" aria-label="Principal">
            {#each navItems as item}
                {@const Icon = item.icon}
                {#if item.soon}
                    <span class="taguara-nav-link taguara-nav-link-soon" aria-disabled="true" title="Proximo en llegar">
                        <Icon size={18} strokeWidth={2} />
                        <span>{item.label}</span>
                        <span class="taguara-nav-soon-badge">Pronto</span>
                    </span>
                {:else}
                    <Link class={`taguara-nav-link ${item.section === activeSection ? 'active' : ''}`} href={item.href}>
                        <Icon size={18} strokeWidth={2} />
                        <span>{item.label}</span>
                    </Link>
                {/if}
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
                        <a class="dropdown-item d-flex align-items-center gap-2" href="/profile">
                            <Store size={16} />
                            Mi perfil
                        </a>
                        <div class="dropdown-divider"></div>
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
                <Link class={`taguara-mobile-link ${item.section === activeSection ? 'active' : ''}`} href={item.href}>
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
