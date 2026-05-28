<script>
    import { Link, useForm } from '@inertiajs/svelte';
    import {
        ArrowLeft,
        ClipboardList,
        Plus,
        Save,
        Trash2,
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
    const firstPresentationFor = (id) => productById(id)?.presentations?.[0]?.id ?? '';

    const defaultItem = () => {
        const product = options.products[0] ?? null;
        return {
            product_id: product?.id ?? '',
            product_presentation_id: product?.presentations?.[0]?.id ?? '',
            description: product?.name ?? '',
            quantity: 1,
            unit_cost: product?.purchase_price ?? 0,
            tax_rate: product?.tax_rate ?? 0,
        };
    };

    const form = useForm(() => ({
        supplier_id: options.suppliers[0]?.id ?? '',
        order_number: '',
        order_date: today,
        expected_date: '',
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
        form.items[index] = {
            ...form.items[index],
            product_presentation_id: firstPresentationFor(form.items[index].product_id),
            description: product?.name ?? '',
            unit_cost: product?.purchase_price ?? 0,
            tax_rate: product?.tax_rate ?? 0,
        };
    };

    const addItem = () => {
        form.items = [...form.items, defaultItem()];
    };

    const removeItem = (index) => {
        if (form.items.length === 1) { return; }
        form.items = form.items.filter((_, i) => i !== index);
    };

    const submit = () => {
        form.post('/purchases/orders');
    };
</script>

<AppLayout title="Nueva orden de compra" activeSection="purchases" {auth}>
    <form class="taguara-product-form" onsubmit={(e) => { e.preventDefault(); submit(); }}>
        <section class="taguara-command-band">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <Link class="btn btn-sm btn-light border d-inline-flex align-items-center gap-1" href="/purchases/orders">
                        <ArrowLeft size={15} />
                        Órdenes
                    </Link>
                </div>
                <p class="text-uppercase small fw-semibold text-primary mb-2">Nueva orden</p>
                <h2 class="h3 mb-2">Crear orden de compra</h2>
                <p class="text-secondary mb-0">Define los productos y cantidades solicitadas al proveedor.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <Link class="btn btn-light border d-inline-flex align-items-center gap-2" href="/purchases/orders">Cancelar</Link>
                <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="submit" disabled={form.processing}>
                    <Save size={17} />
                    {form.processing ? 'Guardando...' : 'Crear orden'}
                </button>
            </div>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-primary mb-1">Encabezado</p>
                    <h3 class="h5 mb-0">Datos de la orden</h3>
                </div>
                <ClipboardList class="text-secondary" size={22} />
            </div>

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" for="po-supplier">Proveedor <span class="text-danger">*</span></label>
                    <select id="po-supplier" class="form-select {error('supplier_id') ? 'is-invalid' : ''}" bind:value={form.supplier_id}>
                        {#each options.suppliers as supplier}
                            <option value={supplier.id}>{supplier.name} {supplier.nit ? `(${supplier.nit})` : ''}</option>
                        {/each}
                    </select>
                    {#if error('supplier_id')}<div class="invalid-feedback">{error('supplier_id')}</div>{/if}
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" for="po-order-number">Número de orden <span class="text-danger">*</span></label>
                    <input id="po-order-number" class="form-control {error('order_number') ? 'is-invalid' : ''}" type="text" bind:value={form.order_number} placeholder="OC-001" />
                    {#if error('order_number')}<div class="invalid-feedback">{error('order_number')}</div>{/if}
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold" for="po-order-date">Fecha de orden <span class="text-danger">*</span></label>
                    <input id="po-order-date" class="form-control {error('order_date') ? 'is-invalid' : ''}" type="date" bind:value={form.order_date} />
                    {#if error('order_date')}<div class="invalid-feedback">{error('order_date')}</div>{/if}
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold" for="po-expected-date">Fecha esperada de entrega</label>
                    <input id="po-expected-date" class="form-control {error('expected_date') ? 'is-invalid' : ''}" type="date" bind:value={form.expected_date} />
                    {#if error('expected_date')}<div class="invalid-feedback">{error('expected_date')}</div>{/if}
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold" for="po-notes">Observaciones</label>
                    <textarea id="po-notes" class="form-control {error('notes') ? 'is-invalid' : ''}" rows="2" bind:value={form.notes} placeholder="Instrucciones especiales..."></textarea>
                    {#if error('notes')}<div class="invalid-feedback">{error('notes')}</div>{/if}
                </div>
            </div>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-primary mb-1">Productos</p>
                    <h3 class="h5 mb-0">Ítems solicitados</h3>
                </div>
                <button class="btn btn-sm btn-light border d-inline-flex align-items-center gap-1" type="button" onclick={addItem}>
                    <Plus size={15} />
                    Agregar ítem
                </button>
            </div>

            {#if error('items')}<div class="alert alert-danger small">{error('items')}</div>{/if}

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
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold small" for="item-product-{index}">Producto</label>
                                <select
                                    id="item-product-{index}"
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

                            <div class="col-6 col-md-2">
                                <label class="form-label fw-semibold small" for="item-qty-{index}">Cantidad</label>
                                <input
                                    id="item-qty-{index}"
                                    class="form-control form-control-sm text-end {error(`items.${index}.quantity`) ? 'is-invalid' : ''}"
                                    type="number" min="1"
                                    bind:value={item.quantity}
                                />
                                {#if error(`items.${index}.quantity`)}<div class="invalid-feedback">{error(`items.${index}.quantity`)}</div>{/if}
                            </div>

                            <div class="col-6 col-md-2">
                                <label class="form-label fw-semibold small" for="item-cost-{index}">Costo unitario</label>
                                <input
                                    id="item-cost-{index}"
                                    class="form-control form-control-sm text-end {error(`items.${index}.unit_cost`) ? 'is-invalid' : ''}"
                                    type="number" min="0"
                                    bind:value={item.unit_cost}
                                />
                                {#if error(`items.${index}.unit_cost`)}<div class="invalid-feedback">{error(`items.${index}.unit_cost`)}</div>{/if}
                            </div>

                            <div class="col-6 col-md-2">
                                <label class="form-label fw-semibold small" for="item-tax-{index}">IVA %</label>
                                <input
                                    id="item-tax-{index}"
                                    class="form-control form-control-sm text-end {error(`items.${index}.tax_rate`) ? 'is-invalid' : ''}"
                                    type="number" min="0" max="100" step="0.01"
                                    bind:value={item.tax_rate}
                                />
                                {#if error(`items.${index}.tax_rate`)}<div class="invalid-feedback">{error(`items.${index}.tax_rate`)}</div>{/if}
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold small" for="item-desc-{index}">Descripción</label>
                                <input
                                    id="item-desc-{index}"
                                    class="form-control form-control-sm {error(`items.${index}.description`) ? 'is-invalid' : ''}"
                                    type="text"
                                    bind:value={item.description}
                                />
                                {#if error(`items.${index}.description`)}<div class="invalid-feedback">{error(`items.${index}.description`)}</div>{/if}
                            </div>

                            <div class="col-12 d-flex justify-content-end">
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

        <section class="taguara-panel">
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
                    <span>Total orden</span>
                    <span>{money.format(total)}</span>
                </div>
            </div>
        </section>
    </form>
</AppLayout>
