<script>
    import { Link, router } from '@inertiajs/svelte';
    import {
        BarChart3,
        Boxes,
        Building2,
        ChevronDown,
        ChevronLeft,
        ChevronRight,
        FileText,
        LayoutDashboard,
        LogOut,
        Menu,
        Package,
        ReceiptText,
        Search,
        Settings,
        ShoppingCart,
        User,
        Users,
    } from '@lucide/svelte';
    import FlashMessages from '../Components/UI/FlashMessages.svelte';
    import LogoMark from '../Components/UI/LogoMark.svelte';
    import SearchModal from '../Components/UI/SearchModal.svelte';

    let { title = 'Panel', activeSection = 'dashboard', auth, children } = $props();
    let isMobileNavOpen = $state(false);
    let isCollapsed = $state(localStorage.getItem('sidebar-collapsed') === 'true');

    const toggleSidebar = () => {
        isCollapsed = !isCollapsed;
        localStorage.setItem('sidebar-collapsed', String(isCollapsed));
    };

    function navTooltip(node, label) {
        let tip = null;

        function show() {
            if (!isCollapsed) return;
            tip = document.createElement('div');
            tip.className = 'taguara-sidebar-tooltip';
            tip.textContent = label;
            document.body.appendChild(tip);
            const rect = node.getBoundingClientRect();
            tip.style.top = `${rect.top + rect.height / 2}px`;
            tip.style.left = `${rect.right + 10}px`;
        }

        function hide() {
            tip?.remove();
            tip = null;
        }

        node.addEventListener('mouseenter', show);
        node.addEventListener('mouseleave', hide);

        return {
            update(newLabel) { label = newLabel; },
            destroy() {
                hide();
                node.removeEventListener('mouseenter', show);
                node.removeEventListener('mouseleave', hide);
            },
        };
    }

    let searchModalOpen = $state(false);

    const navGroups = [
        {
            items: [
                { label: 'Panel', href: '/dashboard', icon: LayoutDashboard, section: 'dashboard' },
            ],
        },
        {
            group: 'Ventas',
            items: [
                { label: 'POS', href: '/pos', icon: ShoppingCart, section: 'pos' },
                { label: 'Ventas', href: '/sales', icon: ReceiptText, section: 'sales' },
                { label: 'Clientes', href: '/customers', icon: User, section: 'customers' },
                { label: 'Facturacion', href: null, icon: FileText, soon: true },
            ],
        },
        {
            group: 'Inventario',
            items: [
                { label: 'Productos', href: '/products', icon: Package, section: 'products' },
                { label: 'Inventario', href: '/inventory', icon: Boxes, section: 'inventory' },
                { label: 'Compras', href: '/purchases', icon: ReceiptText, section: 'purchases' },
            ],
        },
        {
            group: 'Análisis',
            items: [
                { label: 'Reportes', href: '/reports/sales', icon: BarChart3, section: 'reports' },
            ],
        },
        {
            group: 'Administración',
            items: [
                { label: 'Equipo', href: '/team', icon: Users, section: 'team' },
                { label: 'Configuracion', href: '/settings/laboratories', icon: Settings, section: 'configuracion' },
            ],
        },
    ];

    const navItemsFlat = navGroups.flatMap((g) => g.items);

    const logout = () => {
        router.post('/logout');
    };

    const userInitials = (name) =>
        name?.split(' ').slice(0, 2).map((n) => n[0]).join('').toUpperCase() ?? 'U';

    const isMac = typeof navigator !== 'undefined' && /mac/i.test(navigator.platform);
    const searchKbd = isMac ? '⌘K' : 'Ctrl K';

    const handleGlobalKeydown = (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            searchModalOpen = true;
        }
    };
</script>

<svelte:window onkeydown={handleGlobalKeydown} />

