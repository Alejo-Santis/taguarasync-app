<script>
    import { Link, router } from '@inertiajs/svelte';
    import {
        AlertCircle,
        AlertTriangle,
        CheckCircle2,
        Clock,
        FileText,
        RefreshCw,
        RotateCcw,
        Search,
        ShieldCheck,
        ShieldOff,
        XCircle,
    } from '@lucide/svelte';
    import AppLayout from '../../../Layouts/AppLayout.svelte';

    let { auth, submissions, filters, stats } = $props();

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const fmt = (v) => money.format(v ?? 0);

    let filterStatus = $state(filters.status ?? '');
    let filterDocType = $state(filters.doc_type ?? '');

    const applyFilters = () => {
        router.get('/fe/submissions', { status: filterStatus, doc_type: filterDocType }, { preserveState: true, replace: true });
    };

    const resetFilters = () => {
        filterStatus = '';
        filterDocType = '';
        router.get('/fe/submissions', {}, { preserveState: true, replace: true });
    };

    const retrySubmission = (id) => {
        router.post(`/fe/submissions/${id}/retry`, {}, { preserveScroll: true });
    };

    const statusBadge = (status) => {
        const map = {
            'sent': 'text-bg-info text-dark',
            'accepted': 'text-bg-success',
            'accepted_rule90': 'text-bg-success',
            'rejected': 'text-bg-danger',
            'pending': 'text-bg-warning text-dark',
        };
        return map[status] ?? 'text-bg-secondary';
    };

    const statusLabel = (status) => {
        const map = {
            'sent': 'Enviada',
            'accepted': 'Aceptada',
            'accepted_rule90': 'Aceptada (Regla 90)',
            'rejected': 'Rechazada',
            'pending': 'Pendiente',
        };
        return map[status] ?? status ?? '—';
    };

    const docTypeLabel = (type) => ({ invoice: 'Factura', credit_note: 'Nota crédito', debit_note: 'Nota débito' })[type] ?? type;
</script>

