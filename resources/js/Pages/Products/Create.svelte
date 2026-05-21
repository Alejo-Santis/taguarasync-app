<script>
    import { Link, useForm } from '@inertiajs/svelte';
    import {
        ArrowLeft,
        Check,
        PackagePlus,
        Plus,
        Save,
        Trash2,
    } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';

    let { auth, options, product = null } = $props();

    const isEditing = $derived(Boolean(product));

    const defaultPresentation = () => ({
        unit_id: options.units[0]?.id ?? '',
        name: 'Unidad',
        barcode: '',
        minimum_unit_quantity: 1,
        sale_price: '',
        is_default: true,
        is_active: true,
    });

    const form = useForm(() => product ? {
        laboratory_id: product.laboratory_id ?? '',
        product_category_id: product.product_category_id ?? '',
        active_ingredient_id: product.active_ingredient_id ?? '',
        minimum_unit_id: product.minimum_unit_id ?? '',
        internal_code: product.internal_code ?? '',
        barcode: product.barcode ?? '',
        commercial_name: product.commercial_name ?? '',
        generic_name: product.generic_name ?? '',
        cum: product.cum ?? '',
        health_registration: product.health_registration ?? '',
        pharmaceutical_form: product.pharmaceutical_form ?? '',
        concentration: product.concentration ?? '',
        purchase_price: product.purchase_price ?? 0,
        sale_price: product.sale_price ?? '',
        minimum_stock: product.minimum_stock ?? 0,
        regulated_price: product.regulated_price ?? '',
        tax_rate: product.tax_rate ?? 0,
        requires_invima_registration: product.requires_invima_registration ?? true,
        is_controlled: product.is_controlled ?? false,
        control_level: product.control_level ?? '',
        status: product.status ?? 'active',
        notes: product.notes ?? '',
        presentations: product.presentations?.length ? product.presentations : [defaultPresentation()],
    } : {
        laboratory_id: options.laboratories[0]?.id ?? '',
        product_category_id: options.categories[0]?.id ?? '',
        active_ingredient_id: '',
        minimum_unit_id: options.units[0]?.id ?? '',
        internal_code: '',
        barcode: '',
        commercial_name: '',
        generic_name: '',
        cum: '',
        health_registration: '',
        pharmaceutical_form: '',
        concentration: '',
        purchase_price: 0,
        sale_price: '',
        minimum_stock: 0,
        regulated_price: '',
        tax_rate: 0,
        requires_invima_registration: true,
        is_controlled: false,
        control_level: '',
        status: 'active',
        notes: '',
        presentations: [defaultPresentation()],
    });

    const error = (key) => form.errors[key];

    const addPresentation = () => {
        form.presentations = [
            ...form.presentations,
            {
                unit_id: options.units[0]?.id ?? '',
                name: '',
                barcode: '',
                minimum_unit_quantity: 1,
                sale_price: '',
                is_default: false,
                is_active: true,
            },
        ];
    };

    const removePresentation = (index) => {
        if (form.presentations.length === 1) {
            return;
        }

        const wasDefault = form.presentations[index].is_default;

        form.presentations = form.presentations.filter((_, currentIndex) => currentIndex !== index);

        if (wasDefault) {
            form.presentations[0].is_default = true;
        }
    };

    const setDefaultPresentation = (index) => {
        form.presentations = form.presentations.map((presentation, currentIndex) => ({
            ...presentation,
            is_default: currentIndex === index,
        }));
    };

    const submit = () => {
        if (isEditing) {
            form.put(`/products/${product.uuid}`);

            return;
        }

        form.post('/products');
    };
</script>

