<script>
    import { router } from '@inertiajs/svelte';
    import { ActivitySquare, AlertTriangle, ArrowUpDown, CheckCircle2, FileText, RotateCcw, ShieldCheck, ShieldQuestion, XCircle } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';

    let { auth, active_tab, is_super_admin, fe, radian, movements } = $props();

    const tabs = [
        { key: 'fe', label: 'API Facturación FE', icon: FileText },
        { key: 'radian', label: 'RADIAN', icon: ShieldCheck },
        { key: 'movements', label: 'Movimientos de inventario', icon: ArrowUpDown },
    ];

    const setTab = (key) => router.get('/audit', { tab: key }, { preserveState: true, replace: true });

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const fmt = (v) => money.format(v ?? 0);

    const feStatusClass = (s) => {
        if (s === 'accepted') return 'text-bg-success';
        if (s === 'rejected' || s === 'failed') return 'text-bg-danger';
        if (s === 'sent' || s === 'processing') return 'text-bg-primary';
        return 'text-bg-light border text-secondary';
    };

    const radianStatusClass = (s) => {
        if (s === 'validated') return 'text-bg-success';
        if (s === 'rejected' || s === 'error') return 'text-bg-danger';
        return 'text-bg-warning text-dark';
    };

    const movementTypeLabel = (t) => ({
        sale: 'Venta',
        purchase: 'Compra',
        adjustment_in: 'Ajuste entrada',
        adjustment_out: 'Ajuste salida',
        return: 'Devolución',
        transfer: 'Traslado',
    }[t] ?? t);

    const movementTypeClass = (t) => {
        if (t === 'sale' || t === 'adjustment_out') return 'text-bg-danger';
        if (t === 'purchase' || t === 'adjustment_in') return 'text-bg-success';
        return 'text-bg-secondary';
    };
</script>

