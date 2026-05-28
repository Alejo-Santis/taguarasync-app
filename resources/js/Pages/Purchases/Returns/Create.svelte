<script>
    import { Link, useForm } from '@inertiajs/svelte';
    import {
        ArrowLeft,
        Plus,
        Save,
        Trash2,
        Undo2,
    } from '@lucide/svelte';
    import AppLayout from '../../../Layouts/AppLayout.svelte';

    let { auth, options } = $props();

    const today = new Date().toISOString().slice(0, 10);
    const money = new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    });

    const productById = (id) => options.products.find((p) => Number(p.id) === Number(id));
    const lotsForProduct = (id) => productById(id)?.lots ?? [];
    const lotById = (productId, lotId) => lotsForProduct(productId).find((l) => Number(l.id) === Number(lotId));

    const defaultItem = () => {
        const product = options.products[0] ?? null;
        const lot = product?.lots?.[0] ?? null;
        return {
            product_id: product?.id ?? '',
            inventory_lot_id: lot?.id ?? '',
            lot_number: lot?.lot_number ?? '',
            description: product?.name ?? '',
            quantity: 1,
            unit_cost: lot?.unit_cost ?? product?.purchase_price ?? 0,
            tax_rate: product?.tax_rate ?? 0,
        };
    };

    const form = useForm(() => ({
        supplier_id: options.suppliers[0]?.id ?? '',
        purchase_receipt_id: '',
        document_number: '',
        return_date: today,
        reason: '',
        notes: '',
        items: [defaultItem()],
    }));

    const error = (key) => form.errors[key];

    const lineSubtotal = (item) => Number(item.quantity || 0) * Number(item.unit_cost || 0);
    const lineTax = (item) => Math.round(lineSubtotal(item) * (Number(item.tax_rate || 0) / 100));
    const lineTotal = (item) => lineSubtotal(item) + lineTax(item);

    const subtotal = $derived(form.items.reduce((acc, item) => acc + lineSubtotal(item), 0));
    const taxTotal = $derived(form.items.reduce((acc, item) => acc + lineTax(item), 0));
    const total = $derived(subtotal + taxTotal);

    const setProduct = (index) => {
        const product = productById(form.items[index].product_id);
        const lot = product?.lots?.[0] ?? null;
        form.items[index] = {
            ...form.items[index],
            inventory_lot_id: lot?.id ?? '',
            lot_number: lot?.lot_number ?? '',
            description: product?.name ?? '',
            unit_cost: lot?.unit_cost ?? product?.purchase_price ?? 0,
            tax_rate: product?.tax_rate ?? 0,
        };
    };

    const setLot = (index) => {
        const lot = lotById(form.items[index].product_id, form.items[index].inventory_lot_id);
        if (lot) {
            form.items[index] = {
                ...form.items[index],
                lot_number: lot.lot_number,
                unit_cost: lot.unit_cost,
            };
        }
    };

    const addItem = () => {
        form.items = [...form.items, defaultItem()];
    };

    const removeItem = (index) => {
        if (form.items.length === 1) return;
        form.items = form.items.filter((_, i) => i !== index);
    };

    const submit = () => {
        form.post('/purchases/returns');
    };
</script>

