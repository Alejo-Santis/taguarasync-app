<script>
    import { router } from '@inertiajs/svelte';
    import {
        AlertTriangle,
        ArrowRight,
        Boxes,
        CalendarClock,
        CircleDollarSign,
        PackageCheck,
        ReceiptText,
        ShoppingCart,
        TrendingUp,
    } from '@lucide/svelte';
    import AppLayout from '../Layouts/AppLayout.svelte';
    import LogoMark from '../Components/UI/LogoMark.svelte';

    let { auth, kpis, alerts, salesLast7, recentSales } = $props();

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const fmt = (v) => money.format(v ?? 0);

    const maxSale = $derived(Math.max(...salesLast7.map((d) => d.total), 1));
    const barHeight = (total) => Math.max(8, Math.round((total / maxSale) * 100));

    const quickActions = [
        { label: 'Nueva venta POS', icon: ShoppingCart, tone: 'success', href: '/pos' },
        { label: 'Recibir compra', icon: ReceiptText, tone: 'primary', href: '/purchases/create' },
        { label: 'Crear producto', icon: PackageCheck, tone: 'dark', href: '/products/create' },
        { label: 'Ver inventario', icon: Boxes, tone: 'secondary', href: '/inventory' },
    ];
</script>

<AppLayout title="Panel operativo" activeSection="dashboard" {auth}>
    <div class="taguara-dashboard">
        <section class="taguara-command-band">
            <div class="d-flex align-items-center gap-3">
                <LogoMark size={52} />
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Panel operativo</p>
                    <h2 class="h3 mb-1">{auth?.tenant?.name ?? 'Farmacia'}</h2>
                    <p class="text-secondary mb-0">Resumen de ventas, inventario y compras del dia.</p>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="button" onclick={() => router.visit('/pos')}>
                    <ShoppingCart size={18} />
                    Nueva venta
                </button>
                <button class="btn btn-light border d-inline-flex align-items-center gap-2" type="button" onclick={() => router.visit('/purchases/create')}>
                    <ReceiptText size={18} />
                    Recibir compra
                </button>
            </div>
        </section>

        <!-- KPIs reales -->
        <section class="row g-3">
            <div class="col-12 col-md-6 col-xl-3">
                <article class="taguara-kpi-card" role="button" tabindex="0" onclick={() => router.visit('/pos')} style="cursor:pointer">
                    <span class="taguara-kpi-icon text-bg-success"><CircleDollarSign size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Ventas de hoy</p>
                        <p class="h3 mb-1">{fmt(kpis.sales_today)}</p>
                        <p class="small text-secondary mb-0">{kpis.sales_today_count} transacciones</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <article class="taguara-kpi-card" role="button" tabindex="0" onclick={() => router.visit('/inventory')} style="cursor:pointer">
                    <span class="taguara-kpi-icon text-bg-primary"><Boxes size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Unidades en stock</p>
                        <p class="h3 mb-1">{kpis.inventory_units.toLocaleString('es-CO')}</p>
                        <p class="small text-secondary mb-0">{kpis.inventory_lots} lotes activos</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <article class="taguara-kpi-card" role="button" tabindex="0" onclick={() => router.visit('/inventory?expiry=soon')} style="cursor:pointer">
                    <span class={`taguara-kpi-icon ${kpis.expiring_soon > 0 ? 'text-bg-warning' : 'text-bg-secondary'}`}>
                        <CalendarClock size={20} />
                    </span>
                    <div>
                        <p class="text-secondary small mb-1">Por vencer (90 dias)</p>
                        <p class="h3 mb-1">{kpis.expiring_soon}</p>
                        <p class="small text-secondary mb-0">Lotes cerca de caducar</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <article class="taguara-kpi-card" role="button" tabindex="0" onclick={() => router.visit('/purchases')} style="cursor:pointer">
                    <span class="taguara-kpi-icon text-bg-info"><ReceiptText size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Compras del mes</p>
                        <p class="h3 mb-1">{kpis.purchases_month}</p>
                        <p class="small text-secondary mb-0">{fmt(kpis.purchases_month_total)}</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="row g-3">
            <!-- Chart de ventas últimos 7 días -->
            <div class="col-12 col-xl-8">
                <div class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Ventas</p>
                            <h3 class="h5 mb-0">Ultimos 7 dias</h3>
                        </div>
                        <button class="btn btn-sm btn-light border d-inline-flex align-items-center gap-2" type="button" onclick={() => router.visit('/reports/sales')}>
                            Ver reporte
                            <ArrowRight size={16} />
                        </button>
                    </div>

                    {#if salesLast7.every((d) => d.total === 0)}
                        <div class="taguara-empty-chart">
                            <div class="taguara-chart-bars" aria-hidden="true">
                                {#each salesLast7 as day}
                                    <span style="height: 8%"></span>
                                {/each}
                            </div>
                            <div>
                                <div class="taguara-kpi-icon text-bg-success mb-3">
                                    <TrendingUp size={20} />
                                </div>
                                <h4 class="h6 mb-1">Sin ventas en los ultimos 7 dias</h4>
                                <p class="text-secondary mb-0">Abre una caja y registra ventas para ver el historial aqui.</p>
                            </div>
                        </div>
                    {:else}
                        <div class="taguara-chart-container">
                            <div class="taguara-chart-bars-real">
                                {#each salesLast7 as day}
                                    <div class="taguara-chart-col">
                                        <span class="taguara-chart-tooltip">{fmt(day.total)}</span>
                                        <span class="taguara-chart-bar" style="height:{barHeight(day.total)}%"></span>
                                        <span class="taguara-chart-label">{day.label}</span>
                                    </div>
                                {/each}
                            </div>
                        </div>
                    {/if}

                    <!-- Ventas recientes -->
                    {#if recentSales.length > 0}
                        <div class="taguara-recent-sales mt-3">
                            <p class="text-uppercase small fw-semibold text-secondary mb-2">Ultimas ventas</p>
                            {#each recentSales as sale}
                                <div class="taguara-recent-sale-row">
                                    <span class="fw-semibold small">{sale.document_number}</span>
                                    <span class="text-secondary small">{sale.payment_method}</span>
                                    <span class="text-secondary small">{sale.at}</span>
                                    <span class="fw-semibold small">{fmt(sale.total)}</span>
                                </div>
                            {/each}
                        </div>
                    {/if}
                </div>
            </div>

            <!-- Alertas operativas con datos reales -->
            <div class="col-12 col-xl-4">
                <div class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Alertas</p>
                            <h3 class="h5 mb-0">Requieren atencion</h3>
                        </div>
                        <AlertTriangle class={alerts.expiring_lots > 0 || alerts.expired_lots > 0 ? 'text-warning' : 'text-secondary'} size={22} />
                    </div>

                    <div class="vstack gap-3">
                        <div class="taguara-alert-row" role="button" tabindex="0" onclick={() => router.visit('/inventory?expiry=soon')} style="cursor:pointer">
                            <div>
                                <p class="fw-semibold mb-1">Lotes por vencer (30 dias)</p>
                                <p class="small text-secondary mb-0">{alerts.expiring_lots > 0 ? 'Revisar antes de vencer' : 'Sin lotes proximos a vencer'}</p>
                            </div>
                            <span class={`taguara-alert-value ${alerts.expiring_lots > 0 ? '' : 'bg-light text-secondary border'}`}>
                                {alerts.expiring_lots}
                            </span>
                        </div>
                        <div class="taguara-alert-row" role="button" tabindex="0" onclick={() => router.visit('/inventory?expiry=expired')} style="cursor:pointer">
                            <div>
                                <p class="fw-semibold mb-1">Lotes vencidos en stock</p>
                                <p class="small text-secondary mb-0">{alerts.expired_lots > 0 ? 'Retirar del inventario' : 'Sin lotes vencidos'}</p>
                            </div>
                            <span class={`taguara-alert-value ${alerts.expired_lots > 0 ? 'bg-danger text-white' : 'bg-light text-secondary border'}`}>
                                {alerts.expired_lots}
                            </span>
                        </div>
                        <div class="taguara-alert-row">
                            <div>
                                <p class="fw-semibold mb-1">Facturas DIAN pendientes</p>
                                <p class="small text-secondary mb-0">Modulo de facturacion proximo</p>
                            </div>
                            <span class="taguara-alert-value bg-light text-secondary border">—</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Acciones rapidas funcionales -->
        <section class="row g-3">
            <div class="col-12 col-xl-4">
                <div class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Accesos directos</p>
                            <h3 class="h5 mb-0">Acciones rapidas</h3>
                        </div>
                    </div>
                    <div class="taguara-action-grid">
                        {#each quickActions as action}
                            {@const Icon = action.icon}
                            <button class="taguara-action-button" type="button" onclick={() => router.visit(action.href)}>
                                <span class={`taguara-action-icon text-bg-${action.tone}`}>
                                    <Icon size={18} />
                                </span>
                                <span>{action.label}</span>
                            </button>
                        {/each}
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-8">
                <div class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Estado del sistema</p>
                            <h3 class="h5 mb-0">Modulos activos</h3>
                        </div>
                        <CalendarClock class="text-primary" size={22} />
                    </div>
                    <div class="taguara-timeline">
                        <div class="taguara-timeline-item">
                            <span class="taguara-timeline-time text-bg-success text-white">Activo</span>
                            <div>
                                <p class="fw-semibold mb-1">Catalogo farmaceutico</p>
                                <p class="text-secondary small mb-0">Productos, presentaciones, laboratorios, categorias, importacion CSV</p>
                            </div>
                        </div>
                        <div class="taguara-timeline-item">
                            <span class="taguara-timeline-time text-bg-success text-white">Activo</span>
                            <div>
                                <p class="fw-semibold mb-1">Compras e inventario</p>
                                <p class="text-secondary small mb-0">Recepciones, lotes con vencimiento, movimientos FEFO</p>
                            </div>
                        </div>
                        <div class="taguara-timeline-item">
                            <span class="taguara-timeline-time text-bg-success text-white">Activo</span>
                            <div>
                                <p class="fw-semibold mb-1">POS con sistema de cajas</p>
                                <p class="text-secondary small mb-0">Ventas, apertura/cierre de turno, arqueo, N terminales</p>
                            </div>
                        </div>
                        <div class="taguara-timeline-item">
                            <span class="taguara-timeline-time" style="background:var(--taguara-soft-blue);color:#084298">Proximo</span>
                            <div>
                                <p class="fw-semibold mb-1">Reportes y facturacion DIAN</p>
                                <p class="text-secondary small mb-0">Ventas, inventario, compras · Factura electronica Colombia</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</AppLayout>
