<script>
    import { Link, router, useForm } from '@inertiajs/svelte';
    import { fade, fly } from 'svelte/transition';
    import { ArrowLeft, Pencil, Plus, Search, Tag, Trash2, X } from '@lucide/svelte';
    import AppLayout from '../../../Layouts/AppLayout.svelte';
    import SettingsNav from '../../../Components/Settings/SettingsNav.svelte';

    let { auth, priceList, products } = $props();

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const fmt = (value) => money.format(value ?? 0);

    let drawerOpen = $state(false);
    let editingItem = $state(null);
    let productSearch = $state('');

    const itemForm = useForm({
        product_id: '',
        sale_price: '',
    });

    const filteredProducts = $derived(
        productSearch.length < 2
            ? []
            : products.filter((p) =>
                p.name.toLowerCase().includes(productSearch.toLowerCase()) ||
                (p.code ?? '').toLowerCase().includes(productSearch.toLowerCase())
            ).slice(0, 10)
    );

    const selectedProduct = $derived(
        products.find((p) => p.id === Number(itemForm.product_id)) ?? null
    );

    $effect(() => {
        document.body.style.overflow = drawerOpen ? 'hidden' : '';
        return () => { document.body.style.overflow = ''; };
    });

    const handleKeydown = (event) => { if (event.key === 'Escape') closeDrawer(); };

    const openAdd = () => {
        editingItem = null;
        itemForm.reset();
        productSearch = '';
        drawerOpen = true;
    };

    const openEdit = (item) => {
        editingItem = item;
        itemForm.product_id = String(item.product_id);
        itemForm.sale_price = String(item.sale_price);
        productSearch = item.product_name;
        drawerOpen = true;
    };

    const closeDrawer = () => {
        drawerOpen = false;
        editingItem = null;
        itemForm.reset();
        itemForm.clearErrors();
        productSearch = '';
    };

    const selectProduct = (product) => {
        itemForm.product_id = String(product.id);
        itemForm.sale_price = String(product.base_price);
        productSearch = product.name;
    };

    const saveForm = () => {
        itemForm.post(`/settings/price-lists/${priceList.id}/items`, { onSuccess: closeDrawer, preserveScroll: true });
    };

    const removeItem = (item) => {
        if (!confirm(`¿Eliminar el precio especial de "${item.product_name}"? Se usará el precio base del producto.`)) {
            return;
        }
        router.delete(`/settings/price-lists/${priceList.id}/items/${item.id}`, { preserveScroll: true });
    };

    const discountPercent = (base, override) => {
        if (!base || base === 0) { return null; }
        const diff = ((override - base) / base) * 100;
        return diff.toFixed(1);
    };
</script>

<svelte:window onkeydown={handleKeydown} />

