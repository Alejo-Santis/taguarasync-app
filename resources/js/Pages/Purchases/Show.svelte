<script>
    import { Link, router, useForm } from '@inertiajs/svelte';
    import {
        ArrowLeft,
        Download,
        Package,
        Pencil,
        ReceiptText,
        ShieldAlert,
        ShieldCheck,
        ShieldQuestion,
        X,
    } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';

    let { auth, receipt, errors = {} } = $props();

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const fmt = (v) => money.format(v ?? 0);
    const pct = (v) => `${Number(v ?? 0).toFixed(0)}%`;

    const statusClass = (s) => {
        if (s === 'received') return 'text-bg-success';
        if (s === 'voided') return 'text-bg-danger';
        return 'text-bg-secondary';
    };
    const statusLabel = (s) => ({ received: 'Recibida', pending: 'Pendiente', voided: 'Anulada' }[s] ?? s);

    const radianClass = (s) => {
        if (s === 'validated') return 'text-bg-success';
        if (s === 'rejected' || s === 'error') return 'text-bg-danger';
        if (s === 'pending') return 'text-bg-warning text-dark';
        return 'text-bg-light border text-secondary';
    };
    const radianLabel = (s) => ({
        validated: 'RADIAN validada',
        rejected: 'RADIAN rechazada',
        error: 'Error RADIAN',
        pending: 'Pendiente RADIAN',
    }[s] ?? 'Sin validación RADIAN');

    const RadianIcon = (s) => {
        if (s === 'validated') return ShieldCheck;
        if (s === 'rejected' || s === 'error') return ShieldAlert;
        return ShieldQuestion;
    };

    let editingCufe = $state(false);
    const cufeForm = useForm(() => ({ supplier_cufe: receipt.supplier_cufe ?? '' }));

    const saveCufe = () => {
        cufeForm.patch(`/purchases/${receipt.uuid}/cufe`, {
            preserveScroll: true,
            onSuccess: () => { editingCufe = false; },
        });
    };

    const validateRadian = () => {
        router.post(`/purchases/${receipt.uuid}/validate-radian`, {}, { preserveScroll: true });
    };

    const daysUntilExpiry = (dateStr) => {
        if (!dateStr) { return null; }
        const diff = Math.ceil((new Date(dateStr) - new Date()) / 86400000);
        return diff;
    };
</script>

