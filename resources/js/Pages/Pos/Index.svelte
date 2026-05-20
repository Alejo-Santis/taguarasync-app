<script>
    import { router, usePage, useForm } from '@inertiajs/svelte';
    import { fade, scale } from 'svelte/transition';
    import {
        AlertCircle,
        CheckCircle2,
        ChevronRight,
        Clock,
        CreditCard,
        DollarSign,
        Lock,
        Minus,
        Package,
        Plus,
        PrinterCheck,
        Search,
        ShieldAlert,
        ShoppingCart,
        Trash2,
        X,
    } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';

    let { auth, activeSession } = $props();

    const page = usePage();
    let completedSale = $derived(page.props.completedSale ?? null);

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const fmt = (v) => money.format(v ?? 0);

    // Search state
    let searchQuery = $state('');
    let searchResults = $state([]);
    let isSearching = $state(false);
    let searchTimeout = null;

    // Cart state
    let cart = $state([]);
    let cartError = $state('');

    // Payment modal
    let paymentOpen = $state(false);
    let paymentMethod = $state('cash');
    let amountTendered = $state('');
    let isProcessing = $state(false);

    // Receipt
    let receipt = $state(null);

    $effect(() => {
        if (completedSale) {
            receipt = completedSale;
        }
    });

    // Derived cart totals
    const lineSubtotal = (item) => item.quantity * item.unit_price;
    const lineTax = (item) => Math.round(lineSubtotal(item) * (item.tax_rate / 100));
    const lineTotal = (item) => lineSubtotal(item) + lineTax(item);

    const cartSubtotal = $derived(cart.reduce((s, i) => s + lineSubtotal(i), 0));
    const cartTax = $derived(cart.reduce((s, i) => s + lineTax(i), 0));
    const cartTotal = $derived(cartSubtotal + cartTax);
    const cartCount = $derived(cart.reduce((s, i) => s + i.quantity, 0));
    const hasCart = $derived(cart.length > 0);

    const change = $derived(
        paymentMethod === 'cash' && amountTendered !== ''
            ? Math.max(0, Number(amountTendered) - cartTotal)
            : null
    );
    const canConfirm = $derived(
        hasCart && (
            paymentMethod !== 'cash' ||
            (amountTendered !== '' && Number(amountTendered) >= cartTotal)
        )
    );

    // Search
    const handleSearch = (e) => {
        clearTimeout(searchTimeout);
        const q = e.target.value;
        searchQuery = q;
        if (q.trim().length < 2) { searchResults = []; return; }
        searchTimeout = setTimeout(() => doSearch(q), 280);
    };

    const doSearch = async (q) => {
        isSearching = true;
        try {
            const res = await fetch(`/pos/products?q=${encodeURIComponent(q)}`);
            searchResults = await res.json();
        } catch { searchResults = []; }
        finally { isSearching = false; }
    };

    // Cart management
    const addToCart = (product, presentation) => {
        cartError = '';
        const existing = cart.find(i => i.product_presentation_id === presentation.id);
        if (existing) {
            if (existing.quantity < presentation.available) {
                existing.quantity += 1;
                cart = [...cart];
            }
            return;
        }
        cart = [...cart, {
            product_id: product.id,
            product_presentation_id: presentation.id,
            description: product.commercial_name,
            presentation_name: presentation.name,
            concentration: product.concentration,
            quantity: 1,
            unit_price: presentation.unit_price,
            tax_rate: Number(product.tax_rate),
            available: presentation.available,
            is_controlled: product.is_controlled,
        }];
    };

    const updateQty = (index, delta) => {
        const item = cart[index];
        const newQty = item.quantity + delta;
        if (newQty < 1) { removeItem(index); return; }
        if (newQty > item.available) return;
        cart[index] = { ...item, quantity: newQty };
    };

    const removeItem = (index) => { cart = cart.filter((_, i) => i !== index); };

    const clearCart = () => {
        cart = [];
        cartError = '';
        searchQuery = '';
        searchResults = [];
    };

    // Payment
    const openPayment = () => {
        paymentMethod = 'cash';
        amountTendered = '';
        paymentOpen = true;
    };

    const confirmSale = () => {
        if (!canConfirm || isProcessing) return;
        isProcessing = true;
        cartError = '';

        const payload = {
            payment_method: paymentMethod,
            amount_tendered: paymentMethod === 'cash' ? Number(amountTendered) : undefined,
            items: cart.map(i => ({
                product_id: i.product_id,
                product_presentation_id: i.product_presentation_id,
                description: i.description,
                quantity: i.quantity,
                unit_price: i.unit_price,
                tax_rate: i.tax_rate,
            })),
        };

        router.post('/pos/sales', payload, {
            onSuccess: () => {
                paymentOpen = false;
                isProcessing = false;
                clearCart();
            },
            onError: (errors) => {
                isProcessing = false;
                paymentOpen = false;
                cartError = errors.cart || Object.values(errors)[0] || 'Error al procesar la venta.';
            },
        });
    };

    const startNewSale = () => {
        receipt = null;
        clearCart();
    };

    const closeSession = () => {
        router.get(`/pos/session/${activeSession.uuid}/close`);
    };
