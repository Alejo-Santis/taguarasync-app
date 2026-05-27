<script>
    import { Link, useForm } from '@inertiajs/svelte';
    import {
        AlertCircle,
        ArrowLeft,
        Boxes,
        CheckCircle2,
        ChevronDown,
        ChevronUp,
        Download,
        FileSpreadsheet,
        Info,
        Package,
        Plus,
        Save,
        Trash2,
        Upload,
        X,
    } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';

    let { auth, options } = $props();

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });

    const productById = (productId) => options.products.find((p) => Number(p.id) === Number(productId));
    const defaultLotNumber = () => `INICIAL-${new Date().getFullYear()}`;

    const defaultItem = () => {
        const product = options.products[0] ?? null;
        return {
            product_id: product?.id ?? '',
            product_presentation_id: product?.presentations?.[0]?.id ?? '',
            lot_number: defaultLotNumber(),
            expires_on: '',
            quantity: 1,
            unit_cost: product?.purchase_price ?? 0,
        };
    };

    const form = useForm(() => ({
        items: [defaultItem()],
    }));

    const error = (key) => form.errors[key];

    const totalUnits = $derived(form.items.reduce((sum, item) => sum + Number(item.quantity || 0), 0));
    const totalValue = $derived(
        form.items.reduce((sum, item) => sum + Number(item.quantity || 0) * Number(item.unit_cost || 0), 0)
    );

    const setProduct = (index) => {
        const product = productById(form.items[index].product_id);
        form.items[index] = {
            ...form.items[index],
            product_presentation_id: product?.presentations?.[0]?.id ?? '',
            unit_cost: product?.purchase_price ?? 0,
        };
    };

    const addItem = () => {
        form.items = [...form.items, defaultItem()];
    };

    const removeItem = (index) => {
        if (form.items.length === 1) return;
        form.items = form.items.filter((_, i) => i !== index);
    };

    const submit = () => {
        form.post('/inventory/opening');
    };

    // ─── Import from CSV ─────────────────────────────────────────────────────────
    let importOpen = $state(false);
    let importFile = $state(null);
    let importFileInput = $state(null);
    let importLoading = $state(false);
    let importResult = $state(null); // { rows, valid_count, error_count, skipped_count, header_error }
    let importError = $state('');

    const visibleRows = $derived(
        importResult
            ? importResult.rows.filter((r) => r.status !== 'skipped')
            : []
    );

    const getCsrfToken = () => {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : '';
    };

    const handleFileChange = (e) => {
        importFile = e.currentTarget.files?.[0] ?? null;
        importResult = null;
        importError = '';
    };

    const handleFileDrop = (e) => {
        e.preventDefault();
        const file = e.dataTransfer?.files?.[0];
        if (file && (file.name.endsWith('.csv') || file.type === 'text/csv')) {
            importFile = file;
            importResult = null;
            importError = '';
        }
    };

    const parseImport = async () => {
        if (!importFile || importLoading) return;
        importLoading = true;
        importError = '';
        importResult = null;

        const formData = new FormData();
        formData.append('file', importFile);

        try {
            const res = await fetch('/inventory/opening/import', {
                method: 'POST',
                headers: { 'X-XSRF-TOKEN': getCsrfToken() },
                body: formData,
            });

            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                importError = data?.message ?? `Error del servidor (${res.status}). Verifica que el archivo sea un CSV válido.`;
                return;
            }

            importResult = await res.json();

            if (importResult.header_error) {
                importError = importResult.header_error;
                importResult = null;
            }
        } catch {
            importError = 'No se pudo conectar con el servidor. Intenta de nuevo.';
        } finally {
            importLoading = false;
        }
    };

    const applyImport = () => {
        if (!importResult) return;

        const validRows = importResult.rows.filter((r) => r.status === 'ok' || r.status === 'warning');
        if (validRows.length === 0) return;

        const newItems = validRows.map((r) => ({
            product_id: r.product_id,
            product_presentation_id: r.product_presentation_id,
            lot_number: r.lot_number,
            expires_on: r.expires_on,
            quantity: r.quantity,
            unit_cost: r.unit_cost,
        }));

        // Replace the default empty item if it's the only one
        const isDefault =
            form.items.length === 1 &&
            form.items[0].quantity === 1 &&
            form.items[0].lot_number === defaultLotNumber();

        form.items = isDefault ? newItems : [...form.items, ...newItems];

        importOpen = false;
        importFile = null;
        importResult = null;
        importError = '';
    };

    const statusColor = (status) => {
        if (status === 'ok') return 'text-success';
        if (status === 'warning') return 'text-warning';
        return 'text-danger';
    };

    const statusIcon = (status) => {
        if (status === 'ok') return 'ok';
        if (status === 'warning') return 'warn';
        return 'err';
    };