<AppLayout title="Facturación Electrónica" activeSection="fe-submissions" {auth}>
    <div class="taguara-products">

        <!-- Header -->
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Facturación electrónica</p>
                <h2 class="h3 mb-2">Panel de transmisiones DIAN</h2>
                <p class="text-secondary mb-0">Estado de todos los documentos enviados a la DIAN a través de Nextpyme.</p>
            </div>
            <FileText class="text-secondary" size={22} />
        </section>

        <!-- KPIs -->
        <div class="row g-3">
            <div class="col-6 col-md-2">
                <div class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-light"><FileText size={18} /></span>
                    <div>
                        <div class="fw-bold" style="font-size:1.4rem">{stats.total.toLocaleString()}</div>
                        <div class="text-secondary small">Total envíos</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-success"><CheckCircle2 size={18} /></span>
                    <div>
                        <div class="fw-bold text-success" style="font-size:1.4rem">{stats.accepted.toLocaleString()}</div>
                        <div class="text-secondary small">Aceptadas</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-info"><ShieldCheck size={18} /></span>
                    <div>
                        <div class="fw-bold" style="font-size:1.4rem">{stats.sent.toLocaleString()}</div>
                        <div class="text-secondary small">Enviadas</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-warning"><Clock size={18} /></span>
                    <div>
                        <div class="fw-bold text-warning" style="font-size:1.4rem">{stats.pending.toLocaleString()}</div>
                        <div class="text-secondary small">Pendientes</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-danger"><XCircle size={18} /></span>
                    <div>
                        <div class="fw-bold text-danger" style="font-size:1.4rem">{stats.rejected.toLocaleString()}</div>
                        <div class="text-secondary small">Rechazadas</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-secondary"><AlertTriangle size={18} /></span>
                    <div>
                        <div class="fw-bold" style="font-size:1.4rem">{stats.non_recoverable.toLocaleString()}</div>
                        <div class="text-secondary small">No recuperables</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de transmisiones -->
        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Transmisiones</p>
                    <h3 class="h5 mb-0">{submissions.total} registros</h3>
                </div>
            </div>

            <!-- Filtros -->
            <div class="taguara-filter-grid mb-3" style="grid-template-columns: minmax(160px,200px) minmax(160px,200px) auto">
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Estado</span>
                    <select class="form-select" bind:value={filterStatus} onchange={applyFilters}>
                        <option value="">Todos</option>
                        <option value="sent">Enviada</option>
                        <option value="accepted">Aceptada</option>
                        <option value="accepted_rule90">Aceptada (Regla 90)</option>
                        <option value="rejected">Rechazada</option>
                    </select>
                </label>
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Tipo</span>
                    <select class="form-select" bind:value={filterDocType} onchange={applyFilters}>
                        <option value="">Todos</option>
                        <option value="invoice">Factura</option>
                        <option value="credit_note">Nota crédito</option>
                    </select>
                </label>
                <div class="d-flex align-items-end">
                    <button class="btn btn-light border taguara-icon-button" type="button" onclick={resetFilters} aria-label="Limpiar">
                        <RotateCcw size={16} />
                    </button>
                </div>
            </div>

            <div class="taguara-table-wrapper">
                <table class="taguara-table">
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Tipo</th>
                            <th class="text-center">Intentos</th>
                            <th>Estado DIAN</th>
                            <th>CUFE / Clave XML</th>
                            <th>Enviado</th>
                            <th>Respondido</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each submissions.data as item}
                            <tr>
                                <td>
                                    {#if item.sale_uuid}
                                        <Link href={`/sales/${item.sale_uuid}`} class="fw-semibold text-decoration-none" onclick={(e) => e.stopPropagation()}>
                                            {item.document_number ?? `ID ${item.document_id}`}
                                        </Link>
                                    {:else}
                                        <span class="fw-semibold">{item.document_number ?? `ID ${item.document_id}`}</span>
                                    {/if}
                                </td>
                                <td>
                                    <span class="badge text-bg-light border">{docTypeLabel(item.document_type)}</span>
                                </td>
                                <td class="text-center">
                                    <span class={`badge ${item.attempts >= 3 ? 'text-bg-danger' : item.attempts >= 2 ? 'text-bg-warning text-dark' : 'text-bg-light border'}`}>
                                        {item.attempts}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge {statusBadge(item.response_status)}">{statusLabel(item.response_status)}</span>
                                        {#if item.is_non_recoverable}
                                            <AlertTriangle size={13} class="text-warning" title="Error no recuperable" />
                                        {/if}
                                    </div>
                                    {#if item.error_message}
                                        <div class="text-danger mt-1" style="font-size:.75rem; max-width:220px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis" title={item.error_message}>
                                            {item.error_message}
                                        </div>
                                    {/if}
                                </td>
                                <td>
                                    {#if item.xml_document_key}
                                        <span class="font-monospace" style="font-size:.7rem" title={item.xml_document_key}>
                                            {item.xml_document_key.slice(0, 16)}…
                                        </span>
                                    {:else}
                                        <span class="text-secondary">—</span>
                                    {/if}
                                </td>
                                <td class="text-secondary" style="font-size:.8rem">{item.submitted_at ?? '—'}</td>
                                <td class="text-secondary" style="font-size:.8rem">{item.responded_at ?? '—'}</td>
                                <td>
                                    {#if !item.is_non_recoverable && (item.response_status === 'rejected' || item.fe_status === 'pending')}
                                        <button
                                            class="btn btn-sm btn-light border d-inline-flex align-items-center gap-1"
                                            type="button"
                                            title="Reintentar envío"
                                            onclick={() => retrySubmission(item.id)}
                                        >
                                            <RefreshCw size={13} />
                                            Reintentar
                                        </button>
                                    {/if}
                                </td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="8">
                                    <div class="taguara-empty-state">
                                        <ShieldCheck size={34} />
                                        <h4 class="h6 mb-1">Sin transmisiones registradas</h4>
                                        <p class="text-secondary mb-0">Las facturas y notas crédito enviadas a la DIAN aparecerán aquí.</p>
                                    </div>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>

            {#if submissions.links.length > 3}
                <nav class="taguara-pagination mt-3">
                    {#each submissions.links as link}
                        {#if link.url}
                            <Link class={`btn btn-sm ${link.active ? 'btn-taguara' : 'btn-light border'}`} href={link.url}>{@html link.label}</Link>
                        {:else}
                            <span class="btn btn-sm btn-light border disabled">{@html link.label}</span>
                        {/if}
                    {/each}
                </nav>
            {/if}
        </section>
    </div>
</AppLayout>
