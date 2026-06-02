<script>
    import { tick, untrack } from 'svelte';
    import { router, usePage, useForm } from '@inertiajs/svelte';
    import { fade, scale } from 'svelte/transition';
    import {
        AlertCircle,
        CheckCircle2,
        ChevronRight,
        Clock,
        CreditCard,
        DollarSign,
        Eye,
        History,
        Lock,
        Minus,
        Package,
        PackageCheck,
        Plus,
        Printer,
        PrinterCheck,
        Search,
        ShieldAlert,
        ShoppingCart,
        Trash2,
        Upload,
        User,
        UserPlus,
        X,
    } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';
    import PrinterStatus from '../../Components/UI/PrinterStatus.svelte';
    import QzPrinter from '../../Services/QzPrinter.js';

    let { auth, activeSession, customerFormOptions, paymentOptions = { methods: [], bank_accounts: [] } } = $props();

    // ── Configuración de impresora (viene del tenant compartido en auth) ──
    const printerCfg = $derived(auth?.tenant?.printer_settings ?? {});
    const printerName = $derived(printerCfg.printer_name ?? null);
    const autoPrint   = $derived(printerCfg.auto_print ?? false);
    const paperWidth  = $derived(printerCfg.paper_width ?? '80mm');
    const copies      = $derived(printerCfg.copies ?? 1);

    let qzPrinting = $state(false);
    let qzError    = $state('');

    // ── Últimos documentos ────────────────────────────────────────────────
    let recentSales    = $state([]);
    let recentLoading  = $state(false);
    let recentPrinting = $state(null); // uuid actualmente imprimiendo
    let recentOpen     = $state(false);

    async function loadRecentSales() {
        recentLoading = true;
        try {
            const res = await fetch('/pos/recent-sales');
            recentSales = await res.json();
        } catch { recentSales = []; }
        finally { recentLoading = false; }
    }

    async function printRecentThermal(sale) {
        if (!printerName) return;
        recentPrinting = sale.uuid;
        try {
            if (!QzPrinter.connected) await QzPrinter.connect();
            await QzPrinter.printReceipt(printerName, {
                document_number: sale.document_number,
                total: sale.total,
                payment_method: sale.payment_method,
                status: sale.status,
                items: [],
            }, { paperWidth, copies });
        } catch { /* silencioso */ }
        finally { recentPrinting = null; }
    }

    const page = usePage();
    let completedSale = $derived(page.props.completedSale ?? null);

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const fmt = (v) => money.format(v ?? 0);

    // Search state
    let searchQuery = $state('');
    let searchResults = $state([]);
    let isSearching = $state(false);
    let searchTimeout = null;
    let productSearchInput = $state(null);

    // Cart state
    let cart = $state([]);
    let cartError = $state('');

    // Payment modal
    let paymentOpen = $state(false);
    let paymentForm = $state('1'); // '1' = contado, '2' = crédito
    let paymentMethod = $state('cash');
    let paymentMethodId = $state(untrack(() => paymentOptions.methods?.[0]?.id ?? null));
    let amountTendered = $state('');
    let paymentDueDate = $state('');
    let paymentReference = $state('');
    let paymentBankAccountId = $state('');
    let paymentAttachment = $state(null);
    let isProcessing = $state(false);
    let amountTenderedInput = $state(null);

    const isCredit = $derived(paymentForm === '2');

    // Receipt
    let receipt = $state(null);

    // Expiration alert
    let expirationAlert = $state(null); // { product, presentation, expires_on, daysLeft }

    const daysUntilExpiry = (dateStr) => {
        if (!dateStr) { return null; }
        const diff = new Date(dateStr) - new Date();
        return Math.ceil(diff / (1000 * 60 * 60 * 24));
    };

    const dismissExpirationAlert = () => { expirationAlert = null; };

    const addToCartAfterExpirationCheck = (product, presentation) => {
        expirationAlert = null;
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
            regulated_price: product.regulated_price ?? null,
            discount_rate: 0,
            tax_rate: Number(product.tax_rate),
            available: presentation.available,
            is_controlled: false,
            prescription_number: null,
            patient_id_number: null,
            patient_name: null,
        }];
    };

    // Controlled medication modal
    let prescriptionModal = $state(null); // { product, presentation }
    let prescriptionForm = $state({ prescription_number: '', patient_id_number: '', patient_name: '' });

    const openPrescriptionModal = (product, presentation) => {
        prescriptionForm = { prescription_number: '', patient_id_number: '', patient_name: '' };
        prescriptionModal = { product, presentation };
    };
    const confirmPrescription = () => {
        if (!prescriptionModal) { return; }
        const { product, presentation } = prescriptionModal;
        const existing = cart.find(i => i.product_presentation_id === presentation.id);
        if (existing) {
            if (existing.quantity < presentation.available) {
                existing.quantity += 1;
                cart = [...cart];
            }
        } else {
            cart = [...cart, {
                product_id: product.id,
                product_presentation_id: presentation.id,
                description: product.commercial_name,
                presentation_name: presentation.name,
                concentration: product.concentration,
                quantity: 1,
                unit_price: presentation.unit_price,
                regulated_price: prescriptionModal.product.regulated_price ?? null,
                discount_rate: 0,
                tax_rate: Number(product.tax_rate),
                available: presentation.available,
                is_controlled: true,
                prescription_number: prescriptionForm.prescription_number || null,
                patient_id_number: prescriptionForm.patient_id_number || null,
                patient_name: prescriptionForm.patient_name || null,
            }];
        }
        prescriptionModal = null;
    };

    $effect(() => {
        // Cargar últimos documentos al montar
        loadRecentSales();
    });

    $effect(() => {
        if (completedSale) {
            receipt = completedSale;
            // Actualizar historial de documentos
            loadRecentSales();
            // Auto-print si está configurado y hay impresora seleccionada
            if (autoPrint && printerName) {
                printDirectly(completedSale);
            }
        }
    });

    async function printDirectly(saleData) {
        if (!printerName) {
            qzError = 'No hay impresora configurada. Ve a Configuración → Impresora.';
            return;
        }
        qzPrinting = true;
        qzError    = '';
        try {
            if (!QzPrinter.connected) await QzPrinter.connect();
            await QzPrinter.printReceipt(printerName, saleData, { paperWidth, copies });
        } catch (err) {
            qzError = err?.message ?? 'Error al imprimir. ¿QZ Tray está corriendo?';
        } finally {
            qzPrinting = false;
        }
    }

    // Derived cart totals
    const lineGross = (item) => item.quantity * item.unit_price;
    const lineDiscount = (item) => Math.round(lineGross(item) * ((item.discount_rate || 0) / 100));
    const lineSubtotal = (item) => lineGross(item) - lineDiscount(item);
    const lineTax = (item) => Math.round(lineSubtotal(item) * (item.tax_rate / 100));
    const lineTotal = (item) => lineSubtotal(item) + lineTax(item);

    const cartDiscount = $derived(cart.reduce((s, i) => s + lineDiscount(i), 0));
    const cartSubtotal = $derived(cart.reduce((s, i) => s + lineSubtotal(i), 0));
    const cartTax = $derived(cart.reduce((s, i) => s + lineTax(i), 0));
    const cartTotal = $derived(cartSubtotal + cartTax);
    const cartCount = $derived(cart.reduce((s, i) => s + i.quantity, 0));
    const hasCart = $derived(cart.length > 0);
    const paymentMethods = $derived(paymentOptions.methods ?? []);
    const bankAccounts = $derived(paymentOptions.bank_accounts ?? []);
    const selectedPaymentMethod = $derived(
        paymentMethods.find((method) => method.id === paymentMethodId) ?? paymentMethods[0] ?? null
    );
    const isCashPayment = $derived(
        selectedPaymentMethod?.affects_cash === true ||
        selectedPaymentMethod?.type === 'cash' ||
        paymentMethod === 'cash'
    );
    const cashSuggestions = $derived([
        cartTotal,
        Math.ceil(cartTotal / 1000) * 1000,
        Math.ceil(cartTotal / 5000) * 5000,
        Math.ceil(cartTotal / 10000) * 10000,
    ].filter((value, index, values) => value > 0 && values.indexOf(value) === index));

    const change = $derived(
        isCashPayment && amountTendered !== ''
            ? Math.max(0, Number(amountTendered) - cartTotal)
            : null
    );
    const creditLimitExceeded = $derived(
        isCredit &&
        !!selectedCustomer?.credit_limit &&
        (selectedCustomer.credit_balance + cartTotal) > selectedCustomer.credit_limit
    );

    const canConfirm = $derived(
        hasCart && (
            isCredit
                ? (!!selectedCustomer && !selectedCustomer.is_consumidor_final && !!paymentDueDate && !creditLimitExceeded)
                : (
                    (!isCashPayment || (amountTendered !== '' && Number(amountTendered) >= cartTotal)) &&
                    (!selectedPaymentMethod?.requires_reference || paymentReference.trim().length > 0) &&
                    (!selectedPaymentMethod?.requires_bank_account || !!paymentBankAccountId)
                )
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
            const priceListId = selectedCustomer?.price_list_id ?? '';
            const params = new URLSearchParams({ q });
            if (priceListId) { params.set('price_list_id', priceListId); }
            const res = await fetch(`/pos/products?${params}`);
            searchResults = await res.json();
        } catch { searchResults = []; }
        finally { isSearching = false; }
    };

    // Cart management
    const addToCart = (product, presentation) => {
        cartError = '';
        if (product.is_controlled) {
            openPrescriptionModal(product, presentation);
            return;
        }
        const days = daysUntilExpiry(product.next_lot_expires_on);
        if (days !== null && days <= 30) {
            expirationAlert = { product, presentation, expires_on: product.next_lot_expires_on, daysLeft: days };
            return;
        }
        addToCartAfterExpirationCheck(product, presentation);
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

    // Customer selector
    let selectedCustomer = $state(null);
    let customerQuery = $state('');
    let customerResults = $state([]);
    let customerTimeout = null;
    let customerDropOpen = $state(false);
    let customerSearchInput = $state(null);

    const searchCustomers = async (q) => {
        if (q.trim().length < 2) { customerResults = []; return; }
        try {
            const res = await fetch(`/pos/customers?q=${encodeURIComponent(q)}`);
            customerResults = await res.json();
            customerDropOpen = customerResults.length > 0;
        } catch { customerResults = []; }
    };

    const onCustomerInput = (e) => {
        clearTimeout(customerTimeout);
        customerQuery = e.target.value;
        customerTimeout = setTimeout(() => searchCustomers(customerQuery), 280);
    };

    const selectCustomer = (c) => {
        selectedCustomer = c;
        customerQuery = '';
        customerResults = [];
        customerDropOpen = false;
        // Refresh product prices for the new customer's price list
        if (searchQuery.trim().length >= 2) { doSearch(searchQuery); }
    };

    const clearCustomer = () => {
        selectedCustomer = null;
        customerQuery = '';
        // Refresh product prices back to public list
        if (searchQuery.trim().length >= 2) { doSearch(searchQuery); }
    };

    // Create customer modal
    let createCustomerOpen = $state(false);
    let createCustomerLoading = $state(false);
    let createCustomerErrors = $state({});
    let createForm = $state({
        identification_type_code: '13',
        identification_number: '',
        first_name: '',
        last_name: '',
        business_name: '',
        regime_type_code: '49',
        phone: '',
        email: '',
    });

    const isNitForm = $derived(createForm.identification_type_code === '31');

    const getCsrfToken = () => {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : '';
    };

    const openCreateCustomer = () => {
        createCustomerErrors = {};
        createForm = {
            identification_type_code: '13',
            identification_number: /^\d+$/.test(customerQuery.trim()) ? customerQuery.trim() : '',
            first_name: '',
            last_name: '',
            business_name: '',
            regime_type_code: '49',
            phone: '',
            email: '',
        };
        createCustomerOpen = true;
    };

    const submitCreateCustomer = async () => {
        if (createCustomerLoading) return;
        createCustomerLoading = true;
        createCustomerErrors = {};

        try {
            const res = await fetch('/pos/customers', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify(createForm),
            });

            if (res.status === 422) {
                const data = await res.json();
                createCustomerErrors = data.errors ?? {};
                return;
            }

            if (!res.ok) throw new Error();

            const customer = await res.json();
            selectCustomer(customer);
            createCustomerOpen = false;
            customerQuery = '';
        } catch {
            createCustomerErrors = { _general: ['Error al crear el cliente. Intenta de nuevo.'] };
        } finally {
            createCustomerLoading = false;
        }
    };

    const focusProductSearch = async () => {
        await tick();
        productSearchInput?.focus();
        productSearchInput?.select();
    };

    const focusCustomerSearch = async () => {
        await tick();
        customerSearchInput?.focus();
        customerSearchInput?.select();
    };

    const focusAmountTendered = async () => {
        await tick();
        amountTenderedInput?.focus();
        amountTenderedInput?.select();
    };

    // Payment
    const legacyPaymentMethod = (method) => {
        if (!method) return 'cash';
        if (method.type === 'card') return 'card';
        if (['transfer', 'wallet'].includes(method.type)) return 'transfer';

        return 'cash';
    };

    const findPaymentMethod = (shortcut) => {
        if (typeof shortcut === 'number') {
            return paymentMethods.find((method) => method.id === shortcut) ?? null;
        }

        if (shortcut === 'card') {
            return paymentMethods.find((method) => method.type === 'card') ?? null;
        }

        if (shortcut === 'transfer') {
            return paymentMethods.find((method) => ['transfer', 'wallet'].includes(method.type)) ?? null;
        }

        return paymentMethods.find((method) => method.code === shortcut || method.type === shortcut) ?? null;
    };

    const setPaymentMethod = async (shortcut) => {
        const method = findPaymentMethod(shortcut);

        if (!method) return;

        paymentMethodId = method.id;
        paymentMethod = legacyPaymentMethod(method);
        amountTendered = '';
        paymentReference = '';
        paymentAttachment = null;
        paymentBankAccountId = method.requires_bank_account && bankAccounts.length === 1 ? String(bankAccounts[0].id) : '';
        if (method.affects_cash || method.type === 'cash') {
            await focusAmountTendered();
        }
    };

    const openPayment = async (method = 'cash') => {
        if (!hasCart) return;
        paymentForm = '1';
        paymentDueDate = '';
        await setPaymentMethod(method);
        paymentOpen = true;
        if (isCashPayment) {
            await focusAmountTendered();
        }
    };

    const confirmSale = () => {
        if (!canConfirm || isProcessing) return;
        isProcessing = true;
        cartError = '';

        const payload = {
            customer_id: selectedCustomer?.id ?? null,
            payment_method: isCredit ? undefined : paymentMethod,
            payment_form: paymentForm,
            payment_due_date: isCredit ? paymentDueDate : undefined,
            amount_tendered: (!isCredit && isCashPayment) ? Number(amountTendered) : undefined,
            payments: (!isCredit && selectedPaymentMethod) ? [{
                payment_method_id: selectedPaymentMethod.id,
                bank_account_id: paymentBankAccountId ? Number(paymentBankAccountId) : null,
                amount: cartTotal,
                amount_tendered: isCashPayment ? Number(amountTendered) : null,
                reference: paymentReference || null,
                attachment: selectedPaymentMethod.allows_attachment ? paymentAttachment : null,
            }] : undefined,
            items: cart.map(i => ({
                product_id: i.product_id,
                product_presentation_id: i.product_presentation_id,
                description: i.description,
                quantity: i.quantity,
                unit_price: i.unit_price,
                discount_rate: i.discount_rate || 0,
                tax_rate: i.tax_rate,
                prescription_number: i.prescription_number || null,
                patient_id_number: i.patient_id_number || null,
                patient_name: i.patient_name || null,
            })),
        };

        router.post('/pos/sales', payload, {
            forceFormData: paymentAttachment !== null,
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

    const closeOverlays = () => {
        if (receipt) {
            receipt = null;
            return;
        }

        if (recentOpen) {
            recentOpen = false;
            return;
        }

        if (createCustomerOpen) {
            createCustomerOpen = false;
            return;
        }

        if (paymentOpen) {
            paymentOpen = false;
            return;
        }

        customerDropOpen = false;
    };

    const handleKeyboardShortcuts = (event) => {
        if (event.ctrlKey || event.metaKey || event.altKey) return;

        const shortcuts = {
            F2: () => focusProductSearch(),
            F3: () => focusCustomerSearch(),
            F4: () => openPayment('cash'),
            F6: () => paymentOpen ? setPaymentMethod('cash') : openPayment('cash'),
            F7: () => paymentOpen ? setPaymentMethod('card') : openPayment('card'),
            F8: () => paymentOpen ? setPaymentMethod('transfer') : openPayment('transfer'),
            F9: () => {
                if (paymentOpen && canConfirm) confirmSale();
            },
            Escape: () => closeOverlays(),
        };

        if (!shortcuts[event.key]) return;

        event.preventDefault();
        shortcuts[event.key]();
    };
</script>

<svelte:window onkeydown={handleKeyboardShortcuts} />

<AppLayout title="POS" activeSection="pos" {auth} autoCollapse={true}>
    <!-- Session header bar -->
    <div class="taguara-pos-session-bar">
        <div class="taguara-pos-session-main">
            <span class="taguara-pos-session-icon"><PackageCheck size={18} /></span>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <strong>{activeSession.register_name}</strong>
                    <span class="taguara-pos-session-code">{activeSession.register_code}</span>
                    <span class="taguara-pos-session-time"><Clock size={13} /> Desde {activeSession.opened_at}</span>
                </div>
                <div class="taguara-pos-session-sub">Turno activo para venta rapida y facturacion POS</div>
            </div>
        </div>

        <div class="taguara-pos-session-metrics">
            <div>
                <span>{activeSession.sales_count}</span>
                <small>ventas</small>
            </div>
            <div>
                <span>{fmt(activeSession.sales_total)}</span>
                <small>vendido</small>
            </div>
            <div>
                <span>{cartCount}</span>
                <small>items carrito</small>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <PrinterStatus compact={true} showLabel={false} />
            <button class="taguara-pos-close-btn" type="button" onclick={closeSession}>
                <Lock size={13} />
                Cerrar caja
            </button>
        </div>
    </div>

    <div class="taguara-pos-shortcuts" aria-label="Atajos del punto de venta">
        <span><kbd>F2</kbd>Producto</span>
        <span><kbd>F3</kbd>Cliente</span>
        <span><kbd>F4</kbd>Cobrar</span>
        <span><kbd>F6</kbd>Efectivo</span>
        <span><kbd>F7</kbd>Tarjeta</span>
        <span><kbd>F8</kbd>Transferencia</span>
        <span><kbd>F9</kbd>Confirmar</span>
        <span><kbd>Esc</kbd>Cerrar</span>
    </div>

    <div class="taguara-pos">
        <!-- Left: product search -->
        <div class="taguara-pos-search">
            <div class="taguara-pos-searchbar">
                <div class="taguara-pos-search-input">
                    <Search size={21} class="text-success flex-shrink-0" />
                    <input
                        class="form-control border-0 shadow-none"
                        type="search"
                        placeholder="Escanea codigo de barras o busca medicamento..."
                        bind:this={productSearchInput}
                        value={searchQuery}
                        oninput={handleSearch}
                    />
                    {#if isSearching}
                        <span class="spinner-border spinner-border-sm text-secondary flex-shrink-0" role="status"></span>
                    {/if}
                </div>
                <div class="taguara-pos-search-tools">
                    <span class="taguara-pos-chip">Codigo</span>
                    <span class="taguara-pos-chip">Nombre</span>
                    <span class="taguara-pos-chip">Lote FEFO</span>
                    <button
                        type="button"
                        class="taguara-pos-chip taguara-pos-chip--action"
                        onclick={() => { recentOpen = true; loadRecentSales(); }}
                        title="Ver últimos documentos del turno"
                    >
                        <History size={13} />
                        Historial
                    </button>
                </div>
            </div>

            {#if searchResults.length > 0}
                <div class="taguara-pos-results">
                    {#each searchResults as product}
                        <article class="taguara-pos-product-card">
                            <div class="taguara-pos-product-header">
                                <div class="min-w-0">
                                    <div class="taguara-pos-product-title">
                                        <span>{product.commercial_name}</span>
                                        {#if product.is_controlled}
                                            <ShieldAlert size={13} class="text-warning flex-shrink-0" />
                                        {/if}
                                    </div>
                                    <div class="taguara-table-sub">
                                        {[product.pharmaceutical_form, product.concentration].filter(Boolean).join(' · ')}
                                    </div>
                                </div>
                                <span class="taguara-pos-stock-pill">
                                    <Package size={13} />
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
                    <div class="taguara-pos-hint-icon"><Search size={42} /></div>
                    <p class="fw-semibold mb-1">Listo para vender</p>
                    <p class="text-secondary small mb-3">Escanea un codigo o escribe nombre, principio activo, lote o codigo interno.</p>
                    <div class="taguara-pos-hint-grid">
                        <span>FEFO automatico</span>
                        <span>Stock visible</span>
                        <span>Cliente rapido</span>
                    </div>
                </div>
            {/if}
        </div>

        <!-- Right: cart -->
        <aside class="taguara-pos-cart">
            <div class="taguara-pos-cart-header">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <ShoppingCart size={18} />
                        <span class="fw-semibold">Venta actual</span>
                    </div>
                    <div class="taguara-table-sub">{hasCart ? `${cart.length} productos · ${cartCount} unidades` : 'Sin productos agregados'}</div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    {#if hasCart}
                        <span class="badge text-bg-success">{cartCount}</span>
                    {/if}
                    {#if hasCart}
                        <button class="btn btn-sm btn-light border taguara-icon-button-sm" type="button" onclick={clearCart} aria-label="Vaciar carrito">
                            <Trash2 size={14} />
                        </button>
                    {/if}
                </div>
            </div>

            <!-- Customer selector -->
            <div class="taguara-pos-customer" style="padding:.5rem .75rem; border-bottom:1px solid var(--taguara-border)">
                {#if selectedCustomer}
                    <div class="d-flex align-items-center gap-2">
                        <User size={14} class="text-success flex-shrink-0" />
                        <div class="min-w-0 flex-grow-1" style="font-size:.8rem">
                            <div class="fw-semibold text-truncate">{selectedCustomer.full_name}</div>
                            <div class="d-flex align-items-center gap-1 flex-wrap" style="font-size:.73rem; color:var(--bs-secondary-color)">
                                <span>{selectedCustomer.identification}</span>
                                <span class="badge {selectedCustomer.regime_code === '48' ? 'text-bg-warning' : 'text-bg-secondary'}" style="font-size:.6rem">
                                    {selectedCustomer.regime}
                                </span>
                            </div>
                        </div>
                        <button class="btn btn-link p-0 text-secondary" type="button" onclick={clearCustomer} aria-label="Quitar cliente">
                            <X size={13} />
                        </button>
                    </div>
                {:else}
                    <div class="d-flex align-items-center gap-2">
                        <div class="position-relative flex-grow-1">
                            <div class="taguara-pos-customer-search">
                                <User size={13} class="text-secondary" />
                                <input
                                    class="form-control form-control-sm border-0 p-0 bg-transparent"
                                    type="search"
                                    placeholder="Buscar cliente..."
                                    bind:this={customerSearchInput}
                                    value={customerQuery}
                                    oninput={onCustomerInput}
                                />
                            </div>
                            {#if customerDropOpen && customerResults.length > 0}
                                <div class="position-absolute start-0 end-0 bg-white border rounded shadow-sm" style="top:100%; z-index:200; max-height:180px; overflow-y:auto">
                                    {#each customerResults as c}
                                        <button
                                            class="w-100 text-start px-3 py-2 border-0 bg-transparent"
                                            type="button"
                                            style="font-size:.8rem"
                                            onclick={() => selectCustomer(c)}
                                        >
                                            <div class="fw-semibold">{c.full_name}</div>
                                            <div class="d-flex align-items-center gap-1" style="color:var(--bs-secondary-color)">
                                                <span>{c.identification}</span>
                                                <span class="badge {c.regime_code === '48' ? 'text-bg-warning' : 'text-bg-secondary'}" style="font-size:.6rem">{c.regime}</span>
                                            </div>
                                        </button>
                                    {/each}
                                    <button
                                        class="w-100 text-start px-3 py-2 border-0 bg-transparent border-top d-flex align-items-center gap-2 text-primary"
                                        type="button"
                                        style="font-size:.8rem"
                                        onclick={openCreateCustomer}
                                    >
                                        <UserPlus size={13} />
                                        Crear nuevo cliente
                                    </button>
                                </div>
                            {:else if customerQuery.length >= 2}
                                <div class="position-absolute start-0 end-0 bg-white border rounded shadow-sm" style="top:100%; z-index:200;">
                                    <button
                                        class="w-100 text-start px-3 py-2 border-0 bg-transparent d-flex align-items-center gap-2 text-primary"
                                        type="button"
                                        style="font-size:.8rem"
                                        onclick={openCreateCustomer}
                                    >
                                        <UserPlus size={13} />
                                        Crear cliente "{customerQuery}"
                                    </button>
                                </div>
                            {/if}
                        </div>
                        <button
                            class="btn btn-light border flex-shrink-0"
                            style="padding:.25rem .45rem"
                            type="button"
                            onclick={openCreateCustomer}
                            title="Crear nuevo cliente"
                            aria-label="Crear nuevo cliente"
                        >
                            <UserPlus size={14} />
                        </button>
                    </div>
                    {#if !customerQuery}
                        <div class="d-flex align-items-center gap-1 mt-1" style="font-size:.72rem; color:var(--bs-secondary-color)">
                            <User size={11} />
                            <span>Consumidor final — sin cliente asignado</span>
                        </div>
                    {/if}
                {/if}
            </div>

            <div class="taguara-pos-cart-items">
                {#if !hasCart}
                    <div class="taguara-pos-cart-empty">
                        <ShoppingCart size={30} class="text-secondary mb-2" />
                        <p class="fw-semibold mb-1">Carrito vacío</p>
                        <p class="text-secondary small mb-0">Agrega productos desde la busqueda o escanea el codigo de barras.</p>
                    </div>
                {:else}
                    {#each cart as item, index}
                        <div class="taguara-pos-cart-item">
                            <div class="taguara-pos-cart-item-info">
                                <div class="fw-semibold small d-flex align-items-center gap-1">
                                    {item.description}
                                    {#if item.is_controlled}
                                        <ShieldAlert size={12} class="text-warning flex-shrink-0" title="Medicamento controlado" />
                                    {/if}
                                    {#if item.regulated_price && item.unit_price > item.regulated_price}
                                        <span class="badge text-bg-danger" style="font-size:.6rem" title="Precio supera el precio regulado {fmt(item.regulated_price)}">P. regulado</span>
                                    {/if}
                                </div>
                                <div class="taguara-table-sub d-flex align-items-center gap-1">
                                    {item.presentation_name} · {fmt(item.unit_price)}
                                    {#if item.regulated_price}
                                        <span class="taguara-table-sub" style="color:var(--bs-secondary-color)">· Reg: {fmt(item.regulated_price)}</span>
                                    {/if}
                                </div>
                                {#if item.is_controlled && item.patient_id_number}
                                    <div class="taguara-table-sub" style="color:var(--bs-warning-text-emphasis)">
                                        Paciente: {item.patient_name || item.patient_id_number}
                                        {#if item.prescription_number} · Receta: {item.prescription_number}{/if}
                                    </div>
                                {/if}
                                <div class="d-flex align-items-center gap-1 mt-1">
                                    <label class="taguara-table-sub mb-0" for="pos-discount" style="white-space:nowrap">Dto %:</label>
                                    <input
                                        id="pos-discount"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="1"
                                        class="form-control form-control-sm"
                                        style="width:62px; font-size:.75rem; padding:1px 4px;"
                                        value={item.discount_rate || 0}
                                        oninput={(e) => {
                                            const val = Math.min(100, Math.max(0, Number(e.target.value) || 0));
                                            cart[index] = { ...item, discount_rate: val };
                                        }}
                                    />
                                    {#if lineDiscount(item) > 0}
                                        <span class="taguara-table-sub text-success">-{fmt(lineDiscount(item))}</span>
                                    {/if}
                                </div>
                            </div>
                            <div class="taguara-pos-cart-item-controls">
                                <button class="taguara-pos-qty-btn" type="button" aria-label="Disminuir cantidad" onclick={() => updateQty(index, -1)}>
                                    <Minus size={12} />
                                </button>
                                <span class="taguara-pos-qty-value">{item.quantity}</span>
                                <button class="taguara-pos-qty-btn" type="button" aria-label="Aumentar cantidad" onclick={() => updateQty(index, 1)} disabled={item.quantity >= item.available}>
                                    <Plus size={12} />
                                </button>
                            </div>
                            <div class="taguara-pos-cart-item-total">
                                <span class="fw-semibold small">{fmt(lineTotal(item))}</span>
                                <button class="btn btn-link p-0 text-danger" type="button" aria-label="Quitar producto" onclick={() => removeItem(index)}>
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
                        {#if cartDiscount > 0}
                            <span class="taguara-drawer-label text-success">Descuento</span>
                            <span class="text-end text-success">-{fmt(cartDiscount)}</span>
                        {/if}
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
                <!-- Forma de pago: Contado / Crédito -->
                <div class="d-flex gap-2 mb-3">
                    <button
                        type="button"
                        class="btn flex-fill {paymentForm === '1' ? 'btn-taguara' : 'btn-light border'}"
                        onclick={() => { paymentForm = '1'; }}
                    >Contado</button>
                    <button
                        type="button"
                        class="btn flex-fill {paymentForm === '2' ? 'btn-taguara' : 'btn-light border'}"
                        onclick={() => { paymentForm = '2'; }}
                        disabled={!selectedCustomer || selectedCustomer.is_consumidor_final}
                        title={!selectedCustomer ? 'Selecciona un cliente primero' : selectedCustomer.is_consumidor_final ? 'No disponible para Consumidor Final' : ''}
                    >A crédito</button>
                </div>

                {#if isCredit}
                    <!-- Credit sale UI -->
                    {#if !selectedCustomer || selectedCustomer.is_consumidor_final}
                        <div class="alert alert-warning small py-2 mb-3">
                            Selecciona un cliente identificado (no Consumidor Final) para registrar venta a crédito.
                        </div>
                    {:else}
                        <div class="mb-3 p-3 rounded-3 border" style="background:var(--taguara-soft-blue)">
                            <p class="small fw-semibold mb-1">{selectedCustomer.full_name}</p>
                            <div class="d-flex gap-3 flex-wrap" style="font-size:.8rem">
                                <span class="text-secondary">Saldo actual: <strong class="{selectedCustomer.credit_balance > 0 ? 'text-danger' : 'text-success'}">{fmt(selectedCustomer.credit_balance)}</strong></span>
                                {#if selectedCustomer.credit_limit}
                                    <span class="text-secondary">Límite: <strong>{fmt(selectedCustomer.credit_limit)}</strong></span>
                                    {#if creditLimitExceeded}
                                        <span class="badge text-bg-danger">Límite excedido</span>
                                    {:else}
                                        <span class="text-secondary">Disponible: <strong class="text-success">{fmt(selectedCustomer.credit_limit - selectedCustomer.credit_balance)}</strong></span>
                                    {/if}
                                {/if}
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="credit-due-date">Fecha límite de pago <span class="text-danger">*</span></label>
                            <input
                                id="credit-due-date"
                                class="form-control {!paymentDueDate ? 'is-invalid' : ''}"
                                type="date"
                                bind:value={paymentDueDate}
                                min={new Date().toISOString().slice(0, 10)}
                            />
                            {#if !paymentDueDate}<div class="invalid-feedback">Requerida para ventas a crédito.</div>{/if}
                        </div>
                        {#if creditLimitExceeded}
                            <div class="alert alert-danger small py-2">
                                Esta venta supera el límite de crédito del cliente ({fmt(selectedCustomer.credit_limit)}).
                                Saldo actual: {fmt(selectedCustomer.credit_balance)}. No se puede confirmar.
                            </div>
                        {/if}
                    {/if}
                {:else}
                <p class="small fw-semibold text-secondary mb-2">Metodo de pago</p>
                <div class="taguara-pos-payment-methods">
                    {#each paymentMethods as method}
                        <button
                            type="button"
                            class={`taguara-pos-method-btn${selectedPaymentMethod?.id === method.id ? ' active' : ''}`}
                            onclick={() => setPaymentMethod(method.id)}
                        >
                            {#if method.type === 'cash'}
                                <DollarSign size={20} />
                            {:else if method.type === 'card'}
                                <CreditCard size={20} />
                            {:else}
                                <ChevronRight size={20} />
                            {/if}
                            <span>{method.name}</span>
                            {#if method.type === 'cash'}<kbd>F6</kbd>{/if}
                            {#if method.type === 'card'}<kbd>F7</kbd>{/if}
                            {#if ['transfer', 'wallet'].includes(method.type)}<kbd>F8</kbd>{/if}
                        </button>
                    {/each}
                </div>

                {#if selectedPaymentMethod?.requires_reference || selectedPaymentMethod?.requires_bank_account}
                    <div class="taguara-pos-payment-extra">
                        {#if selectedPaymentMethod?.requires_bank_account}
                            <div>
                                <label class="form-label fw-semibold" for="payment-bank-account">Cuenta destino</label>
                                <select id="payment-bank-account" class="form-select" bind:value={paymentBankAccountId}>
                                    <option value="">Seleccionar cuenta...</option>
                                    {#each bankAccounts as account}
                                        <option value={String(account.id)}>
                                            {account.bank_name} · {account.account_name}{account.account_number ? ` · ${account.account_number}` : ''}
                                        </option>
                                    {/each}
                                </select>
                                {#if bankAccounts.length === 0}
                                    <div class="form-text text-danger">Registra una cuenta en Configuracion → Bancos para usar este método.</div>
                                {/if}
                            </div>
                        {/if}

                        {#if selectedPaymentMethod?.requires_reference}
                            <div>
                                <label class="form-label fw-semibold" for="payment-reference">Referencia</label>
                                <input
                                    id="payment-reference"
                                    class={`form-control ${selectedPaymentMethod?.requires_reference && paymentReference.trim().length === 0 ? 'is-invalid' : ''}`}
                                    type="text"
                                    bind:value={paymentReference}
                                    placeholder="Número de aprobación o comprobante"
                                />
                                {#if selectedPaymentMethod?.requires_reference && paymentReference.trim().length === 0}
                                    <div class="invalid-feedback">Este medio de pago requiere referencia.</div>
                                {/if}
                            </div>
                        {/if}

                        {#if selectedPaymentMethod?.allows_attachment}
                            <div>
                                <label class="form-label fw-semibold" for="payment-attachment">Comprobante</label>
                                <label class="taguara-pos-upload" for="payment-attachment">
                                    <Upload size={17} />
                                    <span>{paymentAttachment ? paymentAttachment.name : 'Adjuntar foto o PDF'}</span>
                                </label>
                                <input
                                    id="payment-attachment"
                                    class="visually-hidden"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp,application/pdf"
                                    onchange={(e) => paymentAttachment = e.currentTarget.files?.[0] ?? null}
                                />
                                <div class="form-text">Opcional. Máximo 4 MB.</div>
                            </div>
                        {/if}
                    </div>
                {/if}

                {#if isCashPayment}
                    <div class="mt-4">
                        <label class="form-label fw-semibold" for="tendered">Monto recibido</label>
                        <input
                            id="tendered"
                            class="form-control form-control-lg text-end"
                            type="number"
                            min={cartTotal}
                            step="1000"
                            bind:value={amountTendered}
                            bind:this={amountTenderedInput}
                            placeholder={String(cartTotal)}
                        />
                        <div class="taguara-pos-cash-suggestions">
                            {#each cashSuggestions as value}
                                <button type="button" onclick={() => amountTendered = String(value)}>
                                    {fmt(value)}
                                </button>
                            {/each}
                        </div>
                    </div>
                    {#if change !== null && amountTendered !== '' && Number(amountTendered) >= cartTotal}
                        <div class="taguara-pos-change-display mt-3">
                            <span class="text-secondary small">Cambio</span>
                            <span class="h3 mb-0 text-success fw-bold">{fmt(change)}</span>
                        </div>
                    {/if}
                {/if}

                {/if}<!-- end {#if isCredit} -->

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
                        {isCredit ? 'Registrar venta a crédito' : 'Confirmar venta'} <kbd class="taguara-btn-kbd">F9</kbd>
                    {/if}
                </button>
            </div>
        </div>
    {/if}

    <!-- Create Customer Modal -->
    {#if createCustomerOpen}
        <div class="taguara-drawer-backdrop" transition:fade={{ duration: 150 }} onclick={() => (createCustomerOpen = false)} role="presentation"></div>
        <div class="taguara-pos-modal" style="max-width:420px" transition:scale={{ duration: 180, start: 0.96 }}>
            <div class="taguara-pos-modal-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="taguara-kpi-icon text-bg-primary"><UserPlus size={18} /></span>
                    <div>
                        <p class="text-uppercase small fw-semibold mb-1" style="color:var(--bs-primary)">Nuevo cliente</p>
                        <h2 class="h5 mb-0">Registro rápido</h2>
                    </div>
                </div>
                <button class="btn btn-light border taguara-icon-button" type="button" onclick={() => (createCustomerOpen = false)}><X size={17} /></button>
            </div>

            <div class="taguara-pos-modal-body">
                {#if createCustomerErrors._general}
                    <div class="alert alert-danger small py-2 d-flex gap-2">
                        <AlertCircle size={15} class="flex-shrink-0 mt-1" />
                        {createCustomerErrors._general[0]}
                    </div>
                {/if}

                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small fw-semibold mb-1" for="cc-id-type">Tipo de documento *</label>
                        <select id="cc-id-type" class="form-select form-select-sm" bind:value={createForm.identification_type_code}>
                            {#each customerFormOptions.identification_types as type}
                                <option value={type.code}>{type.name}</option>
                            {/each}
                        </select>
                        {#if createCustomerErrors.identification_type_code}
                            <div class="text-danger" style="font-size:.75rem">{createCustomerErrors.identification_type_code[0]}</div>
                        {/if}
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-semibold mb-1" for="cc-id-num">Número de documento *</label>
                        <input
                            id="cc-id-num"
                            class="form-control form-control-sm"
                            bind:value={createForm.identification_number}
                            placeholder="Ej. 1234567890"
                        />
                        {#if createCustomerErrors.identification_number}
                            <div class="text-danger" style="font-size:.75rem">{createCustomerErrors.identification_number[0]}</div>
                        {/if}
                    </div>

                    {#if isNitForm}
                        <div class="col-12">
                            <label class="form-label small fw-semibold mb-1" for="cc-biz-name">Razón social *</label>
                            <input id="cc-biz-name" class="form-control form-control-sm" bind:value={createForm.business_name} placeholder="Nombre de la empresa" />
                            {#if createCustomerErrors.business_name}
                                <div class="text-danger" style="font-size:.75rem">{createCustomerErrors.business_name[0]}</div>
                            {/if}
                        </div>
                    {:else}
                        <div class="col-6">
                            <label class="form-label small fw-semibold mb-1" for="cc-fname">Nombre *</label>
                            <input id="cc-fname" class="form-control form-control-sm" bind:value={createForm.first_name} placeholder="Nombre" />
                            {#if createCustomerErrors.first_name}
                                <div class="text-danger" style="font-size:.75rem">{createCustomerErrors.first_name[0]}</div>
                            {/if}
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold mb-1" for="cc-lname">Apellido</label>
                            <input id="cc-lname" class="form-control form-control-sm" bind:value={createForm.last_name} placeholder="Apellido" />
                        </div>
                    {/if}

                    <div class="col-12">
                        <div class="form-label small fw-semibold mb-1">Régimen IVA</div>
                        <div class="d-flex gap-2">
                            {#each customerFormOptions.regime_types as regime}
                                <button
                                    type="button"
                                    class="btn btn-sm flex-fill {createForm.regime_type_code === regime.code ? 'btn-dark' : 'btn-outline-secondary'}"
                                    onclick={() => (createForm.regime_type_code = regime.code)}
                                >
                                    {regime.name}
                                </button>
                            {/each}
                        </div>
                    </div>

                    <div class="col-6">
                        <label class="form-label small fw-semibold mb-1" for="cc-phone">Teléfono</label>
                        <input id="cc-phone" class="form-control form-control-sm" bind:value={createForm.phone} type="tel" placeholder="Opcional" />
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold mb-1" for="cc-email">Email</label>
                        <input id="cc-email" class="form-control form-control-sm" bind:value={createForm.email} type="email" placeholder="Opcional" />
                        {#if createCustomerErrors.email}
                            <div class="text-danger" style="font-size:.75rem">{createCustomerErrors.email[0]}</div>
                        {/if}
                    </div>
                </div>

                <button
                    class="btn btn-taguara w-100 mt-4 d-inline-flex align-items-center justify-content-center gap-2"
                    type="button"
                    onclick={submitCreateCustomer}
                    disabled={createCustomerLoading}
                >
                    {#if createCustomerLoading}
                        <span class="spinner-border spinner-border-sm"></span>
                        Guardando...
                    {:else}
                        <UserPlus size={16} />
                        Crear cliente
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
                {#if receipt.payment_form === '2'}
                    <div class="alert alert-info small py-2 mb-3 d-flex align-items-center gap-2">
                        <CreditCard size={15} class="flex-shrink-0" />
                        Venta a crédito registrada. El pago se gestiona en Cartera.
                    </div>
                {/if}
                <div class="taguara-drawer-grid mb-4">
                    <span class="taguara-drawer-label">Total</span>
                    <span class="fw-bold">{fmt(receipt.total)}</span>
                    <span class="taguara-drawer-label">Forma de pago</span>
                    <span>{receipt.payment_method}</span>
                    {#if receipt.payment_form === '2' && receipt.payment_due_date}
                        <span class="taguara-drawer-label">Vence</span>
                        <span class="text-warning fw-semibold">{receipt.payment_due_date}</span>
                    {/if}
                    {#if receipt.change_amount !== null && receipt.change_amount > 0}
                        <span class="taguara-drawer-label">Cambio entregado</span>
                        <span class="text-success fw-semibold">{fmt(receipt.change_amount)}</span>
                    {/if}
                    <span class="taguara-drawer-label">Items vendidos</span>
                    <span>{receipt.items_count}</span>
                </div>

                {#if qzError}
                    <div class="alert alert-warning small py-2 mb-2">{qzError}</div>
                {/if}

                <div class="d-flex gap-2 mb-2">
                    {#if printerName}
                        <!-- Impresión directa QZ Tray -->
                        <button
                            class="btn btn-outline-success flex-fill d-inline-flex align-items-center justify-content-center gap-2"
                            type="button"
                            onclick={() => printDirectly(receipt)}
                            disabled={qzPrinting}
                        >
                            <PrinterCheck size={16} />
                            {qzPrinting ? 'Imprimiendo...' : 'Imprimir directo'}
                        </button>
                    {:else}
                        <!-- Fallback: recibo HTML en nueva pestaña -->
                        <a
                            class="btn btn-light border flex-fill d-inline-flex align-items-center justify-content-center gap-2"
                            href={receipt?.uuid ? `/sales/${receipt.uuid}/receipt` : '#'}
                            target="_blank"
                            rel="noopener"
                        >
                            <PrinterCheck size={17} />
                            Imprimir recibo
                        </a>
                    {/if}
                </div>

                <div class="d-flex gap-2">
                    {#if printerName}
                        <!-- Si hay QZ, igual ofrecer el HTML como alternativa -->
                        <a
                            class="btn btn-light border d-inline-flex align-items-center justify-content-center gap-1"
                            href={receipt?.uuid ? `/sales/${receipt.uuid}/receipt` : '#'}
                            target="_blank"
                            rel="noopener"
                            title="Abrir recibo en el navegador"
                        >
                            <PrinterCheck size={15} />
                        </a>
                    {/if}
                    <button class="btn btn-taguara flex-fill d-inline-flex align-items-center justify-content-center gap-2" type="button" onclick={startNewSale}>
                        <Plus size={17} />
                        Nueva venta
                    </button>
                </div>
            </div>
        </div>
    {/if}

    <!-- Controlled medication prescription modal -->
    {#if expirationAlert}
        <div class="taguara-drawer-backdrop" transition:fade={{ duration: 150 }} onclick={dismissExpirationAlert} role="presentation"></div>
        <div class="taguara-modal" transition:scale={{ duration: 180, start: 0.95 }} role="dialog" aria-modal="true">
            <div class="taguara-modal-inner" style="max-width:440px">
                <div class="taguara-modal-header">
                    <div class="d-flex align-items-center gap-2">
                        <AlertCircle size={20} class="text-warning" />
                        <h2 class="h5 mb-0">Lote próximo a vencer</h2>
                    </div>
                    <button class="btn btn-light border taguara-icon-button" type="button" onclick={dismissExpirationAlert}><X size={17} /></button>
                </div>
                <div class="taguara-modal-body">
                    <div class="alert alert-warning d-flex align-items-start gap-2 mb-3">
                        <AlertCircle size={18} class="flex-shrink-0 mt-1" />
                        <div>
                            <strong>{expirationAlert.product.commercial_name}</strong> —
                            el lote disponible vence el <strong>{expirationAlert.expires_on}</strong>
                            {#if expirationAlert.daysLeft <= 0}
                                (ya venció).
                            {:else}
                                (en <strong>{expirationAlert.daysLeft}</strong> día{expirationAlert.daysLeft !== 1 ? 's' : ''}).
                            {/if}
                        </div>
                    </div>
                    <p class="text-secondary small mb-0">¿Deseas agregarlo al carrito de todas formas?</p>
                </div>
                <div class="taguara-modal-footer">
                    <button class="btn btn-light border flex-fill" type="button" onclick={dismissExpirationAlert}>Cancelar</button>
                    <button
                        class="btn btn-warning flex-fill d-inline-flex align-items-center justify-content-center gap-2"
                        type="button"
                        onclick={() => addToCartAfterExpirationCheck(expirationAlert.product, expirationAlert.presentation)}
                    >
                        Agregar de todas formas
                    </button>
                </div>
            </div>
        </div>
    {/if}

    <!-- Modal: Últimos documentos del turno -->
    {#if recentOpen}
        <div class="taguara-drawer-backdrop" transition:fade={{ duration: 150 }} onclick={() => recentOpen = false} role="presentation"></div>
        <div class="taguara-pos-modal" style="max-width:520px" transition:scale={{ duration: 180, start: 0.96 }}>
            <div class="taguara-pos-modal-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="taguara-kpi-icon text-bg-secondary"><History size={18} /></span>
                    <div>
                        <p class="text-uppercase small fw-semibold mb-1" style="color:var(--taguara-muted)">Turno activo</p>
                        <h2 class="h5 mb-0">Últimos documentos</h2>
                    </div>
                </div>
                <button class="btn btn-light border taguara-icon-button" type="button" onclick={() => recentOpen = false}><X size={17} /></button>
            </div>
            <div class="taguara-pos-modal-body" style="padding:0; overflow-y:auto; max-height:60vh">
                {#if recentLoading}
                    <div class="d-flex justify-content-center align-items-center py-5">
                        <span class="spinner-border spinner-border-sm text-secondary me-2" role="status"></span>
                        <span class="text-secondary small">Cargando...</span>
                    </div>
                {:else if recentSales.length === 0}
                    <div class="taguara-empty-state py-5">
                        <History size={28} class="text-secondary" />
                        <p class="text-secondary small mb-0 mt-2">Sin ventas en este turno aún.</p>
                    </div>
                {:else}
                    <div class="taguara-pos-recent-list" style="margin:0">
                        {#each recentSales as sale}
                            <div class="taguara-pos-recent-item">
                                <div class="min-w-0 flex-grow-1">
                                    <div class="fw-semibold" style="font-size:.82rem">{sale.document_number}</div>
                                    <div class="taguara-table-sub">{sale.created_at} · {sale.payment_method}</div>
                                </div>
                                <span class="fw-semibold" style="font-size:.82rem;white-space:nowrap">
                                    $ {Number(sale.total).toLocaleString('es-CO')}
                                </span>
                                <div class="d-flex gap-1 flex-shrink-0">
                                    <a
                                        class="btn btn-sm btn-light border taguara-icon-button-sm"
                                        href={`/sales/${sale.uuid}`}
                                        title="Ver detalle"
                                        aria-label="Ver detalle"
                                    >
                                        <Eye size={13} />
                                    </a>
                                    <a
                                        class="btn btn-sm btn-light border taguara-icon-button-sm"
                                        href={`/sales/${sale.uuid}/receipt`}
                                        target="_blank"
                                        rel="noopener"
                                        title="Recibo HTML"
                                        aria-label="Recibo HTML"
                                    >
                                        <PrinterCheck size={13} />
                                    </a>
                                    {#if printerName}
                                        <button
                                            class="btn btn-sm btn-light border taguara-icon-button-sm"
                                            type="button"
                                            disabled={recentPrinting === sale.uuid}
                                            title="Reimprimir térmica"
                                            aria-label="Reimprimir térmica"
                                            onclick={() => printRecentThermal(sale)}
                                        >
                                            <Printer size={13} />
                                        </button>
                                    {/if}
                                </div>
                            </div>
                        {/each}
                    </div>
                {/if}
            </div>
        </div>
    {/if}

    {#if prescriptionModal}
        <div class="taguara-drawer-backdrop" transition:fade={{ duration: 150 }} onclick={() => prescriptionModal = null} role="presentation"></div>
        <div class="taguara-pos-modal" style="max-width:440px" transition:scale={{ duration: 180, start: 0.96 }}>
            <div class="taguara-pos-modal-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-warning mb-1" style="display:flex;align-items:center;gap:6px">
                        <ShieldAlert size={14} /> Medicamento controlado
                    </p>
                    <h2 class="h5 mb-0">{prescriptionModal.product.commercial_name}</h2>
                </div>
                <button class="btn btn-light border taguara-icon-button" type="button" onclick={() => prescriptionModal = null}><X size={17} /></button>
            </div>
            <div class="taguara-pos-modal-body">
                <p class="small text-secondary mb-3">
                    Este medicamento requiere registro del paciente y número de receta (Res. 1478/2006).
                </p>
                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="rx-numero">No. de receta médica</label>
                    <input
                        id="rx-numero"
                        type="text"
                        class="form-control"
                        placeholder="Ej. RM-2024-001234"
                        bind:value={prescriptionForm.prescription_number}
                    />
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="rx-cedula">Cédula del paciente <span class="text-danger">*</span></label>
                    <input
                        id="rx-cedula"
                        type="text"
                        class="form-control"
                        placeholder="Número de identificación"
                        bind:value={prescriptionForm.patient_id_number}
                    />
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-semibold" for="rx-nombre">Nombre del paciente</label>
                    <input
                        id="rx-nombre"
                        type="text"
                        class="form-control"
                        placeholder="Nombre completo"
                        bind:value={prescriptionForm.patient_name}
                    />
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-light border flex-fill" type="button" onclick={() => prescriptionModal = null}>
                        Cancelar
                    </button>
                    <button
                        class="btn btn-warning flex-fill fw-semibold"
                        type="button"
                        onclick={confirmPrescription}
                        disabled={!prescriptionForm.patient_id_number.trim()}
                    >
                        Agregar al carrito
                    </button>
                </div>
            </div>
        </div>
    {/if}
</AppLayout>
