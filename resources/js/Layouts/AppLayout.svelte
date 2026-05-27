<script>
    import { Link, router } from '@inertiajs/svelte';
    import {
        ActivitySquare,
        BarChart3,
        Bell,
        Boxes,
        Building2,
        ChevronDown,
        ChevronLeft,
        ChevronRight,
        CheckCheck,
        CircleDollarSign,
        FileText,
        LayoutDashboard,
        LogOut,
        Menu,
        Package,
        ReceiptText,
        Search,
        Settings,
        ShoppingCart,
        Truck,
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

    const perms = $derived(auth?.permissions ?? []);
    const can = (p) => perms.includes(p);

    const allNavGroups = [
        {
            items: [
                { label: 'Panel', href: '/dashboard', icon: LayoutDashboard, section: 'dashboard', perm: 'dashboard.view' },
            ],
        },
        {
            group: 'Ventas',
            items: [
                { label: 'POS', href: '/pos', icon: ShoppingCart, section: 'pos', perm: 'pos.sell' },
                { label: 'Ventas', href: '/sales', icon: ReceiptText, section: 'sales', perm: 'sales.view' },
                { label: 'Clientes', href: '/customers', icon: User, section: 'customers', perm: 'sales.view' },
                { label: 'Cartera', href: '/sales/receivables', icon: CircleDollarSign, section: 'receivables', perm: 'sales.view' },
                { label: 'Facturación FE', href: '/fe/submissions', icon: FileText, section: 'fe-submissions', perm: 'billing.view' },
            ],
        },
        {
            group: 'Compras e inventario',
            items: [
                { label: 'Productos', href: '/products', icon: Package, section: 'products', perm: 'products.view' },
                { label: 'Inventario', href: '/inventory', icon: Boxes, section: 'inventory', perm: 'inventory.view' },
                { label: 'Compras', href: '/purchases', icon: ReceiptText, section: 'purchases', perm: 'purchases.view' },
                { label: 'Proveedores', href: '/purchases/payables', icon: Truck, section: 'payables', perm: 'purchases.view' },
            ],
        },
        {
            group: 'Análisis',
            items: [
                { label: 'Reportes', href: '/reports/sales', icon: BarChart3, section: 'reports', perm: 'reports.view' },
            ],
        },
        {
            group: 'Administración',
            items: [
                { label: 'Auditorías', href: '/audit', icon: ActivitySquare, section: 'audit', perm: 'reports.view' },
                { label: 'Equipo', href: '/team', icon: Users, section: 'team', perm: 'users.manage' },
                { label: 'Configuracion', href: '/settings/laboratories', icon: Settings, section: 'configuracion', perm: 'settings.manage' },
            ],
        },
    ];

    // Filter groups and items by permission
    const navGroups = allNavGroups
        .map((g) => ({ ...g, items: g.items.filter((i) => !i.perm || can(i.perm)) }))
        .filter((g) => g.items.length > 0);

    const navItemsFlat = navGroups.flatMap((g) => g.items);

    const logout = () => {
        router.post('/logout');
    };

    const notifications = $derived(auth?.notifications ?? { unread_count: 0, items: [] });

    const notificationClass = (severity) => {
        if (severity === 'critical') return 'taguara-notification-dot--critical';
        if (severity === 'warning') return 'taguara-notification-dot--warning';
        return 'taguara-notification-dot--info';
    };

    const openNotification = (item) => {
        router.patch(`/notifications/${item.id}/read`, {}, {
            preserveScroll: true,
            onSuccess: () => router.visit(item.href ?? '/dashboard'),
        });
    };

    const markNotificationsRead = () => {
        router.patch('/notifications/read-all', {}, { preserveScroll: true });
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

        {#if auth?.user?.is_super_admin}
            <nav class="taguara-sidebar-nav border-top pt-2 mt-1" aria-label="Super Admin">
                <span class="taguara-nav-group-label taguara-nav-label text-danger">Super admin</span>
                <div use:navTooltip={'Farmacias'}>
                    <Link
                        class={`taguara-nav-link ${activeSection === 'admin' ? 'active' : ''}`}
                        href="/admin/tenants"
                    >
                        <Building2 size={18} strokeWidth={2} />
                        <span class="taguara-nav-label">Farmacias</span>
                    </Link>
                </div>
            </nav>
        {/if}

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

                <div class="dropdown">
                    <button
                        class="taguara-bell-btn"
                        type="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                        aria-label="Notificaciones"
                    >
                        <Bell size={18} />
                        {#if notifications.unread_count > 0}
                            <span class="taguara-bell-badge">{notifications.unread_count > 9 ? '9+' : notifications.unread_count}</span>
                        {/if}
                    </button>
                    <div class="dropdown-menu dropdown-menu-end taguara-notification-menu">
                        <div class="taguara-notification-header">
                            <div>
                                <p class="mb-0 fw-semibold">Notificaciones</p>
                                <span>{notifications.unread_count} pendiente{notifications.unread_count === 1 ? '' : 's'}</span>
                            </div>
                            {#if notifications.unread_count > 0}
                                <button class="btn btn-sm btn-light border taguara-icon-button-sm" type="button" onclick={markNotificationsRead} title="Marcar todas como leídas">
                                    <CheckCheck size={15} />
                                </button>
                            {/if}
                        </div>
                        <div class="taguara-notification-list">
                            {#each notifications.items as item}
                                <button class="taguara-notification-item" type="button" onclick={() => openNotification(item)}>
                                    <span class={`taguara-notification-dot ${notificationClass(item.severity)}`}></span>
                                    <span class="taguara-notification-body">
                                        <span class="taguara-notification-title">{item.title}</span>
                                        <span class="taguara-notification-text">{item.body}</span>
                                        <span class="taguara-notification-time">{item.created_at}</span>
                                    </span>
                                </button>
                            {:else}
                                <div class="taguara-notification-empty">
                                    <Bell size={20} />
                                    <span>Sin alertas pendientes</span>
                                </div>
                            {/each}
                        </div>
                    </div>
                </div>

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
