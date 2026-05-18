<script>
    import { Link, useForm } from '@inertiajs/svelte';
    import {
        ArrowLeft,
        Boxes,
        Info,
        Plus,
        ReceiptText,
        Save,
        Trash2,
        Truck,
    } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';

    let { auth, options } = $props();

    const today = new Date().toISOString().slice(0, 10);
    const money = new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    });

    const productById = (productId) => options.products.find((product) => Number(product.id) === Number(productId));
    const firstPresentationFor = (productId) => productById(productId)?.presentations?.[0]?.id ?? '';

    const defaultItem = () => {
        const product = options.products[0] ?? null;

        return {
            product_id: product?.id ?? '',
            product_presentation_id: product?.presentations?.[0]?.id ?? '',
            description: product?.name ?? '',
            lot_number: '',
            expires_on: '',
            quantity: 1,
            unit_cost: product?.purchase_price ?? 0,
            tax_rate: product?.tax_rate ?? 0,
        };
    };

    const form = useForm(() => ({
        supplier_id: options.suppliers[0]?.id ?? '',
        document_number: '',
        document_date: today,
        received_at: '',
        notes: '',
        items: [defaultItem()],
    }));

    const error = (key) => form.errors[key];

    const lineSubtotal = (item) => Number(item.quantity || 0) * Number(item.unit_cost || 0);
    const lineTax = (item) => Math.round(lineSubtotal(item) * (Number(item.tax_rate || 0) / 100));
    const lineTotal = (item) => lineSubtotal(item) + lineTax(item);

    const subtotal = $derived(form.items.reduce((total, item) => total + lineSubtotal(item), 0));
    const taxTotal = $derived(form.items.reduce((total, item) => total + lineTax(item), 0));
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
        if (form.items.length === 1) {
            return;
        }

        form.items = form.items.filter((_, currentIndex) => currentIndex !== index);
    };

    const submit = () => {
        form.post('/purchases');
    };
</script>