<AppLayout title="Nueva devolución" activeSection="purchases" {auth}>
    <form class="taguara-product-form" onsubmit={(e) => { e.preventDefault(); submit(); }}>
        <section class="taguara-command-band">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <Link class="btn btn-sm btn-light border d-inline-flex align-items-center gap-1" href="/purchases/returns">
                        <ArrowLeft size={15} />
                        Devoluciones
                    </Link>
                </div>
                <p class="text-uppercase small fw-semibold text-danger mb-2">Nueva devolución</p>
                <h2 class="h3 mb-2">Registrar devolución a proveedor</h2>
                <p class="text-secondary mb-0">El inventario se reversará automáticamente al confirmar.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <Link class="btn btn-light border d-inline-flex align-items-center gap-2" href="/purchases/returns">
                    Cancelar
                </Link>
                <button class="btn btn-danger d-inline-flex align-items-center gap-2" type="submit" disabled={form.processing}>
                    <Save size={17} />
                    {form.processing ? 'Guardando...' : 'Confirmar devolución'}
                </button>
            </div>
        </section>

        <!-- Header -->
        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-danger mb-1">Encabezado</p>
                    <h3 class="h5 mb-0">Datos del documento</h3>
                </div>
                <Undo2 class="text-secondary" size={22} />
            </div>

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" for="ret-supplier">
                        Proveedor <span class="text-danger">*</span>
                    </label>
                    <select id="ret-supplier" class="form-select {error('supplier_id') ? 'is-invalid' : ''}" bind:value={form.supplier_id}>
                        {#each options.suppliers as supplier}
                            <option value={supplier.id}>{supplier.name} {supplier.nit ? `(${supplier.nit})` : ''}</option>
                        {/each}
                    </select>
                    {#if error('supplier_id')}<div class="invalid-feedback">{error('supplier_id')}</div>{/if}
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" for="ret-purchase">Compra relacionada</label>
                    <select id="ret-purchase" class="form-select {error('purchase_receipt_id') ? 'is-invalid' : ''}" bind:value={form.purchase_receipt_id}>
                        <option value="">— Sin referencia —</option>
                        {#each options.receipts as receipt}
                            <option value={receipt.id}>{receipt.document_number} ({receipt.received_at})</option>
                        {/each}
                    </select>
                    {#if error('purchase_receipt_id')}<div class="invalid-feedback">{error('purchase_receipt_id')}</div>{/if}
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold" for="ret-docnum">
                        Número de documento <span class="text-danger">*</span>
                    </label>
                    <input id="ret-docnum" class="form-control {error('document_number') ? 'is-invalid' : ''}" type="text" bind:value={form.document_number} placeholder="DEV-001" />
                    {#if error('document_number')}<div class="invalid-feedback">{error('document_number')}</div>{/if}
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold" for="ret-date">
                        Fecha de devolución <span class="text-danger">*</span>
                    </label>
                    <input id="ret-date" class="form-control {error('return_date') ? 'is-invalid' : ''}" type="date" bind:value={form.return_date} />
                    {#if error('return_date')}<div class="invalid-feedback">{error('return_date')}</div>{/if}
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold" for="ret-reason">Motivo</label>
                    <input id="ret-reason" class="form-control {error('reason') ? 'is-invalid' : ''}" type="text" bind:value={form.reason} placeholder="Producto vencido, defectuoso..." />
                    {#if error('reason')}<div class="invalid-feedback">{error('reason')}</div>{/if}
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold" for="ret-notes">Observaciones</label>
                    <textarea id="ret-notes" class="form-control {error('notes') ? 'is-invalid' : ''}" rows="2" bind:value={form.notes} placeholder="Información adicional..."></textarea>
                    {#if error('notes')}<div class="invalid-feedback">{error('notes')}</div>{/if}
                </div>
            </div>
        </section>

        <!-- Items -->
        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-danger mb-1">Productos</p>
                    <h3 class="h5 mb-0">Ítems a devolver</h3>
                </div>
                <button class="btn btn-sm btn-light border d-inline-flex align-items-center gap-1" type="button" onclick={addItem}>
                    <Plus size={15} />
                    Agregar ítem
                </button>
            </div>

            {#if error('items')}
                <div class="alert alert-danger small">{error('items')}</div>
            {/if}

            <div class="d-flex flex-column gap-3">
                {#each form.items as item, index (index)}
                    <div class="border rounded p-3 position-relative">
                        {#if form.items.length > 1}
                            <button
                                class="btn btn-sm btn-light border taguara-icon-button position-absolute top-0 end-0 m-2"
                                type="button"
                                aria-label="Eliminar ítem"
                                onclick={() => removeItem(index)}
                            >
                                <Trash2 size={15} class="text-danger" />
                            </button>
                        {/if}

                        <div class="row g-3">
                            <div class="col-12 col-md-5">
                                <label class="form-label fw-semibold small" for="ret-product-{index}">Producto</label>
                                <select
                                    id="ret-product-{index}"
                                    class="form-select form-select-sm {error(`items.${index}.product_id`) ? 'is-invalid' : ''}"
                                    bind:value={item.product_id}
                                    onchange={() => setProduct(index)}
                                >
                                    {#each options.products as product}
                                        <option value={product.id}>{product.name}</option>
                                    {/each}
                                </select>
                                {#if error(`items.${index}.product_id`)}<div class="invalid-feedback">{error(`items.${index}.product_id`)}</div>{/if}
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label fw-semibold small" for="ret-lot-{index}">Lote</label>
                                <select
                                    id="ret-lot-{index}"
                                    class="form-select form-select-sm {error(`items.${index}.inventory_lot_id`) ? 'is-invalid' : ''}"
                                    bind:value={item.inventory_lot_id}
                                    onchange={() => setLot(index)}
                                >
                                    {#each lotsForProduct(item.product_id) as lot}
                                        <option value={lot.id}>{lot.lot_number} (Stock: {lot.current_quantity})</option>
                                    {/each}
                                </select>
                                {#if error(`items.${index}.inventory_lot_id`)}<div class="invalid-feedback">{error(`items.${index}.inventory_lot_id`)}</div>{/if}
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label fw-semibold small" for="ret-qty-{index}">Cantidad</label>
                                <input
                                    id="ret-qty-{index}"
                                    class="form-control form-control-sm text-end {error(`items.${index}.quantity`) ? 'is-invalid' : ''}"
                                    type="number"
                                    min="1"
                                    bind:value={item.quantity}
                                />
                                {#if error(`items.${index}.quantity`)}<div class="invalid-feedback">{error(`items.${index}.quantity`)}</div>{/if}
                            </div>

                            <div class="col-12 col-md-5">
                                <label class="form-label fw-semibold small" for="ret-desc-{index}">Descripción</label>
                                <input
                                    id="ret-desc-{index}"
                                    class="form-control form-control-sm {error(`items.${index}.description`) ? 'is-invalid' : ''}"
                                    type="text"
                                    bind:value={item.description}
                                />
                                {#if error(`items.${index}.description`)}<div class="invalid-feedback">{error(`items.${index}.description`)}</div>{/if}
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label fw-semibold small" for="ret-cost-{index}">Costo unitario</label>
                                <input
                                    id="ret-cost-{index}"
                                    class="form-control form-control-sm text-end {error(`items.${index}.unit_cost`) ? 'is-invalid' : ''}"
                                    type="number"
                                    min="0"
                                    bind:value={item.unit_cost}
                                />
                                {#if error(`items.${index}.unit_cost`)}<div class="invalid-feedback">{error(`items.${index}.unit_cost`)}</div>{/if}
                            </div>

                            <div class="col-6 col-md-2">
                                <label class="form-label fw-semibold small" for="ret-tax-{index}">IVA %</label>
                                <input
                                    id="ret-tax-{index}"
                                    class="form-control form-control-sm text-end {error(`items.${index}.tax_rate`) ? 'is-invalid' : ''}"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    bind:value={item.tax_rate}
                                />
                                {#if error(`items.${index}.tax_rate`)}<div class="invalid-feedback">{error(`items.${index}.tax_rate`)}</div>{/if}
                            </div>

                            <div class="col-6 col-md-2 d-flex align-items-end justify-content-end">
                                <div class="text-end">
                                    <p class="small text-secondary mb-0">Total línea</p>
                                    <p class="fw-semibold mb-0">{money.format(lineTotal(item))}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                {/each}
            </div>
        </section>

        <!-- Totals -->
        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-danger mb-1">Resumen</p>
                    <h3 class="h5 mb-0">Totales de la devolución</h3>
                </div>
            </div>

            <div class="taguara-totals-footer">
                <div class="taguara-totals-row">
                    <span class="text-secondary">Subtotal</span>
                    <span>{money.format(subtotal)}</span>
                </div>
                {#if taxTotal > 0}
                    <div class="taguara-totals-row">
                        <span class="text-secondary">IVA</span>
                        <span>{money.format(taxTotal)}</span>
                    </div>
                {/if}
                <div class="taguara-totals-row taguara-totals-total">
                    <span>Total a devolver</span>
                    <span>{money.format(total)}</span>
                </div>
            </div>
        </section>
    </form>
</AppLayout>