<AppLayout title="Auditorías" activeSection="audit" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Administración</p>
                <h2 class="h3 mb-2">Auditorías del sistema</h2>
                <p class="text-secondary mb-0">Historial de llamadas FE, eventos RADIAN y movimientos de inventario.</p>
            </div>
            <ActivitySquare class="text-secondary" size={22} />
        </section>

        <!-- Tabs -->
        <nav class="taguara-config-nav" aria-label="Pestañas de auditoría">
            {#each tabs as tab}
                {@const Icon = tab.icon}
                <button
                    type="button"
                    class={`taguara-config-tab d-inline-flex align-items-center gap-2 ${tab.key === active_tab ? 'active' : ''}`}
                    onclick={() => setTab(tab.key)}
                >
                    <Icon size={15} />
                    {tab.label}
                </button>
            {/each}
        </nav>

        <!-- ── Tab: API FE ─────────────────────────────────────────── -->
        {#if active_tab === 'fe'}
            {#if !fe}
                <div class="taguara-panel">
                    <div class="taguara-empty-state" style="min-height:120px">
                        <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
                        <p class="text-secondary small mb-0">Cargando envíos FE...</p>
                    </div>
                </div>
            {:else}
                <section class="row g-3">
                    <div class="col-12 col-sm-4">
                        <article class="taguara-kpi-card">
                            <span class="taguara-kpi-icon text-bg-primary"><FileText size={20} /></span>
                            <div>
                                <p class="text-secondary small mb-1">Total envíos</p>
                                <p class="h3 mb-0">{fe.stats.total}</p>
                            </div>
                        </article>
                    </div>
                    <div class="col-12 col-sm-4">
                        <article class="taguara-kpi-card">
                            <span class="taguara-kpi-icon {fe.stats.errors > 0 ? 'text-bg-danger' : 'text-bg-success'}">
                                {#if fe.stats.errors > 0}<XCircle size={20} />{:else}<CheckCircle2 size={20} />{/if}
                            </span>
                            <div>
                                <p class="text-secondary small mb-1">Errores no recuperables</p>
                                <p class="h3 mb-0 {fe.stats.errors > 0 ? 'text-danger' : ''}">{fe.stats.errors}</p>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Envíos API</p>
                            <h3 class="h5 mb-0">Últimos 100 envíos a la API de FE</h3>
                        </div>
                        <a href="/fe/submissions" class="btn btn-sm btn-light border">Ver historial completo →</a>
                    </div>
                    <div class="taguara-table-wrapper mt-3">
                        <table class="taguara-table">
                            <thead>
                                <tr>
                                    {#if is_super_admin}<th>Empresa</th>{/if}
                                    <th>Tipo</th>
                                    <th>XML Document Key</th>
                                    <th class="text-center">Intentos</th>
                                    <th>Estado</th>
                                    <th>Enviado</th>
                                    <th>Respondido</th>
                                    <th>Error</th>
                                </tr>
                            </thead>
                            <tbody>
                                {#each fe.rows as row}
                                    <tr>
                                        {#if is_super_admin}<td class="fw-semibold" style="font-size:.85rem">{row.tenant ?? '—'}</td>{/if}
                                        <td><span class="badge text-bg-light border text-secondary">{row.document_type}</span></td>
                                        <td class="text-secondary" style="font-size:.8rem;max-width:180px">
                                            <div class="text-truncate" title={row.xml_document_key}>{row.xml_document_key ?? '—'}</div>
                                        </td>
                                        <td class="text-center">{row.attempts}</td>
                                        <td>
                                            <span class={`badge ${feStatusClass(row.response_status)}`}>
                                                {row.is_non_recoverable ? '⚠ ' : ''}{row.response_status ?? 'pending'}
                                            </span>
                                        </td>
                                        <td class="text-secondary" style="font-size:.8rem">{row.submitted_at ?? '—'}</td>
                                        <td class="text-secondary" style="font-size:.8rem">{row.responded_at ?? '—'}</td>
                                        <td class="text-danger" style="font-size:.78rem;max-width:220px">
                                            {#if row.error}<div class="text-truncate" title={row.error}>{row.error}</div>{/if}
                                        </td>
                                    </tr>
                                {:else}
                                    <tr><td colspan={is_super_admin ? 8 : 7}>
                                        <div class="taguara-empty-state" style="min-height:100px">
                                            <FileText size={28} />
                                            <p class="text-secondary small mb-0">No hay envíos FE registrados.</p>
                                        </div>
                                    </td></tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </section>
            {/if}

        <!-- ── Tab: RADIAN ────────────────────────────────────────── -->
        {:else if active_tab === 'radian'}
            {#if !radian}
                <div class="taguara-panel">
                    <div class="taguara-empty-state" style="min-height:120px">
                        <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
                        <p class="text-secondary small mb-0">Cargando historial RADIAN...</p>
                    </div>
                </div>
            {:else}
                <section class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">RADIAN</p>
                            <h3 class="h5 mb-0">Historial de validaciones ({radian.rows.length})</h3>
                        </div>
                    </div>
                    <div class="taguara-table-wrapper mt-3">
                        <table class="taguara-table">
                            <thead>
                                <tr>
                                    {#if is_super_admin}<th>Empresa</th>{/if}
                                    <th>Documento</th>
                                    <th>Proveedor</th>
                                    <th>CUFE</th>
                                    <th>Estado</th>
                                    <th>Verificado</th>
                                    <th>Detalle</th>
                                </tr>
                            </thead>
                            <tbody>
                                {#each radian.rows as row}
                                    <tr>
                                        {#if is_super_admin}<td class="fw-semibold" style="font-size:.85rem">{row.tenant ?? '—'}</td>{/if}
                                        <td>
                                            <a href={`/purchases/${row.uuid}`} class="text-decoration-none fw-semibold small">{row.document_number}</a>
                                        </td>
                                        <td class="text-secondary" style="font-size:.875rem">{row.supplier ?? '—'}</td>
                                        <td style="font-size:.75rem;max-width:160px">
                                            {#if row.supplier_cufe}
                                                <code class="text-secondary text-truncate d-block" title={row.supplier_cufe}>{row.supplier_cufe.slice(0, 20)}…</code>
                                            {:else}
                                                <span class="text-secondary fst-italic">Sin CUFE</span>
                                            {/if}
                                        </td>
                                        <td>
                                            {#if row.radian_status}
                                                <span class={`badge ${radianStatusClass(row.radian_status)}`}>
                                                    {row.radian_status_label ?? row.radian_status}
                                                </span>
                                            {/if}
                                        </td>
                                        <td class="text-secondary" style="font-size:.8rem">{row.radian_checked_at}</td>
                                        <td class="text-danger" style="font-size:.78rem;max-width:200px">
                                            {#if row.radian_error_message}
                                                <div class="text-truncate" title={row.radian_error_message}>{row.radian_error_message}</div>
                                            {/if}
                                        </td>
                                    </tr>
                                {:else}
                                    <tr><td colspan={is_super_admin ? 7 : 6}>
                                        <div class="taguara-empty-state" style="min-height:100px">
                                            <ShieldQuestion size={28} />
                                            <p class="text-secondary small mb-0">No hay validaciones RADIAN registradas.</p>
                                        </div>
                                    </td></tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </section>
            {/if}

        <!-- ── Tab: Movimientos ───────────────────────────────────── -->
        {:else if active_tab === 'movements'}
            {#if !movements}
                <div class="taguara-panel">
                    <div class="taguara-empty-state" style="min-height:120px">
                        <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
                        <p class="text-secondary small mb-0">Cargando movimientos...</p>
                    </div>
                </div>
            {:else}
                <section class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Inventario</p>
                            <h3 class="h5 mb-0">Últimos 200 movimientos de inventario</h3>
                        </div>
                        <AlertTriangle class="text-secondary" size={18} />
                    </div>
                    <div class="taguara-table-wrapper mt-3">
                        <table class="taguara-table">
                            <thead>
                                <tr>
                                    {#if is_super_admin}<th>Empresa</th>{/if}
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Producto</th>
                                    <th class="text-end">Δ Cantidad</th>
                                    <th class="text-end">Antes → Después</th>
                                    <th>Referencia</th>
                                    <th>Usuario</th>
                                    <th>Notas</th>
                                </tr>
                            </thead>
                            <tbody>
                                {#each movements.rows as row}
                                    <tr>
                                        {#if is_super_admin}<td class="fw-semibold" style="font-size:.85rem">{row.tenant ?? '—'}</td>{/if}
                                        <td class="text-secondary" style="font-size:.8rem">{row.occurred_at}</td>
                                        <td>
                                            <span class={`badge ${movementTypeClass(row.type)}`}>{movementTypeLabel(row.type)}</span>
                                        </td>
                                        <td>
                                            <div class="taguara-table-name">{row.product ?? '—'}</div>
                                        </td>
                                        <td class="text-end fw-semibold {row.quantity_delta > 0 ? 'text-success' : 'text-danger'}">
                                            {row.quantity_delta > 0 ? '+' : ''}{row.quantity_delta}
                                        </td>
                                        <td class="text-end text-secondary" style="font-size:.8rem">
                                            {row.quantity_before} → {row.quantity_after}
                                        </td>
                                        <td class="text-secondary" style="font-size:.8rem">{row.reference_code ?? '—'}</td>
                                        <td class="text-secondary" style="font-size:.8rem">{row.user ?? '—'}</td>
                                        <td class="text-secondary" style="font-size:.78rem">{row.notes ?? ''}</td>
                                    </tr>
                                {:else}
                                    <tr><td colspan={is_super_admin ? 9 : 8}>
                                        <div class="taguara-empty-state" style="min-height:100px">
                                            <RotateCcw size={28} />
                                            <p class="text-secondary small mb-0">Sin movimientos registrados.</p>
                                        </div>
                                    </td></tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </section>
            {/if}
        {/if}
    </div>
</AppLayout>