<main class="taguara-app">
    <FlashMessages />

    <aside class={`taguara-sidebar ${isCollapsed ? 'taguara-sidebar--collapsed' : ''}`}>
        <div class="taguara-sidebar-brand">
            <Link class="taguara-sidebar-brand-link" href="/dashboard">
                <LogoMark size={38} />
                <span class="taguara-nav-label">
                    <span class="d-block fw-semibold lh-sm">Taguara Sync</span>
                    <span class="d-block text-secondary small">Farmacia hibrida</span>
                </span>
            </Link>
            <button
                class="taguara-collapse-btn"
                type="button"
                onclick={toggleSidebar}
                aria-label={isCollapsed ? 'Expandir menú' : 'Colapsar menú'}
            >
                {#if isCollapsed}
                    <ChevronRight size={15} />
                {:else}
                    <ChevronLeft size={15} />
                {/if}
            </button>
        </div>

        <nav class="taguara-sidebar-nav" aria-label="Principal">
            {#each navGroups as { group, items }}
                {#if group}
                    <span class="taguara-nav-group-label taguara-nav-label">{group}</span>
                {/if}
                {#each items as item}
                    {@const Icon = item.icon}
                    {#if item.soon}
                        <span
                            class="taguara-nav-link taguara-nav-link-soon"
                            aria-disabled="true"
                            use:navTooltip={item.label}
                        >
                            <Icon size={18} strokeWidth={2} />
                            <span class="taguara-nav-label">{item.label}</span>
                            <span class="taguara-nav-soon-badge taguara-nav-label">Pronto</span>
                        </span>
                    {:else}
                        <div use:navTooltip={item.label}>
                            <Link
                                class={`taguara-nav-link ${item.section === activeSection ? 'active' : ''}`}
                                href={item.href}
                            >
                                <Icon size={18} strokeWidth={2} />
                                <span class="taguara-nav-label">{item.label}</span>
                            </Link>
                        </div>
                    {/if}
                {/each}
            {/each}
        </nav>

        <div class="taguara-sidebar-footer">
            <div class="taguara-tenant-chip">
                <span class="taguara-tenant-avatar">
                    {(auth?.tenant?.name ?? 'S').slice(0, 2).toUpperCase()}
                </span>
                <span class="taguara-nav-label min-w-0">
                    <span class="d-block fw-semibold text-truncate" style="font-size:0.9rem">{auth?.tenant?.name ?? 'Sin farmacia'}</span>
                    <span class="d-block text-secondary" style="font-size:0.75rem">Farmacia activa</span>
                </span>
            </div>
        </div>
    </aside>

    <section class={`taguara-main ${isCollapsed ? 'taguara-main--collapsed' : ''}`}>
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
                <h1 class="h5 mb-0 fw-bold">{title}</h1>
            </div>

            <div class="taguara-topbar-actions">
                <!-- Search trigger -->
                <button class="taguara-search-trigger" onclick={() => (searchModalOpen = true)}>
                    <Search size={15} />
                    <span class="taguara-search-trigger-text">Buscar...</span>
                    <kbd class="taguara-search-kbd">{searchKbd}</kbd>
                </button>

                <!-- Online indicator -->
                <span class="taguara-online-indicator" title="Conectado">
                    <span class="taguara-online-dot">
                        <span class="taguara-online-ring"></span>
                    </span>
                    <span class="taguara-online-label">En línea</span>
                </span>

                <!-- User menu -->
                <div class="dropdown">
                    <button class="taguara-user-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="taguara-user-avatar">
                            {userInitials(auth?.user?.name)}
                        </span>
                        <span class="taguara-user-info">
                            <span class="taguara-user-name">{auth?.user?.name ?? 'Usuario'}</span>
                        </span>
                        <ChevronDown size={14} class="taguara-user-chevron" />
                    </button>
                    <div class="dropdown-menu dropdown-menu-end taguara-user-menu">
                        <div class="taguara-user-menu-header">
                            <span class="taguara-user-avatar taguara-user-avatar--lg">
                                {userInitials(auth?.user?.name)}
                            </span>
                            <div class="min-w-0">
                                <p class="mb-0 fw-semibold text-truncate" style="font-size:0.9rem">{auth?.user?.name ?? 'Usuario'}</p>
                                <p class="mb-0 text-truncate" style="font-size:0.78rem;color:var(--taguara-muted)">{auth?.user?.email ?? ''}</p>
                            </div>
                        </div>
                        <div class="dropdown-divider my-1"></div>
                        <a class="dropdown-item taguara-user-menu-item" href="/profile">
                            <User size={15} />
                            Mi perfil
                        </a>
                        <div class="dropdown-divider my-1"></div>
                        <button class="dropdown-item taguara-user-menu-item taguara-user-logout" type="button" onclick={logout}>
                            <LogOut size={15} />
                            Cerrar sesión
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <div class={`taguara-mobile-nav ${isMobileNavOpen ? 'open' : ''}`}>
            {#each navItemsFlat.filter((i) => !i.soon).slice(0, 6) as item}
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

<SearchModal bind:open={searchModalOpen} />
