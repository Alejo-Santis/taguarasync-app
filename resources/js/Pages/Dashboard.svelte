<script>
    import {
        AlertTriangle,
        ArrowRight,
        Boxes,
        CalendarClock,
        CircleDollarSign,
        FileText,
        PackageCheck,
        ReceiptText,
        ShoppingCart,
        TrendingUp,
    } from '@lucide/svelte';
    import AppLayout from '../Layouts/AppLayout.svelte';
    import LogoMark from '../Components/UI/LogoMark.svelte';

    let { auth } = $props();

    const kpis = [
        {
            label: 'Ventas de hoy',
            value: '$ 0',
            detail: 'Caja pendiente de apertura',
            icon: CircleDollarSign,
            tone: 'success',
        },
        {
            label: 'Items en inventario',
            value: '0',
            detail: 'Catalogo por crear',
            icon: Boxes,
            tone: 'primary',
        },
        {
            label: 'Compras pendientes',
            value: '0',
            detail: 'Recepciones por registrar',
            icon: ReceiptText,
            tone: 'warning',
        },
        {
            label: 'Facturas DIAN',
            value: '0',
            detail: 'Sin envios pendientes',
            icon: FileText,
            tone: 'info',
        },
    ];

    const quickActions = [
        { label: 'Nueva venta POS', icon: ShoppingCart, tone: 'success' },
        { label: 'Recibir compra', icon: ReceiptText, tone: 'primary' },
        { label: 'Crear producto', icon: PackageCheck, tone: 'dark' },
        { label: 'Ajustar inventario', icon: Boxes, tone: 'secondary' },
    ];

    const alerts = [
        { title: 'Productos por vencer', value: '0', detail: 'Sin lotes registrados aun' },
        { title: 'Stock bajo', value: '0', detail: 'Se activara al crear inventario' },
        { title: 'Facturas pendientes', value: '0', detail: 'Cola DIAN sin actividad' },
    ];

    let timeline = $derived([
        { time: 'Ahora', title: 'Base multi-tenant lista', detail: auth?.tenant?.name ?? 'Tenant demo activo' },
        { time: 'Fase 2', title: 'Catalogo farmaceutico', detail: 'Unidades, laboratorios, categorias y productos' },
        { time: 'Fase 3', title: 'Compras e inventario', detail: 'Recepcion directa, lotes y movimientos' },
    ]);
</script>

<AppLayout title="Panel operativo" activeSection="dashboard" {auth}>
    <div class="taguara-dashboard">
        <section class="taguara-command-band">
            <div class="d-flex align-items-center gap-3">
                <LogoMark size={52} />
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Turno de hoy</p>
                    <h2 class="h3 mb-1">{auth?.tenant?.name ?? 'Farmacia'}</h2>
                    <p class="text-secondary mb-0">Resumen operativo para ventas, inventario, compras y facturacion.</p>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="button">
                    <ShoppingCart size={18} />
                    Nueva venta
                </button>
                <button class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" type="button">
                    <ReceiptText size={18} />
                    Recibir compra
                </button>
            </div>
        </section>

        <section class="row g-3">
            {#each kpis as kpi}
                {@const Icon = kpi.icon}
                <div class="col-12 col-md-6 col-xl-3">
                    <article class="taguara-kpi-card">
                        <div class={`taguara-kpi-icon text-bg-${kpi.tone}`}>
                            <Icon size={20} />
                        </div>
                        <div>
                            <p class="text-secondary small mb-1">{kpi.label}</p>
                            <p class="h3 mb-1">{kpi.value}</p>
                            <p class="small text-secondary mb-0">{kpi.detail}</p>
                        </div>
                    </article>
                </div>
            {/each}
        </section>

        <section class="row g-3">
            <div class="col-12 col-xl-8">
                <div class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Flujo comercial</p>
                            <h3 class="h5 mb-0">Actividad del dia</h3>
                        </div>
                        <button class="btn btn-sm btn-light border d-inline-flex align-items-center gap-2" type="button">
                            Ver reporte
                            <ArrowRight size={16} />
                        </button>
                    </div>

                    <div class="taguara-empty-chart">
                        <div class="taguara-chart-bars" aria-hidden="true">
                            <span style="height: 34%"></span>
                            <span style="height: 58%"></span>
                            <span style="height: 42%"></span>
                            <span style="height: 74%"></span>
                            <span style="height: 52%"></span>
                            <span style="height: 86%"></span>
                            <span style="height: 64%"></span>
                        </div>
                        <div>
                            <div class="taguara-kpi-icon text-bg-success mb-3">
                                <TrendingUp size={20} />
                            </div>
                            <h4 class="h6 mb-1">Datos listos para conectar</h4>
                            <p class="text-secondary mb-0">Cuando activemos POS e inventario, este panel mostrara ventas, margen y rotacion.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Prioridades</p>
                            <h3 class="h5 mb-0">Alertas operativas</h3>
                        </div>
                        <AlertTriangle class="text-warning" size={22} />
                    </div>

                    <div class="vstack gap-3">
                        {#each alerts as alert}
                            <div class="taguara-alert-row">
                                <div>
                                    <p class="fw-semibold mb-1">{alert.title}</p>
                                    <p class="small text-secondary mb-0">{alert.detail}</p>
                                </div>
                                <span class="taguara-alert-value">{alert.value}</span>
                            </div>
                        {/each}
                    </div>
                </div>
            </div>
        </section>

        <section class="row g-3">
            <div class="col-12 col-xl-4">
                <div class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Accesos</p>
                            <h3 class="h5 mb-0">Acciones rapidas</h3>
                        </div>
                    </div>

                    <div class="taguara-action-grid">
                        {#each quickActions as action}
                            {@const Icon = action.icon}
                            <button class="taguara-action-button" type="button">
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
                            <p class="text-uppercase small fw-semibold text-success mb-1">Implementacion</p>
                            <h3 class="h5 mb-0">Ruta inmediata</h3>
                        </div>
                        <CalendarClock class="text-primary" size={22} />
                    </div>

                    <div class="taguara-timeline">
                        {#each timeline as item}
                            <div class="taguara-timeline-item">
                                <span class="taguara-timeline-time">{item.time}</span>
                                <div>
                                    <p class="fw-semibold mb-1">{item.title}</p>
                                    <p class="text-secondary small mb-0">{item.detail}</p>
                                </div>
                            </div>
                        {/each}
                    </div>
                </div>
            </div>
        </section>
    </div>
</AppLayout>
