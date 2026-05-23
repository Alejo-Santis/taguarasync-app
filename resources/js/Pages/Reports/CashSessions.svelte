<script>
    import { Link, router } from '@inertiajs/svelte';
    import { BarChart3, CircleDollarSign, Clock, Eye, Filter, RotateCcw, ShieldAlert } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';
    import ReportsNav from '../../Components/Reports/ReportsNav.svelte';

    let { auth, filters, sessions, summary, registers, cashiers } = $props();

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const fmt = (v) => money.format(v ?? 0);

    let form = $state({
        from: filters.from,
        to: filters.to,
        status: filters.status ?? '',
        cash_register_id: filters.cash_register_id ?? '',
        user_id: filters.user_id ?? '',
        difference: filters.difference ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        router.get('/reports/cash-sessions', form, { preserveState: true, replace: true });
    };

    const reset = () => {
        const now = new Date();
        form = {
            from: `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`,
            to: now.toISOString().slice(0, 10),
            status: '',
            cash_register_id: '',
            user_id: '',
            difference: '',
        };
        router.get('/reports/cash-sessions', form, { preserveState: true, replace: true });
    };

    const statusClass = (value) => value === 'open' ? 'text-bg-success' : 'text-bg-secondary';
    const differenceClass = (value) => {
        if (value === null || value === undefined) return 'text-secondary';
        if (value < 0) return 'text-danger fw-semibold';
        if (value > 0) return 'text-warning fw-semibold';
        return 'text-success fw-semibold';
    };
</script>

<AppLayout title="Reportes" activeSection="reports" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Reportes</p>
                <h2 class="h3 mb-2">Cierres y auditoria de caja</h2>
                <p class="text-secondary mb-0">Consulta aperturas, cierres, ventas del turno y diferencias de arqueo.</p>
            </div>
            <BarChart3 class="text-secondary" size={22} />
        </section>

        <ReportsNav active="cash-sessions" />

        <section class="taguara-panel">
            <div class="taguara-panel-header align-items-start">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Filtros</p>
                    <h3 class="h5 mb-0">Buscar turnos de caja</h3>
                </div>
                <Filter class="text-secondary" size={22} />
            </div>

            <form class="taguara-filter-grid" onsubmit={submit}>
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Desde</span>
                    <input class="form-control" type="date" bind:value={form.from} />
                </label>
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Hasta</span>
                    <input class="form-control" type="date" bind:value={form.to} />
                </label>
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Estado</span>
                    <select class="form-select" bind:value={form.status}>
                        <option value="">Todos</option>
                        <option value="open">Abiertas</option>
                        <option value="closed">Cerradas</option>
                    </select>
                </label>
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Caja</span>
                    <select class="form-select" bind:value={form.cash_register_id}>
                        <option value="">Todas</option>
                        {#each registers as register}
                            <option value={register.id}>{register.name}</option>
                        {/each}
                    </select>
                </label>
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Cajero</span>
                    <select class="form-select" bind:value={form.user_id}>
                        <option value="">Todos</option>
                        {#each cashiers as cashier}
                            <option value={cashier.id}>{cashier.name}</option>
                        {/each}
                    </select>
                </label>
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Cuadre</span>
                    <select class="form-select" bind:value={form.difference}>
                        <option value="">Todos</option>
                        <option value="balanced">Cuadradas</option>
                        <option value="short">Faltantes</option>
                        <option value="over">Sobrantes</option>
                    </select>
                </label>
                <div class="d-flex align-items-end gap-2">
                    <button class="btn btn-taguara" type="submit">Aplicar</button>
                    <button class="btn btn-light border taguara-icon-button" type="button" aria-label="Limpiar filtros" onclick={reset}>
                        <RotateCcw size={17} />
                    </button>
                </div>
            </form>
        </section>

        <section class="row g-3">
            <div class="col-12 col-md-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-primary"><Clock size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Turnos</p>
                        <p class="h3 mb-1">{summary.sessions_count}</p>
                        <p class="small text-secondary mb-0">{summary.open_count} abiertos · {summary.closed_count} cerrados</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-success"><CircleDollarSign size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Ventas</p>
                        <p class="h3 mb-1">{fmt(summary.sales_total)}</p>
                        <p class="small text-secondary mb-0">Total asociado a turnos</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-3">
                <article class="taguara-kpi-card">
                    <span class={`taguara-kpi-icon ${summary.difference_total === 0 ? 'text-bg-secondary' : 'text-bg-warning'}`}><ShieldAlert size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Diferencia neta</p>
                        <p class={`h3 mb-1 ${differenceClass(summary.difference_total)}`}>{fmt(summary.difference_total)}</p>
                        <p class="small text-secondary mb-0">Sobrantes menos faltantes</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-info"><Eye size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Resultado</p>
                        <p class="h3 mb-1">{sessions.total}</p>
                        <p class="small text-secondary mb-0">Registros filtrados</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Historial</p>
                    <h3 class="h5 mb-0">Aperturas y cierres</h3>
                </div>
                <span class="badge text-bg-light border text-secondary">Pagina {sessions.current_page} de {sessions.last_page}</span>
            </div>

            <div class="taguara-table-wrapper">
                <table class="taguara-table">
                    <thead>
                        <tr>
                            <th>Caja</th>
                            <th>Cajero</th>
                            <th>Apertura</th>
                            <th>Cierre</th>
                            <th class="text-end">Ventas</th>
                            <th class="text-end">Esperado</th>
                            <th class="text-end">Contado</th>
                            <th class="text-end">Diferencia</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each sessions.data as session}
                            <tr>
                                <td>
                                    <div class="taguara-table-name">{session.register.name}</div>
                                    <div class="taguara-table-sub">{session.register.code}</div>
                                </td>
                                <td>{session.cashier}</td>
                                <td class="small text-secondary">{session.opened_at}</td>
                                <td class="small text-secondary">{session.closed_at ?? 'Sin cierre'}</td>
                                <td class="text-end">
                                    <div class="fw-semibold">{fmt(session.sales_total)}</div>
                                    <div class="taguara-table-sub">{session.sales_count} ventas</div>
                                </td>
                                <td class="text-end">{fmt(session.expected_closing)}</td>
                                <td class="text-end">{session.actual_closing_amount === null ? '—' : fmt(session.actual_closing_amount)}</td>
                                <td class={`text-end ${differenceClass(session.difference)}`}>{session.difference === null ? '—' : fmt(session.difference)}</td>
                                <td><span class={`badge ${statusClass(session.status.value)}`}>{session.status.label}</span></td>
                                <td class="text-end">
                                    <Link class="btn btn-sm btn-light border taguara-icon-button-sm" href={`/reports/cash-sessions/${session.uuid}`} aria-label="Ver detalle">
                                        <Eye size={15} />
                                    </Link>
                                </td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="10">
                                    <div class="taguara-empty-state" style="min-height:130px">
                                        <ShieldAlert size={30} />
                                        <p class="text-secondary small mb-0">No hay turnos con estos filtros.</p>
                                    </div>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>

            {#if sessions.links.length > 3}
                <nav class="taguara-pagination mt-3" aria-label="Paginacion de cajas">
                    {#each sessions.links as link}
                        {#if link.url}<Link class={`btn btn-sm ${link.active ? 'btn-taguara' : 'btn-light border'}`} href={link.url}>{@html link.label}</Link>
                        {:else}<span class="btn btn-sm btn-light border disabled">{@html link.label}</span>{/if}
                    {/each}
                </nav>
            {/if}
        </section>
    </div>
</AppLayout>