<AppLayout title={priceList.name} activeSection="configuracion" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Listas de precio</p>
                <h2 class="h3 mb-2">{priceList.name}</h2>
                <p class="text-secondary mb-0">{priceList.description ?? 'Precios especiales por producto para esta lista.'}</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <Link href="/settings/price-lists" class="btn btn-light border d-inline-flex align-items-center gap-2">
                    <ArrowLeft size={17} />
                    Volver
                </Link>
                <div class="d-flex gap-1 flex-wrap justify-content-end">
                    {#if priceList.is_default}<span class="badge text-bg-primary">Por defecto</span>{/if}
                    <span class={`badge ${priceList.is_active ? 'text-bg-success' : 'text-bg-secondary'}`}>{priceList.is_active ? 'Activa' : 'Inactiva'}</span>
                </div>
            </div>
        </section>

        <SettingsNav active="price-lists" />

        <section class="taguara-panel">
            <div class="taguara-panel-header align-items-start">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Precios especiales</p>
                    <h3 class="h5 mb-0">{priceList.items.length} producto{priceList.items.length !== 1 ? 's' : ''} con precio especial</h3>
                </div>
                <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="button" onclick={openAdd}>
                    <Plus size={17} />
                    Agregar producto
                </button>
            </div>

            <div class="taguara-table-wrapper mt-3">
                <table class="taguara-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th class="text-end">Precio base</th>
                            <th class="text-end">Precio especial</th>
                            <th class="text-end">Diferencia</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each priceList.items as item}
                            {@const diff = discountPercent(item.base_price, item.sale_price)}
                            <tr onclick={() => openEdit(item)}>
                                <td>
                                    <div class="taguara-table-name">{item.product_name}</div>
                                </td>
                                <td class="text-end text-secondary">{fmt(item.base_price)}</td>
                                <td class="text-end fw-semibold">{fmt(item.sale_price)}</td>
                                <td class="text-end">
                                    {#if diff !== null}
                                        <span class={`badge ${Number(diff) < 0 ? 'text-bg-success' : Number(diff) > 0 ? 'text-bg-warning text-dark' : 'text-bg-light border text-secondary'}`}>
                                            {Number(diff) > 0 ? '+' : ''}{diff}%
                                        </span>
                                    {/if}
                                </td>
                                <td>
                                    <div class="d-flex gap-1 justify-content-end">
                                        <button class="btn btn-sm btn-light border taguara-icon-button-sm" type="button" onclick={(e) => { e.stopPropagation(); openEdit(item); }}><Pencil size={15} /></button>
                                        <button class="btn btn-sm btn-light border taguara-icon-button-sm text-danger" type="button" onclick={(e) => { e.stopPropagation(); removeItem(item); }}><Trash2 size={15} /></button>
                                    </div>
                                </td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="5">
                                    <div class="taguara-empty-state">
                                        <Tag size={34} />
                                        <h4 class="h6 mb-1">Sin precios especiales</h4>
                                        <p class="text-secondary mb-0">Agrega productos para definir precios especiales en esta lista.</p>
                                    </div>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    {#if drawerOpen}
        <div class="taguara-drawer-backdrop" transition:fade={{ duration: 150 }} onclick={closeDrawer} role="presentation"></div>
        <aside class="taguara-drawer" transition:fly={{ x: 480, duration: 220 }}>
            <div class="taguara-drawer-header">
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <div>
                        <p class="text-uppercase small fw-semibold text-success mb-1">Precio especial</p>
                        <h2 class="h5 mb-0">{editingItem ? editingItem.product_name : 'Agregar producto'}</h2>
                    </div>
                    <button class="btn btn-light border taguara-icon-button flex-shrink-0" type="button" onclick={closeDrawer}><X size={17} /></button>
                </div>
            </div>
            <div class="taguara-drawer-body">
                <form id="item-form" onsubmit={(event) => { event.preventDefault(); saveForm(); }}>
                    <div class="vstack gap-3">
                        {#if !editingItem}
                            <div>
                                <label class="form-label" for="product-search">Producto <span class="text-danger">*</span></label>
                                <div class="position-relative">
                                    <span class="position-absolute top-50 start-0 translate-middle-y ms-2 text-secondary"><Search size={16} /></span>
                                    <input
                                        id="product-search"
                                        class="form-control ps-4"
                                        class:is-invalid={itemForm.errors.product_id}
                                        type="text"
                                        bind:value={productSearch}
                                        placeholder="Buscar por nombre o código..."
                                        autocomplete="off"
                                    />
                                </div>
                                {#if itemForm.errors.product_id}<div class="text-danger small mt-1">{itemForm.errors.product_id}</div>{/if}
                                {#if filteredProducts.length > 0}
                                    <ul class="list-group mt-1 shadow-sm" style="max-height:220px;overflow-y:auto">
                                        {#each filteredProducts as product}
                                            <button
                                                class="list-group-item list-group-item-action py-2 text-start"
                                                type="button"
                                                onclick={() => selectProduct(product)}
                                            >
                                                <div class="fw-semibold small">{product.name}</div>
                                                <div class="text-secondary" style="font-size:0.78rem">{product.code ?? ''} · Precio base: {fmt(product.base_price)}</div>
                                            </button>
                                        {/each}
                                    </ul>
                                {/if}
                                {#if selectedProduct}
                                    <div class="alert alert-success py-2 px-3 mt-2 small mb-0">
                                        Seleccionado: <strong>{selectedProduct.name}</strong> · Precio base: {fmt(selectedProduct.base_price)}
                                    </div>
                                {/if}
                            </div>
                        {:else}
                            <div class="alert alert-light border small py-2 px-3 mb-0">
                                <strong>{editingItem.product_name}</strong>
                                <div class="text-secondary">Precio base: {fmt(editingItem.base_price)}</div>
                            </div>
                        {/if}

                        <div>
                            <label class="form-label" for="item-sale-price">Precio especial (COP) <span class="text-danger">*</span></label>
                            <input
                                id="item-sale-price"
                                class="form-control"
                                class:is-invalid={itemForm.errors.sale_price}
                                type="number"
                                min="0"
                                step="1"
                                bind:value={itemForm.sale_price}
                                placeholder="0"
                            />
                            {#if itemForm.errors.sale_price}<div class="invalid-feedback">{itemForm.errors.sale_price}</div>{/if}
                            {#if itemForm.sale_price && selectedProduct}
                                {@const diff = discountPercent(selectedProduct.base_price, Number(itemForm.sale_price))}
                                {#if diff !== null}
                                    <div class="form-text">
                                        {Number(diff) < 0 ? `Descuento del ${Math.abs(Number(diff))}% sobre precio base` :
                                         Number(diff) > 0 ? `Incremento del ${diff}% sobre precio base` : 'Igual al precio base'}
                                    </div>
                                {/if}
                            {/if}
                        </div>
                    </div>
                </form>
            </div>
            <div class="taguara-drawer-footer">
                <button class="btn btn-taguara w-100" type="submit" form="item-form" disabled={itemForm.processing || (!editingItem && !itemForm.product_id)}>
                    {itemForm.processing ? 'Guardando...' : (editingItem ? 'Actualizar precio' : 'Agregar precio especial')}
                </button>
            </div>
        </aside>
    {/if}
</AppLayout>