</script>

<AppLayout title="POS" activeSection="pos" {auth}>
    <!-- Session header bar -->
    <div class="taguara-pos-session-bar">
        <div class="d-flex align-items-center gap-3">
            <span class="taguara-pos-session-tag">
                <span class="fw-semibold">{activeSession.register_name}</span>
                <span class="text-secondary">·</span>
                <Clock size={13} />
                <span>Desde {activeSession.opened_at}</span>
            </span>
            <span class="text-secondary small">
                {activeSession.sales_count} ventas · {fmt(activeSession.sales_total)}
            </span>
        </div>
        <button class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-2" type="button" onclick={closeSession}>
            <Lock size={13} />
            Cerrar caja
        </button>
    </div>

    <div class="taguara-pos">
        <!-- Left: product search -->
        <div class="taguara-pos-search">
            <div class="taguara-pos-searchbar">
                <Search size={18} class="text-secondary flex-shrink-0" />
                <input
                    class="form-control border-0 shadow-none"
                    type="search"
                    placeholder="Buscar por nombre, codigo o barras..."
                    value={searchQuery}
                    oninput={handleSearch}
                    autofocus
                />
                {#if isSearching}
                    <span class="spinner-border spinner-border-sm text-secondary flex-shrink-0" role="status"></span>
                {/if}
            </div>

            {#if searchResults.length > 0}
                <div class="taguara-pos-results">
                    {#each searchResults as product}
                        <article class="taguara-pos-product-card">
                            <div class="taguara-pos-product-header">
                                <div class="min-w-0">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-semibold">{product.commercial_name}</span>
                                        {#if product.is_controlled}
                                            <ShieldAlert size={13} class="text-warning flex-shrink-0" />
                                        {/if}
                                    </div>
                                    <div class="taguara-table-sub">
                                        {[product.pharmaceutical_form, product.concentration].filter(Boolean).join(' · ')}
                                    </div>
                                </div>
                                <span class="badge text-bg-light border text-secondary small">
                                    {product.available_units} {product.minimum_unit_code}
                                </span>
                            </div>
                            <div class="taguara-pos-presentations">
                                {#each product.presentations as pres}
                                    <button
                                        type="button"
                                        class={`taguara-pos-pres-btn${pres.is_default ? ' default' : ''}`}
                                        onclick={() => addToCart(product, pres)}
                                        disabled={pres.available === 0}
                                        title={pres.available === 0 ? 'Sin stock' : `${pres.available} disponibles`}
                                    >
                                        <span class="fw-semibold">{pres.name}</span>
                                        <span class="taguara-table-sub">{fmt(pres.unit_price)}</span>
                                        {#if pres.available === 0}
                                            <span class="badge text-bg-secondary" style="font-size:.65rem">Sin stock</span>
                                        {:else}
                                            <Plus size={13} class="text-success" />
                                        {/if}
                                    </button>
                                {/each}
                            </div>
                        </article>
                    {/each}
                </div>
            {:else if searchQuery.length >= 2 && !isSearching}
                <div class="taguara-empty-state" style="min-height:160px">
                    <Package size={28} />
                    <p class="text-secondary small mb-0">No se encontraron productos con stock disponible.</p>
                </div>
            {:else if searchQuery.length === 0}
                <div class="taguara-pos-hint">
                    <Search size={40} class="text-secondary mb-3" />
                    <p class="fw-semibold mb-1">Busca un producto para comenzar</p>
                    <p class="text-secondary small mb-0">Escribe al menos 2 caracteres — nombre, codigo o codigo de barras.</p>
                </div>
            {/if}
        </div>

        <!-- Right: cart -->
        <aside class="taguara-pos-cart">
            <div class="taguara-pos-cart-header">
                <div class="d-flex align-items-center gap-2">
                    <ShoppingCart size={18} />
                    <span class="fw-semibold">Carrito</span>
                    {#if hasCart}
                        <span class="badge text-bg-success">{cartCount}</span>
                    {/if}
                </div>
                {#if hasCart}
                    <button class="btn btn-sm btn-light border taguara-icon-button-sm" type="button" onclick={clearCart} aria-label="Vaciar carrito">
                        <Trash2 size={14} />
                    </button>
                {/if}
            </div>

            <div class="taguara-pos-cart-items">
                {#if !hasCart}
                    <div class="taguara-pos-cart-empty">
                        <ShoppingCart size={28} class="text-secondary mb-2" />
                        <p class="text-secondary small mb-0">Agrega productos desde la busqueda</p>
                    </div>
                {:else}
                    {#each cart as item, index}
                        <div class="taguara-pos-cart-item">
                            <div class="taguara-pos-cart-item-info">
                                <div class="fw-semibold small">{item.description}</div>
                                <div class="taguara-table-sub">{item.presentation_name} · {fmt(item.unit_price)}</div>
                            </div>
                            <div class="taguara-pos-cart-item-controls">
                                <button class="taguara-pos-qty-btn" type="button" onclick={() => updateQty(index, -1)}>
                                    <Minus size={12} />
                                </button>
                                <span class="taguara-pos-qty-value">{item.quantity}</span>
                                <button class="taguara-pos-qty-btn" type="button" onclick={() => updateQty(index, 1)} disabled={item.quantity >= item.available}>
                                    <Plus size={12} />
                                </button>
                            </div>
                            <div class="taguara-pos-cart-item-total">
                                <span class="fw-semibold small">{fmt(lineTotal(item))}</span>
                                <button class="btn btn-link p-0 text-danger" type="button" onclick={() => removeItem(index)}>
                                    <X size={13} />
                                </button>
                            </div>
                        </div>
                    {/each}
                {/if}
            </div>

            {#if cartError}
                <div class="alert alert-danger small m-2 mb-0 d-flex gap-2">
                    <AlertCircle size={15} class="flex-shrink-0 mt-1" />
                    {cartError}
                </div>
            {/if}

            <div class="taguara-pos-cart-footer">
                {#if hasCart}
                    <div class="taguara-drawer-grid mb-3">
                        <span class="taguara-drawer-label">Subtotal</span>
                        <span class="text-end">{fmt(cartSubtotal)}</span>
                        <span class="taguara-drawer-label">IVA</span>
                        <span class="text-end">{fmt(cartTax)}</span>
                        <span class="taguara-drawer-label fw-bold">Total</span>
                        <span class="text-end fw-bold">{fmt(cartTotal)}</span>
                    </div>
                {/if}
                <button
                    class="btn btn-taguara w-100 d-inline-flex align-items-center justify-content-center gap-2"
                    style="min-height:52px; font-size:1.05rem"
                    type="button"
                    onclick={openPayment}
                    disabled={!hasCart}
                >
                    <ChevronRight size={20} />
                    {hasCart ? `Cobrar ${fmt(cartTotal)}` : 'Agrega productos'}
                </button>
            </div>
        </aside>
    </div>

    <!-- Payment modal — CENTERED -->
    {#if paymentOpen}
        <div class="taguara-drawer-backdrop" transition:fade={{ duration: 150 }} onclick={() => paymentOpen = false} role="presentation"></div>
        <div class="taguara-pos-modal" transition:scale={{ duration: 180, start: 0.96 }}>
            <div class="taguara-pos-modal-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Cobro</p>
                    <h2 class="h4 mb-0">Total: {fmt(cartTotal)}</h2>
                </div>
                <button class="btn btn-light border taguara-icon-button" type="button" onclick={() => paymentOpen = false}><X size={17} /></button>
            </div>

            <div class="taguara-pos-modal-body">
                <p class="small fw-semibold text-secondary mb-2">Metodo de pago</p>
                <div class="taguara-pos-payment-methods">
                    <button
                        type="button"
                        class={`taguara-pos-method-btn${paymentMethod === 'cash' ? ' active' : ''}`}
                        onclick={() => { paymentMethod = 'cash'; amountTendered = ''; }}
                    >
                        <DollarSign size={20} />
                        Efectivo
                    </button>
                    <button
                        type="button"
                        class={`taguara-pos-method-btn${paymentMethod === 'card' ? ' active' : ''}`}
                        onclick={() => { paymentMethod = 'card'; amountTendered = ''; }}
                    >
                        <CreditCard size={20} />
                        Tarjeta
                    </button>
                    <button
                        type="button"
                        class={`taguara-pos-method-btn${paymentMethod === 'transfer' ? ' active' : ''}`}
                        onclick={() => { paymentMethod = 'transfer'; amountTendered = ''; }}
                    >
                        <ChevronRight size={20} />
                        Transferencia
                    </button>
                </div>

                {#if paymentMethod === 'cash'}
                    <div class="mt-4">
                        <label class="form-label fw-semibold" for="tendered">Monto recibido</label>
                        <input
                            id="tendered"
                            class="form-control form-control-lg text-end"
                            type="number"
                            min={cartTotal}
                            step="1000"
                            bind:value={amountTendered}
                            placeholder={String(cartTotal)}
                        />
                    </div>
                    {#if change !== null && amountTendered !== '' && Number(amountTendered) >= cartTotal}
                        <div class="taguara-pos-change-display mt-3">
                            <span class="text-secondary small">Cambio</span>
                            <span class="h3 mb-0 text-success fw-bold">{fmt(change)}</span>
                        </div>
                    {/if}
                {/if}

                <button
                    class="btn btn-taguara btn-lg w-100 mt-4 d-inline-flex align-items-center justify-content-center gap-2"
                    type="button"
                    onclick={confirmSale}
                    disabled={!canConfirm || isProcessing}
                >
                    {#if isProcessing}
                        <span class="spinner-border spinner-border-sm"></span>
                        Procesando...
                    {:else}
                        <CheckCircle2 size={18} />
                        Confirmar venta
                    {/if}
                </button>
            </div>
        </div>
    {/if}

    <!-- Receipt -->
    {#if receipt}
        <div class="taguara-drawer-backdrop" transition:fade={{ duration: 150 }} role="presentation"></div>
        <div class="taguara-pos-modal" transition:scale={{ duration: 180, start: 0.96 }}>
            <div class="taguara-pos-modal-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="taguara-kpi-icon text-bg-success"><CheckCircle2 size={20} /></span>
                    <div>
                        <p class="text-uppercase small fw-semibold text-success mb-1">Venta completada</p>
                        <h2 class="h5 mb-0">{receipt.document_number}</h2>
                    </div>
                </div>
            </div>

            <div class="taguara-pos-modal-body">
                <div class="taguara-drawer-grid mb-4">
                    <span class="taguara-drawer-label">Total cobrado</span>
                    <span class="fw-bold">{fmt(receipt.total)}</span>
                    <span class="taguara-drawer-label">Metodo de pago</span>
                    <span>{receipt.payment_method}</span>
                    {#if receipt.change_amount !== null && receipt.change_amount > 0}
                        <span class="taguara-drawer-label">Cambio entregado</span>
                        <span class="text-success fw-semibold">{fmt(receipt.change_amount)}</span>
                    {/if}
                    <span class="taguara-drawer-label">Items vendidos</span>
                    <span>{receipt.items_count}</span>
                </div>

                <div class="d-flex gap-2">
                    <a
                        class="btn btn-light border flex-fill d-inline-flex align-items-center justify-content-center gap-2"
                        href={receipt?.uuid ? `/sales/${receipt.uuid}/receipt` : '#'}
                        target="_blank"
                        rel="noopener"
                    >
                        <PrinterCheck size={17} />
                        Imprimir
                    </a>
                    <button class="btn btn-taguara flex-fill d-inline-flex align-items-center justify-content-center gap-2" type="button" onclick={startNewSale}>
                        <Plus size={17} />
                        Nueva venta
                    </button>
                </div>
            </div>
        </div>
    {/if}
</AppLayout>