<AppLayout title={receipt.document_number} activeSection="purchases" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Compras</p>
                <h2 class="h3 mb-2">{receipt.document_number}</h2>
                <p class="text-secondary mb-0">
                    {receipt.supplier.name}
                    {#if receipt.supplier.nit} · NIT {receipt.supplier.nit}{/if}
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap justify-content-end align-items-center">
                <span class={`badge fs-6 ${statusClass(receipt.status)}`}>{statusLabel(receipt.status)}</span>
                {#if receipt.radian_status}
                    {@const RIcon = RadianIcon(receipt.radian_status)}
                    <span class={`badge fs-6 d-inline-flex align-items-center gap-1 ${radianClass(receipt.radian_status)}`}>
                        <RIcon size={13} />
                        {radianLabel(receipt.radian_status)}
                    </span>
                {/if}
                {#if receipt.has_attachment}
                    <a class="btn btn-light border d-inline-flex align-items-center gap-2" href={`/purchases/${receipt.uuid}/attachment`}>
                        <Download size={17} />
                        Adjunto
                    </a>
                {/if}
                <Link href="/purchases" class="btn btn-light border d-inline-flex align-items-center gap-2">
                    <ArrowLeft size={17} />
                    Compras
                </Link>
            </div>
        </section>

        <!-- Info general -->
        <div class="row g-3 mb-3">
            <div class="col-12 col-md-6">
                <div class="taguara-panel h-100">
                    <p class="text-uppercase small fw-semibold text-success mb-3">Información del documento</p>
                    <dl class="row g-2 mb-0" style="font-size:.9rem">
                        <dt class="col-5 text-secondary fw-normal">Proveedor</dt>
                        <dd class="col-7 mb-0">
                            <Link href={`/purchases/payables/${receipt.supplier.uuid}`} class="text-decoration-none fw-semibold">
                                {receipt.supplier.name}
                            </Link>
                        </dd>
                        <dt class="col-5 text-secondary fw-normal">Fecha doc.</dt>
                        <dd class="col-7 mb-0">{receipt.document_date}</dd>
                        {#if receipt.received_at}
                            <dt class="col-5 text-secondary fw-normal">Recibida</dt>
                            <dd class="col-7 mb-0">{receipt.received_at}</dd>
                        {/if}
                        {#if receipt.user}
                            <dt class="col-5 text-secondary fw-normal">Registrado por</dt>
                            <dd class="col-7 mb-0">{receipt.user}</dd>
                        {/if}
                        {#if receipt.purchase_order_number}
                            <dt class="col-5 text-secondary fw-normal">Orden de compra</dt>
                            <dd class="col-7 mb-0">
                                <Link href={`/purchases/orders/${receipt.purchase_order_uuid}`} class="text-decoration-none">
                                    {receipt.purchase_order_number}
                                </Link>
                            </dd>
                        {/if}
                        {#if receipt.notes}
                            <dt class="col-5 text-secondary fw-normal">Notas</dt>
                            <dd class="col-7 mb-0">{receipt.notes}</dd>
                        {/if}
                    </dl>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="taguara-panel h-100">
                    <p class="text-uppercase small fw-semibold text-success mb-3">Totales</p>
                    <dl class="row g-2 mb-0" style="font-size:.9rem">
                        <dt class="col-6 text-secondary fw-normal">Subtotal</dt>
                        <dd class="col-6 mb-0 text-end">{fmt(receipt.subtotal)}</dd>
                        <dt class="col-6 text-secondary fw-normal">IVA</dt>
                        <dd class="col-6 mb-0 text-end">{fmt(receipt.tax_total)}</dd>
                        <dt class="col-6 fw-semibold border-top pt-2">Total</dt>
                        <dd class="col-6 mb-0 text-end fw-semibold border-top pt-2 fs-5">{fmt(receipt.total)}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Items -->
        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Líneas</p>
                    <h3 class="h5 mb-0">{receipt.items.length} producto{receipt.items.length !== 1 ? 's' : ''} recibidos</h3>
                </div>
            </div>
            <div class="taguara-table-wrapper mt-3">
                <table class="taguara-table">
                    <thead>
                        <tr>
                            <th>Producto / Descripción</th>
                            <th>Lote</th>
                            <th>Vence</th>
                            <th class="text-end">Cant.</th>
                            <th class="text-end">Costo unit.</th>
                            <th class="text-end">IVA</th>
                            <th class="text-end">Total línea</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each receipt.items as item}
                            {@const days = daysUntilExpiry(item.expires_on)}
                            <tr>
                                <td>
                                    <div class="taguara-table-name">{item.product_name ?? item.description}</div>
                                    {#if item.product_name && item.description !== item.product_name}
                                        <div class="taguara-table-sub">{item.description}</div>
                                    {/if}
                                </td>
                                <td class="text-secondary" style="font-size:.85rem">{item.lot_number ?? '—'}</td>
                                <td>
                                    {#if item.expires_on}
                                        <span class={`badge ${days !== null && days <= 90 ? (days <= 30 ? 'text-bg-danger' : 'text-bg-warning text-dark') : 'text-bg-light border text-secondary'}`}>
                                            {item.expires_on}
                                        </span>
                                    {:else}
                                        <span class="text-secondary">—</span>
                                    {/if}
                                </td>
                                <td class="text-end">{item.quantity}</td>
                                <td class="text-end">{fmt(item.unit_cost)}</td>
                                <td class="text-end text-secondary">{pct(item.tax_rate)}</td>
                                <td class="text-end fw-semibold">{fmt(item.line_total)}</td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="7">
                                    <div class="taguara-empty-state" style="min-height:100px">
                                        <Package size={28} />
                                        <p class="text-secondary small mb-0">Sin líneas registradas.</p>
                                    </div>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold">
                            <td colspan="6" class="text-end">Total</td>
                            <td class="text-end">{fmt(receipt.total)}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <!-- Panel RADIAN -->
        {#if receipt.status !== 'voided'}
            <section class="taguara-panel">
                <div class="taguara-panel-header">
                    <div>
                        <p class="text-uppercase small fw-semibold text-success mb-1">Facturación electrónica</p>
                        <h3 class="h5 mb-0">RADIAN — Validación del documento de proveedor</h3>
                    </div>
                    {#if receipt.radian_status}
                        {@const RIcon = RadianIcon(receipt.radian_status)}
                        <span class={`badge fs-6 d-inline-flex align-items-center gap-1 ${radianClass(receipt.radian_status)}`}>
                            <RIcon size={13} />
                            {radianLabel(receipt.radian_status)}
                        </span>
                    {/if}
                </div>

                <!-- CUFE del proveedor -->
                <div class="mt-3">
                    <p class="small text-secondary mb-2">
                        El <strong>CUFE</strong> es la clave única del documento electrónico del proveedor en la DIAN.
                        Se necesita para enviar eventos RADIAN (030 acuse · 032 recibo del bien).
                    </p>
                    {#if editingCufe}
                        <div class="d-flex gap-2 align-items-start">
                            <input
                                class="form-control form-control-sm"
                                class:is-invalid={cufeForm.errors.supplier_cufe}
                                type="text"
                                placeholder="Pega aquí el CUFE del proveedor (96 caracteres)"
                                bind:value={cufeForm.supplier_cufe}
                                style="font-family: monospace; font-size: 0.8rem"
                            />
                            <button class="btn btn-sm btn-taguara flex-shrink-0" type="button" onclick={saveCufe} disabled={cufeForm.processing}>Guardar</button>
                            <button class="btn btn-sm btn-light border flex-shrink-0" type="button" onclick={() => { editingCufe = false; cufeForm.reset(); }}><X size={15} /></button>
                        </div>
                        {#if cufeForm.errors.supplier_cufe}
                            <div class="text-danger small mt-1">{cufeForm.errors.supplier_cufe}</div>
                        {/if}
                    {:else}
                        <div class="d-flex align-items-center gap-2">
                            {#if receipt.supplier_cufe}
                                <code class="small text-secondary text-break flex-grow-1">{receipt.supplier_cufe}</code>
                            {:else}
                                <span class="small text-secondary fst-italic flex-grow-1">Sin CUFE — ingresa el del documento del proveedor</span>
                            {/if}
                            <button class="btn btn-sm btn-light border flex-shrink-0" type="button" onclick={() => { cufeForm.supplier_cufe = receipt.supplier_cufe ?? ''; editingCufe = true; }}>
                                <Pencil size={14} />
                            </button>
                        </div>
                    {/if}
                </div>

                <!-- Error RADIAN -->
                {#if errors.radian || receipt.radian_error_message}
                    <div class="alert alert-danger mt-3 mb-0 small py-2">
                        {errors.radian ?? receipt.radian_error_message}
                    </div>
                {/if}

                <!-- Botón validar -->
                {#if receipt.radian_status !== 'validated'}
                    <div class="mt-3">
                        <button
                            class="btn btn-light border d-inline-flex align-items-center gap-2"
                            type="button"
                            onclick={validateRadian}
                            disabled={!receipt.supplier_cufe}
                            title={!receipt.supplier_cufe ? 'Ingresa el CUFE antes de validar' : ''}
                        >
                            <ShieldQuestion size={17} />
                            Enviar eventos RADIAN (030 + 032)
                        </button>
                        {#if receipt.radian_checked_at}
                            <span class="text-secondary small ms-2">Última consulta: {receipt.radian_checked_at}</span>
                        {/if}
                    </div>
                {/if}
            </section>
        {/if}
    </div>
</AppLayout>