<AppLayout title={isEditing ? 'Editar producto' : 'Nuevo producto'} activeSection="products" {auth}>
    <form class="taguara-product-form" onsubmit={(event) => { event.preventDefault(); submit(); }}>
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Catalogo farmaceutico</p>
                <h2 class="h3 mb-2">{isEditing ? 'Editar producto' : 'Registrar producto'}</h2>
                <p class="text-secondary mb-0">{isEditing ? 'Actualiza datos comerciales, regulatorios y presentaciones del producto.' : 'Crea el producto base y sus presentaciones comerciales para ventas por unidad, blister, caja o frasco.'}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <Link class="btn btn-light border d-inline-flex align-items-center gap-2" href="/products">
                    <ArrowLeft size={18} />
                    Volver
                </Link>
                <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="submit" disabled={form.processing}>
                    <Save size={18} />
                    {isEditing ? 'Actualizar' : 'Guardar'}
                </button>
            </div>
        </section>

        <section class="row g-3">
            <div class="col-12 col-xl-8">
                <div class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Datos base</p>
                            <h3 class="h5 mb-0">Identificacion del producto</h3>
                        </div>
                        <PackagePlus class="text-success" size={22} />
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="commercial_name">Nombre comercial</label>
                            <input id="commercial_name" class:is-invalid={error('commercial_name')} class="form-control" type="text" bind:value={form.commercial_name}>
                            {#if error('commercial_name')}<div class="invalid-feedback">{error('commercial_name')}</div>{/if}
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="generic_name">Nombre generico</label>
                            <input id="generic_name" class:is-invalid={error('generic_name')} class="form-control" type="text" bind:value={form.generic_name}>
                            {#if error('generic_name')}<div class="invalid-feedback">{error('generic_name')}</div>{/if}
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label" for="internal_code">Codigo interno</label>
                            <input id="internal_code" class:is-invalid={error('internal_code')} class="form-control" type="text" bind:value={form.internal_code}>
                            {#if error('internal_code')}<div class="invalid-feedback">{error('internal_code')}</div>{/if}
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label" for="barcode">Codigo de barras</label>
                            <input id="barcode" class:is-invalid={error('barcode')} class="form-control" type="text" bind:value={form.barcode}>
                            {#if error('barcode')}<div class="invalid-feedback">{error('barcode')}</div>{/if}
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label" for="status">Estado</label>
                            <select id="status" class:is-invalid={error('status')} class="form-select" bind:value={form.status}>
                                {#each options.statuses as status}
                                    <option value={status.value}>{status.label}</option>
                                {/each}
                            </select>
                            {#if error('status')}<div class="invalid-feedback">{error('status')}</div>{/if}
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label" for="laboratory_id">Laboratorio</label>
                            <select id="laboratory_id" class:is-invalid={error('laboratory_id')} class="form-select" bind:value={form.laboratory_id}>
                                <option value="">Seleccionar</option>
                                {#each options.laboratories as laboratory}
                                    <option value={laboratory.id}>{laboratory.name}</option>
                                {/each}
                            </select>
                            {#if error('laboratory_id')}<div class="invalid-feedback">{error('laboratory_id')}</div>{/if}
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label" for="product_category_id">Categoria</label>
                            <select id="product_category_id" class:is-invalid={error('product_category_id')} class="form-select" bind:value={form.product_category_id}>
                                <option value="">Seleccionar</option>
                                {#each options.categories as category}
                                    <option value={category.id}>{category.name}</option>
                                {/each}
                            </select>
                            {#if error('product_category_id')}<div class="invalid-feedback">{error('product_category_id')}</div>{/if}
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label" for="active_ingredient_id">Principio activo</label>
                            <select id="active_ingredient_id" class:is-invalid={error('active_ingredient_id')} class="form-select" bind:value={form.active_ingredient_id}>
                                <option value="">Sin principio activo</option>
                                {#each options.activeIngredients as ingredient}
                                    <option value={ingredient.id}>{ingredient.name}</option>
                                {/each}
                            </select>
                            {#if error('active_ingredient_id')}<div class="invalid-feedback">{error('active_ingredient_id')}</div>{/if}
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label" for="minimum_unit_id">Unidad minima</label>
                            <select id="minimum_unit_id" class:is-invalid={error('minimum_unit_id')} class="form-select" bind:value={form.minimum_unit_id}>
                                <option value="">Seleccionar</option>
                                {#each options.units as unit}
                                    <option value={unit.id}>{unit.name}</option>
                                {/each}
                            </select>
                            {#if error('minimum_unit_id')}<div class="invalid-feedback">{error('minimum_unit_id')}</div>{/if}
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label" for="pharmaceutical_form">Forma farmaceutica</label>
                            <input id="pharmaceutical_form" class:is-invalid={error('pharmaceutical_form')} class="form-control" type="text" bind:value={form.pharmaceutical_form} placeholder="Tableta, jarabe, suspension">
                            {#if error('pharmaceutical_form')}<div class="invalid-feedback">{error('pharmaceutical_form')}</div>{/if}
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label" for="concentration">Concentracion</label>
                            <input id="concentration" class:is-invalid={error('concentration')} class="form-control" type="text" bind:value={form.concentration} placeholder="500mg, 250mg/5ml">
                            {#if error('concentration')}<div class="invalid-feedback">{error('concentration')}</div>{/if}
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="cum">CUM</label>
                            <input id="cum" class:is-invalid={error('cum')} class="form-control" type="text" bind:value={form.cum}>
                            {#if error('cum')}<div class="invalid-feedback">{error('cum')}</div>{/if}
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="health_registration">Registro sanitario</label>
                            <input id="health_registration" class:is-invalid={error('health_registration')} class="form-control" type="text" bind:value={form.health_registration}>
                            {#if error('health_registration')}<div class="invalid-feedback">{error('health_registration')}</div>{/if}
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="notes">Notas</label>
                            <textarea id="notes" class:is-invalid={error('notes')} class="form-control" rows="3" bind:value={form.notes}></textarea>
                            {#if error('notes')}<div class="invalid-feedback">{error('notes')}</div>{/if}
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Precios y control</p>
                            <h3 class="h5 mb-0">Condiciones</h3>
                        </div>
                    </div>

                    <div class="vstack gap-3">
                        <div>
                            <label class="form-label" for="purchase_price">Precio compra</label>
                            <input id="purchase_price" class:is-invalid={error('purchase_price')} class="form-control" type="number" min="0" bind:value={form.purchase_price}>
                            {#if error('purchase_price')}<div class="invalid-feedback">{error('purchase_price')}</div>{/if}
                        </div>

                        <div>
                            <label class="form-label" for="sale_price">Precio venta base</label>
                            <input id="sale_price" class:is-invalid={error('sale_price')} class="form-control" type="number" min="0" bind:value={form.sale_price}>
                            {#if error('sale_price')}<div class="invalid-feedback">{error('sale_price')}</div>{/if}
                        </div>

                        <div>
                            <label class="form-label" for="minimum_stock">Stock minimo (unidades)</label>
                            <input id="minimum_stock" class:is-invalid={error('minimum_stock')} class="form-control" type="number" min="0" bind:value={form.minimum_stock}>
                            <div class="form-text">El sistema alertara cuando el stock baje de este numero.</div>
                            {#if error('minimum_stock')}<div class="invalid-feedback">{error('minimum_stock')}</div>{/if}
                        </div>

                        <div>
                            <label class="form-label" for="regulated_price">Precio regulado</label>
                            <input id="regulated_price" class:is-invalid={error('regulated_price')} class="form-control" type="number" min="0" bind:value={form.regulated_price}>
                            {#if error('regulated_price')}<div class="invalid-feedback">{error('regulated_price')}</div>{/if}
                        </div>

                        <div>
                            <label class="form-label" for="tax_rate">Impuesto %</label>
                            <input id="tax_rate" class:is-invalid={error('tax_rate')} class="form-control" type="number" min="0" max="100" step="0.01" bind:value={form.tax_rate}>
                            {#if error('tax_rate')}<div class="invalid-feedback">{error('tax_rate')}</div>{/if}
                        </div>

                        <div class="form-check form-switch">
                            <input id="requires_invima_registration" class="form-check-input" type="checkbox" bind:checked={form.requires_invima_registration}>
                            <label class="form-check-label" for="requires_invima_registration">Requiere registro INVIMA</label>
                        </div>

                        <div class="form-check form-switch">
                            <input id="is_controlled" class="form-check-input" type="checkbox" bind:checked={form.is_controlled}>
                            <label class="form-check-label" for="is_controlled">Medicamento controlado</label>
                        </div>

                        {#if form.is_controlled}
                            <div>
                                <label class="form-label" for="control_level">Nivel de control</label>
                                <input id="control_level" class:is-invalid={error('control_level')} class="form-control" type="number" min="1" max="5" bind:value={form.control_level}>
                                {#if error('control_level')}<div class="invalid-feedback">{error('control_level')}</div>{/if}
                            </div>
                        {/if}
                    </div>
                </div>
            </div>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Presentaciones</p>
                    <h3 class="h5 mb-0">Venta por unidad, blister, caja o frasco</h3>
                </div>
                <button class="btn btn-light border d-inline-flex align-items-center gap-2" type="button" onclick={addPresentation}>
                    <Plus size={17} />
                    Agregar
                </button>
            </div>

            {#if error('presentations')}
                <div class="alert alert-danger">{error('presentations')}</div>
            {/if}

            <div class="taguara-presentation-editor">
                {#each form.presentations as presentation, index}
                    <article class="taguara-presentation-editor-row">
                        <div class="taguara-presentation-editor-header">
                            <button class={`btn btn-sm ${presentation.is_default ? 'btn-taguara' : 'btn-light border'} d-inline-flex align-items-center gap-2`} type="button" onclick={() => setDefaultPresentation(index)}>
                                <Check size={15} />
                                Principal
                            </button>

                            <button class="btn btn-sm btn-light border taguara-icon-button" type="button" aria-label="Eliminar presentacion" onclick={() => removePresentation(index)} disabled={form.presentations.length === 1}>
                                <Trash2 size={16} />
                            </button>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-3">
                                <label class="form-label" for={`presentation_name_${index}`}>Nombre</label>
                                <input id={`presentation_name_${index}`} class:is-invalid={error(`presentations.${index}.name`)} class="form-control" type="text" bind:value={presentation.name} placeholder="Blister x 10">
                                {#if error(`presentations.${index}.name`)}<div class="invalid-feedback">{error(`presentations.${index}.name`)}</div>{/if}
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label" for={`presentation_unit_${index}`}>Unidad</label>
                                <select id={`presentation_unit_${index}`} class:is-invalid={error(`presentations.${index}.unit_id`)} class="form-select" bind:value={presentation.unit_id}>
                                    {#each options.units as unit}
                                        <option value={unit.id}>{unit.name}</option>
                                    {/each}
                                </select>
                                {#if error(`presentations.${index}.unit_id`)}<div class="invalid-feedback">{error(`presentations.${index}.unit_id`)}</div>{/if}
                            </div>

                            <div class="col-12 col-md-2">
                                <label class="form-label" for={`presentation_quantity_${index}`}>Cantidad minima</label>
                                <input id={`presentation_quantity_${index}`} class:is-invalid={error(`presentations.${index}.minimum_unit_quantity`)} class="form-control" type="number" min="1" bind:value={presentation.minimum_unit_quantity}>
                                {#if error(`presentations.${index}.minimum_unit_quantity`)}<div class="invalid-feedback">{error(`presentations.${index}.minimum_unit_quantity`)}</div>{/if}
                            </div>

                            <div class="col-12 col-md-2">
                                <label class="form-label" for={`presentation_price_${index}`}>Precio</label>
                                <input id={`presentation_price_${index}`} class:is-invalid={error(`presentations.${index}.sale_price`)} class="form-control" type="number" min="0" bind:value={presentation.sale_price}>
                                {#if error(`presentations.${index}.sale_price`)}<div class="invalid-feedback">{error(`presentations.${index}.sale_price`)}</div>{/if}
                            </div>

                            <div class="col-12 col-md-2">
                                <label class="form-label" for={`presentation_barcode_${index}`}>Barra</label>
                                <input id={`presentation_barcode_${index}`} class:is-invalid={error(`presentations.${index}.barcode`)} class="form-control" type="text" bind:value={presentation.barcode}>
                                {#if error(`presentations.${index}.barcode`)}<div class="invalid-feedback">{error(`presentations.${index}.barcode`)}</div>{/if}
                            </div>
                        </div>

                        <div class="form-check form-switch mt-3">
                            <input id={`presentation_active_${index}`} class="form-check-input" type="checkbox" bind:checked={presentation.is_active}>
                            <label class="form-check-label" for={`presentation_active_${index}`}>Presentacion activa</label>
                        </div>
                    </article>
                {/each}
            </div>
        </section>
    </form>
</AppLayout>