<AppLayout title="Nueva compra" activeSection="purchases" {auth}>
    <form class="taguara-product-form" onsubmit={(event) => { event.preventDefault(); submit(); }}>
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Recepcion de compra</p>
                <h2 class="h3 mb-2">Registrar remision o factura</h2>
                <p class="text-secondary mb-0">Ingresa el documento recibido y sus lotes para alimentar inventario automaticamente.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <Link class="btn btn-light border d-inline-flex align-items-center gap-2" href="/purchases">
                    <ArrowLeft size={18} />
                    Volver
                </Link>
                <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="submit" disabled={form.processing}>
                    <Save size={18} />
                    Recibir compra
                </button>
            </div>
        </section>

        <section class="row g-3">
            <div class="col-12 col-xl-8">
                <div class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Documento</p>
                            <h3 class="h5 mb-0">Datos de la compra</h3>
                        </div>
                        <ReceiptText class="text-success" size={22} />
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="supplier_id">Proveedor</label>
                            <select id="supplier_id" class:is-invalid={error('supplier_id')} class="form-select" bind:value={form.supplier_id}>
                                <option value="">Seleccionar</option>
                                {#each options.suppliers as supplier}
                                    <option value={supplier.id}>{supplier.name}{supplier.nit ? ` · ${supplier.nit}` : ''}</option>
                                {/each}
                            </select>
                            {#if error('supplier_id')}<div class="invalid-feedback">{error('supplier_id')}</div>{/if}
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="document_number">Numero de documento</label>
                            <input id="document_number" class:is-invalid={error('document_number')} class="form-control" type="text" bind:value={form.document_number} placeholder="REM-1001">
                            {#if error('document_number')}<div class="invalid-feedback">{error('document_number')}</div>{/if}
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="document_date">Fecha del documento</label>
                            <input id="document_date" class:is-invalid={error('document_date')} class="form-control" type="date" bind:value={form.document_date}>
                            {#if error('document_date')}<div class="invalid-feedback">{error('document_date')}</div>{/if}
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="received_at">Fecha de recepcion</label>
                            <input id="received_at" class:is-invalid={error('received_at')} class="form-control" type="datetime-local" bind:value={form.received_at}>
                            {#if error('received_at')}<div class="invalid-feedback">{error('received_at')}</div>{/if}
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="notes">Notas</label>
                            <textarea id="notes" class:is-invalid={error('notes')} class="form-control" rows="2" bind:value={form.notes}></textarea>
                            {#if error('notes')}<div class="invalid-feedback">{error('notes')}</div>{/if}
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Resumen</p>
                            <h3 class="h5 mb-0">Totales calculados</h3>
                        </div>
                        <Truck class="text-success" size={22} />
                    </div>

                    <div class="taguara-drawer-grid">
                        <span class="taguara-drawer-label">Lineas</span>
                        <span>{form.items.length}</span>
                        <span class="taguara-drawer-label">Subtotal</span>
                        <span>{money.format(subtotal)}</span>
                        <span class="taguara-drawer-label">Impuestos</span>
                        <span>{money.format(taxTotal)}</span>
                        <span class="taguara-drawer-label">Total</span>
                        <span class="fw-semibold">{money.format(total)}</span>
                    </div>

                    <p class="text-secondary small mt-3 mb-0 d-flex align-items-start gap-2">
                        <Boxes size={15} class="flex-shrink-0 mt-1" />
                        Cada linea crea o reutiliza el lote del producto y registra un movimiento de inventario de tipo compra.
                    </p>
                </div>
            </div>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Lineas</p>
                    <h3 class="h5 mb-0">Productos recibidos</h3>
                </div>
                <button class="btn btn-light border d-inline-flex align-items-center gap-2" type="button" onclick={addItem}>
                    <Plus size={17} />
                    Agregar linea
                </button>
            </div>

            {#if error('items')}<div class="alert alert-danger small">{error('items')}</div>{/if}

            <div class="taguara-table-wrapper">
                <table class="taguara-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Presentacion</th>
                            <th>Lote</th>
                            <th>Vence</th>
                            <th class="text-end">Cant.</th>
                            <th class="text-end">Costo</th>
                            <th class="text-end">IVA %</th>
                            <th class="text-end">Total linea</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each form.items as item, index}
                            {@const product = productById(item.product_id)}
                            <tr>
                                <td style="min-width: 220px">
                                    <select class:is-invalid={error(`items.${index}.product_id`)} class="form-select form-select-sm" bind:value={item.product_id} onchange={() => setProduct(index)}>
                                        <option value="">Seleccionar</option>
                                        {#each options.products as productOption}
                                            <option value={productOption.id}>{productOption.name}</option>
                                        {/each}
                                    </select>
                                    <input class:is-invalid={error(`items.${index}.description`)} class="form-control form-control-sm mt-2" type="text" bind:value={item.description} placeholder="Descripcion en documento">
                                </td>
                                <td style="min-width: 160px">
                                    <select class:is-invalid={error(`items.${index}.product_presentation_id`)} class="form-select form-select-sm" bind:value={item.product_presentation_id}>
                                        <option value="">Sin presentacion</option>
                                        {#each product?.presentations ?? [] as presentation}
                                            <option value={presentation.id}>{presentation.name}</option>
                                        {/each}
                                    </select>
                                </td>
                                <td style="min-width: 120px">
                                    <input class:is-invalid={error(`items.${index}.lot_number`)} class="form-control form-control-sm" type="text" bind:value={item.lot_number} placeholder="LOT-001">
                                </td>
                                <td style="min-width: 140px">
                                    <input class:is-invalid={error(`items.${index}.expires_on`)} class="form-control form-control-sm" type="date" bind:value={item.expires_on}>
                                </td>
                                <td style="min-width: 90px">
                                    <input class:is-invalid={error(`items.${index}.quantity`)} class="form-control form-control-sm text-end" type="number" min="1" bind:value={item.quantity}>
                                </td>
                                <td style="min-width: 110px">
                                    <input class:is-invalid={error(`items.${index}.unit_cost`)} class="form-control form-control-sm text-end" type="number" min="0" bind:value={item.unit_cost}>
                                </td>
                                <td style="min-width: 80px">
                                    <input class:is-invalid={error(`items.${index}.tax_rate`)} class="form-control form-control-sm text-end" type="number" min="0" max="100" step="0.01" bind:value={item.tax_rate}>
                                </td>
                                <td class="text-end fw-semibold" style="min-width: 110px">{money.format(lineTotal(item))}</td>
                                <td>
                                    <button class="btn btn-sm btn-light border taguara-icon-button-sm" type="button" aria-label="Eliminar linea" onclick={() => removeItem(index)} disabled={form.items.length === 1}>
                                        <Trash2 size={15} />
                                    </button>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </section>
    </form>
</AppLayout>