</script>

<AppLayout title="Carga inicial de inventario" activeSection="inventory" {auth}>
    <form class="taguara-product-form" onsubmit={(e) => { e.preventDefault(); submit(); }}>
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Inventario</p>
                <h2 class="h3 mb-2">Carga inicial de stock</h2>
                <p class="text-secondary mb-0">
                    Registra la mercancía que ya tienes físicamente sin necesidad de una compra. Cada línea crea un lote con su cantidad de apertura.
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <Link class="btn btn-light border d-inline-flex align-items-center gap-2" href="/inventory">
                    <ArrowLeft size={18} />
                    Inventario
                </Link>
                <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="submit" disabled={form.processing}>
                    <Save size={18} />
                    Guardar inventario inicial
                </button>
            </div>
        </section>

        <div class="alert d-flex align-items-start gap-2 mb-0" style="background:var(--taguara-soft-blue);border:1px solid #bfdbfe;border-radius:10px;color:#1e40af">
            <Info size={17} class="flex-shrink-0 mt-1" />
            <div class="small">
                <strong>¿Cuándo usar esto?</strong> Cuando tu farmacia ya tiene productos en bodega pero nunca ha procesado compras en el sistema.
                Cada línea registra un movimiento de tipo <strong>Apertura</strong> y activa el lote en el inventario.
                Si tienes facturas de proveedor, usa <a href="/purchases/create" class="fw-semibold" style="color:#1e40af">Recibir compra</a> en su lugar.
            </div>
        </div>

        <!-- ── Import from Excel panel ─────────────────────────────────────── -->
        <div class="taguara-panel" style="border: 1px solid #d1fae5">
            <button
                type="button"
                class="d-flex w-100 align-items-center gap-3 border-0 bg-transparent p-0 text-start"
                onclick={() => { importOpen = !importOpen; }}
            >
                <span class="taguara-kpi-icon text-bg-success flex-shrink-0"><FileSpreadsheet size={18} /></span>
                <div class="flex-grow-1">
                    <p class="fw-semibold mb-0">Importar desde Excel / CSV</p>
                    <p class="taguara-table-sub mb-0">Descarga la plantilla, llénala con tu stock y sube el archivo para previsualizar antes de cargar.</p>
                </div>
                {#if importOpen}
                    <ChevronUp size={17} class="text-secondary flex-shrink-0" />
                {:else}
                    <ChevronDown size={17} class="text-secondary flex-shrink-0" />
                {/if}
            </button>

            {#if importOpen}
                <div class="mt-4" style="border-top:1px solid var(--taguara-border); padding-top:1.25rem">
                    <!-- Step 1: download template -->
                    <div class="d-flex align-items-start gap-3 mb-4">
                        <span class="taguara-setup-step-num flex-shrink-0">1</span>
                        <div>
                            <p class="fw-semibold mb-1 small">Descarga la plantilla</p>
                            <p class="taguara-table-sub mb-2">Contiene todos tus productos activos pre-cargados. Solo agrega cantidad, lote y fecha de vencimiento a los que tengas en bodega.</p>
                            <a
                                class="btn btn-light border d-inline-flex align-items-center gap-2"
                                href="/inventory/opening/template"
                                download
                            >
                                <Download size={16} />
                                Descargar plantilla.csv
                            </a>
                        </div>
                    </div>

                    <!-- Step 2: upload -->
                    <div class="d-flex align-items-start gap-3 mb-4">
                        <span class="taguara-setup-step-num flex-shrink-0">2</span>
                        <div class="w-100">
                            <p class="fw-semibold mb-1 small">Sube el archivo completado</p>

                            <!-- Drop zone -->
                            <!-- svelte-ignore a11y_no_static_element_interactions -->
                            <div
                                class="taguara-import-dropzone {importFile ? 'has-file' : ''}"
                                role="button"
                                tabindex="0"
                                ondrop={handleFileDrop}
                                ondragover={(e) => e.preventDefault()}
                                onclick={() => importFileInput?.click()}
                                onkeydown={(e) => e.key === 'Enter' && importFileInput?.click()}
                            >
                                {#if importFile}
                                    <FileSpreadsheet size={24} class="text-success" />
                                    <div>
                                        <p class="fw-semibold mb-0 small">{importFile.name}</p>
                                        <p class="taguara-table-sub mb-0">{(importFile.size / 1024).toFixed(1)} KB</p>
                                    </div>
                                    <button
                                        type="button"
                                        class="btn btn-link p-0 text-secondary ms-auto"
                                        onclick={(e) => { e.stopPropagation(); importFile = null; importResult = null; importError = ''; }}
                                        aria-label="Quitar archivo"
                                    >
                                        <X size={15} />
                                    </button>
                                {:else}
                                    <Upload size={24} class="text-secondary" />
                                    <div>
                                        <p class="fw-semibold mb-0 small">Arrastra el CSV aquí o haz clic para buscar</p>
                                        <p class="taguara-table-sub mb-0">Formato: .csv (máx. 2 MB)</p>
                                    </div>
                                {/if}
                            </div>

                            <input
                                type="file"
                                accept=".csv,text/csv"
                                class="visually-hidden"
                                bind:this={importFileInput}
                                onchange={handleFileChange}
                            />

                            {#if importError}
                                <div class="alert alert-danger small py-2 mt-2 d-flex gap-2">
                                    <AlertCircle size={15} class="flex-shrink-0 mt-1" />
                                    {importError}
                                </div>
                            {/if}

                            <button
                                type="button"
                                class="btn btn-taguara mt-3 d-inline-flex align-items-center gap-2"
                                onclick={parseImport}
                                disabled={!importFile || importLoading}
                            >
                                {#if importLoading}
                                    <span class="spinner-border spinner-border-sm"></span>
                                    Procesando...
                                {:else}
                                    <FileSpreadsheet size={16} />
                                    Validar archivo
                                {/if}
                            </button>
                        </div>
                    </div>

                    <!-- Step 3: preview & confirm -->
                    {#if importResult}
                        <div class="d-flex align-items-start gap-3">
                            <span class="taguara-setup-step-num flex-shrink-0">3</span>
                            <div class="w-100">
                                <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
                                    <p class="fw-semibold mb-0 small">Revisa y confirma</p>
                                    <span class="badge text-bg-success">{importResult.valid_count} válidas</span>
                                    {#if importResult.error_count > 0}
                                        <span class="badge text-bg-danger">{importResult.error_count} con error</span>
                                    {/if}
                                    {#if importResult.skipped_count > 0}
                                        <span class="badge text-bg-secondary">{importResult.skipped_count} omitidas (sin cantidad)</span>
                                    {/if}
                                </div>

                                {#if visibleRows.length === 0}
                                    <div class="alert alert-warning small py-2">
                                        No se encontraron filas con cantidad. Revisa que llenaste la columna <strong>cantidad</strong> en la plantilla.
                                    </div>
                                {:else}
                                    <div class="taguara-table-wrapper mb-3" style="max-height:360px;overflow-y:auto">
                                        <table class="taguara-table" style="font-size:.8rem">
                                            <thead>
                                                <tr>
                                                    <th style="width:28px"></th>
                                                    <th>Producto</th>
                                                    <th>Presentación</th>
                                                    <th>Lote</th>
                                                    <th>Vence</th>
                                                    <th class="text-end">Cant.</th>
                                                    <th class="text-end">Costo unit.</th>
                                                    <th>Observaciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {#each visibleRows as row}
                                                    <tr class={row.status === 'error' ? 'table-danger' : row.status === 'warning' ? 'table-warning' : ''}>
                                                        <td class="text-center">
                                                            {#if row.status === 'ok'}
                                                                <CheckCircle2 size={14} class="text-success" />
                                                            {:else if row.status === 'warning'}
                                                                <AlertCircle size={14} class="text-warning" />
                                                            {:else}
                                                                <X size={14} class="text-danger" />
                                                            {/if}
                                                        </td>
                                                        <td>{row.product_name}</td>
                                                        <td>{row.presentation_name}</td>
                                                        <td>
                                                            {row.lot_number}
                                                            {#if row.lot_number_auto}
                                                                <span class="badge text-bg-secondary" style="font-size:.6rem">auto</span>
                                                            {/if}
                                                        </td>
                                                        <td>{row.expires_on || '—'}</td>
                                                        <td class="text-end">{row.quantity.toLocaleString('es-CO')}</td>
                                                        <td class="text-end">{money.format(row.unit_cost)}</td>
                                                        <td style="max-width:200px">
                                                            {#if row.errors.length > 0}
                                                                <ul class="mb-0 ps-3" style="font-size:.75rem">
                                                                    {#each row.errors as msg}
                                                                        <li>{msg}</li>
                                                                    {/each}
                                                                </ul>
                                                            {/if}
                                                        </td>
                                                    </tr>
                                                {/each}
                                            </tbody>
                                        </table>
                                    </div>

                                    {#if importResult.valid_count > 0}
                                        <button
                                            type="button"
                                            class="btn btn-taguara d-inline-flex align-items-center gap-2"
                                            onclick={applyImport}
                                        >
                                            <Plus size={16} />
                                            Agregar {importResult.valid_count} {importResult.valid_count === 1 ? 'línea válida' : 'líneas válidas'} al formulario
                                        </button>
                                        <p class="taguara-table-sub mt-2 mb-0">Puedes revisar y editar cada línea en la tabla antes de guardar definitivamente.</p>
                                    {:else}
                                        <div class="alert alert-danger small py-2">
                                            Todas las filas tienen errores. Corrige el archivo y vuelve a subir.
                                        </div>
                                    {/if}
                                {/if}
                            </div>
                        </div>
                    {/if}
                </div>
            {/if}
        </div>

        <!-- ── Manual form ─────────────────────────────────────────────────── -->
        <section class="row g-3">
            <div class="col-12 col-xl-4">
                <div class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Resumen</p>
                            <h3 class="h5 mb-0">Totales</h3>
                        </div>
                        <Boxes class="text-success" size={22} />
                    </div>
                    <div class="taguara-drawer-grid">
                        <span class="taguara-drawer-label">Líneas</span>
                        <span>{form.items.length}</span>
                        <span class="taguara-drawer-label">Unidades totales</span>
                        <span class="fw-semibold">{totalUnits.toLocaleString('es-CO')}</span>
                        <span class="taguara-drawer-label">Valor de inventario</span>
                        <span class="fw-semibold">{money.format(totalValue)}</span>
                    </div>
                    <p class="text-secondary small mt-3 mb-0 d-flex align-items-start gap-2">
                        <Package size={15} class="flex-shrink-0 mt-1" />
                        El número de lote identifica el lote físico. Si no lo conoces, usa el generado automáticamente.
                    </p>
                </div>
            </div>

            <div class="col-12 col-xl-8">
                <div class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Líneas</p>
                            <h3 class="h5 mb-0">Productos a cargar</h3>
                        </div>
                        <button class="btn btn-light border d-inline-flex align-items-center gap-2" type="button" onclick={addItem}>
                            <Plus size={17} />
                            Agregar línea
                        </button>
                    </div>

                    {#if error('items')}<div class="alert alert-danger small">{error('items')}</div>{/if}

                    <div class="taguara-table-wrapper">
                        <table class="taguara-table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Presentación</th>
                                    <th>Lote</th>
                                    <th>Vence</th>
                                    <th class="text-end">Cant.</th>
                                    <th class="text-end">Costo unit.</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                {#each form.items as item, index}
                                    {@const product = productById(item.product_id)}
                                    <tr>
                                        <td style="min-width:220px">
                                            <select
                                                class:is-invalid={error(`items.${index}.product_id`)}
                                                class="form-select form-select-sm"
                                                bind:value={item.product_id}
                                                onchange={() => setProduct(index)}
                                            >
                                                <option value="">Seleccionar</option>
                                                {#each options.products as p}
                                                    <option value={p.id}>{p.name}</option>
                                                {/each}
                                            </select>
                                        </td>
                                        <td style="min-width:150px">
                                            <select
                                                class:is-invalid={error(`items.${index}.product_presentation_id`)}
                                                class="form-select form-select-sm"
                                                bind:value={item.product_presentation_id}
                                            >
                                                <option value="">Sin presentación</option>
                                                {#each product?.presentations ?? [] as pres}
                                                    <option value={pres.id}>{pres.name}</option>
                                                {/each}
                                            </select>
                                        </td>
                                        <td style="min-width:140px">
                                            <input
                                                class:is-invalid={error(`items.${index}.lot_number`)}
                                                class="form-control form-control-sm"
                                                type="text"
                                                bind:value={item.lot_number}
                                                placeholder="INICIAL-2026"
                                            />
                                        </td>
                                        <td style="min-width:140px">
                                            <input
                                                class:is-invalid={error(`items.${index}.expires_on`)}
                                                class="form-control form-control-sm"
                                                type="date"
                                                bind:value={item.expires_on}
                                            />
                                        </td>
                                        <td style="min-width:90px">
                                            <input
                                                class:is-invalid={error(`items.${index}.quantity`)}
                                                class="form-control form-control-sm text-end"
                                                type="number"
                                                min="1"
                                                bind:value={item.quantity}
                                            />
                                        </td>
                                        <td style="min-width:120px">
                                            <input
                                                class:is-invalid={error(`items.${index}.unit_cost`)}
                                                class="form-control form-control-sm text-end"
                                                type="number"
                                                min="0"
                                                bind:value={item.unit_cost}
                                                placeholder="0"
                                            />
                                        </td>
                                        <td>
                                            <button
                                                class="btn btn-sm btn-light border taguara-icon-button-sm"
                                                type="button"
                                                aria-label="Eliminar línea"
                                                onclick={() => removeItem(index)}
                                                disabled={form.items.length === 1}
                                            >
                                                <Trash2 size={15} />
                                            </button>
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </form>
</AppLayout>

<style>
    .taguara-setup-step-num {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: var(--taguara-green, #16a34a);
        color: #fff;
        font-size: .75rem;
        font-weight: 700;
        flex-shrink: 0;
        margin-top: 2px;
    }

    .taguara-import-dropzone {
        display: flex;
        align-items: center;
        gap: .75rem;
        padding: 1rem 1.25rem;
        border: 2px dashed var(--taguara-border, #e2e8f0);
        border-radius: 10px;
        cursor: pointer;
        transition: border-color .15s, background .15s;
        background: var(--taguara-surface, #fafafa);
    }

    .taguara-import-dropzone:hover,
    .taguara-import-dropzone.has-file {
        border-color: #16a34a;
        background: #f0fdf4;
    }
</style>
