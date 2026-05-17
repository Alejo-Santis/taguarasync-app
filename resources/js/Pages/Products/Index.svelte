<script>
    import { Link, router } from '@inertiajs/svelte';
    import {
        Barcode,
        Boxes,
        ChevronDown,
        Filter,
        Package,
        PackageCheck,
        Plus,
        RotateCcw,
        Search,
        ShieldAlert,
        Tags,
    } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';

    let { auth, products, filters, stats, statuses } = $props();

    let form = $state({
        q: '',
        status: '',
        controlled: '',
    });
    let expandedProducts = $state({});

    const money = new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    });

    const hasProducts = $derived(products.data.length > 0);

    $effect(() => {
        form.q = filters.q ?? '';
        form.status = filters.status ?? '';
        form.controlled = filters.controlled ?? '';
    });

    const submit = (event) => {
        event.preventDefault();

        router.get('/products', form, {
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        form = { q: '', status: '', controlled: '' };

        router.get('/products', {}, {
            preserveState: true,
            replace: true,
        });
    };

    const toggleProduct = (uuid) => {
        expandedProducts[uuid] = !expandedProducts[uuid];
    };

    const statusClass = (status) => {
        if (status === 'active') {
            return 'text-bg-success';
        }

        if (status === 'inactive') {
            return 'text-bg-secondary';
        }

        return 'text-bg-warning';
    };

    const formatMoney = (value) => {
        if (value === null || value === undefined) {
            return 'Sin precio';
        }

        return money.format(value);
    };
</script>

<AppLayout title="Productos" activeSection="products" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Catalogo farmaceutico</p>
                <h2 class="h3 mb-2">Productos y presentaciones</h2>
                <p class="text-secondary mb-0">Consulta por producto, codigo, barra o presentacion antes de conectar inventario y compras.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="button">
                    <Plus size={18} />
                    Nuevo producto
                </button>
            </div>
        </section>

        <section class="row g-3">
            <div class="col-12 col-md-6 col-xl-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-success"><PackageCheck size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Productos activos</p>
                        <p class="h3 mb-1">{stats.active}</p>
                        <p class="small text-secondary mb-0">Listos para vender</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-primary"><Boxes size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Total catalogo</p>
                        <p class="h3 mb-1">{stats.total}</p>
                        <p class="small text-secondary mb-0">En esta farmacia</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-warning"><ShieldAlert size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Controlados</p>
                        <p class="h3 mb-1">{stats.controlled}</p>
                        <p class="small text-secondary mb-0">Requieren seguimiento</p>
                    </div>
                </article>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <article class="taguara-kpi-card">
                    <span class="taguara-kpi-icon text-bg-info"><Tags size={20} /></span>
                    <div>
                        <p class="text-secondary small mb-1">Presentaciones</p>
                        <p class="h3 mb-1">{stats.presentations}</p>
                        <p class="small text-secondary mb-0">Unidad, blister, caja, frasco</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header align-items-start">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Busqueda</p>
                    <h3 class="h5 mb-0">Filtros del catalogo</h3>
                </div>
                <Filter class="text-secondary" size={22} />
            </div>

            <form class="taguara-filter-grid" onsubmit={submit}>
                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Producto, codigo o barra</span>
                    <span class="taguara-filter-input">
                        <Search size={17} />
                        <input class="form-control" bind:value={form.q} type="search" placeholder="Acetaminofen, PRD-1001, 770..." />
                    </span>
                </label>

                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Estado</span>
                    <select class="form-select" bind:value={form.status}>
                        <option value="">Todos</option>
                        {#each statuses as status}
                            <option value={status.value}>{status.label}</option>
                        {/each}
                    </select>
                </label>

                <label class="form-label mb-0">
                    <span class="small fw-semibold text-secondary">Control sanitario</span>
                    <select class="form-select" bind:value={form.controlled}>
                        <option value="">Todos</option>
                        <option value="yes">Controlados</option>
                        <option value="no">No controlados</option>
                    </select>
                </label>

                <div class="d-flex align-items-end gap-2">
                    <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="submit">
                        <Search size={17} />
                        Filtrar
                    </button>
                    <button class="btn btn-light border taguara-icon-button" type="button" aria-label="Limpiar filtros" onclick={resetFilters}>
                        <RotateCcw size={17} />
                    </button>
                </div>
            </form>
        </section>

        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Resultados</p>
                    <h3 class="h5 mb-0">{products.total} productos encontrados</h3>
                </div>
                <span class="badge text-bg-light border text-secondary">
                    Pagina {products.current_page} de {products.last_page}
                </span>
            </div>

            {#if hasProducts}
                <div class="taguara-product-list">
                    {#each products.data as product}
                        <article class="taguara-product-row">
                            <div class="taguara-product-summary">
                                <div class="taguara-product-main">
                                    <span class="taguara-product-icon"><Package size={20} /></span>
                                    <div class="min-w-0">
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                            <h4 class="h6 mb-0 text-truncate">{product.commercial_name}</h4>
                                            <span class={`badge ${statusClass(product.status.value)}`}>{product.status.label}</span>
                                            {#if product.is_controlled}
                                                <span class="badge text-bg-warning">Controlado</span>
                                            {/if}
                                        </div>
                                        <p class="text-secondary small mb-0">
                                            {product.generic_name ?? 'Sin generico'} · {product.pharmaceutical_form ?? 'Sin forma'} · {product.concentration ?? 'Sin concentracion'}
                                        </p>
                                    </div>
                                </div>

                                <div class="taguara-product-price">
                                    <p class="text-secondary small mb-1">Precio base</p>
                                    <p class="h6 mb-0">{formatMoney(product.sale_price)}</p>
                                </div>

                                <div class="taguara-product-count">
                                    <p class="text-secondary small mb-1">Presentaciones</p>
                                    <p class="h6 mb-0">{product.presentations_count}</p>
                                </div>

                                <button
                                    class="btn btn-light border taguara-detail-button"
                                    type="button"
                                    aria-expanded={Boolean(expandedProducts[product.uuid])}
                                    aria-label={expandedProducts[product.uuid] ? 'Ocultar detalle del producto' : 'Mostrar detalle del producto'}
                                    onclick={() => toggleProduct(product.uuid)}
                                >
                                    <span class="d-none d-sm-inline">{expandedProducts[product.uuid] ? 'Ocultar' : 'Detalle'}</span>
                                    <ChevronDown class={expandedProducts[product.uuid] ? 'taguara-rotate' : ''} size={17} />
                                </button>
                            </div>

                            {#if expandedProducts[product.uuid]}
                                <div class="taguara-product-detail">
                                    <div class="taguara-product-meta">
                                        <span><Barcode size={15} /> {product.barcode ?? 'Sin codigo de barras'}</span>
                                        <span>{product.internal_code ?? 'Sin codigo interno'}</span>
                                        <span>{product.laboratory ?? 'Sin laboratorio'}</span>
                                        <span>{product.category ?? 'Sin categoria'}</span>
                                        <span>{product.active_ingredient ?? 'Sin principio activo'}</span>
                                    </div>

                                    <div class="taguara-presentation-strip">
                                        {#each product.presentations as presentation}
                                            <span class={`taguara-presentation-pill ${presentation.is_default ? 'default' : ''}`}>
                                                {presentation.name}
                                                <small>{presentation.minimum_unit_quantity} {product.minimum_unit?.code ?? 'und'}</small>
                                            </span>
                                        {/each}
                                    </div>
                                </div>
                            {/if}
                        </article>
                    {/each}
                </div>

                {#if products.links.length > 3}
                    <nav class="taguara-pagination" aria-label="Paginacion de productos">
                        {#each products.links as link}
                            {#if link.url}
                                <Link class={`btn btn-sm ${link.active ? 'btn-taguara' : 'btn-light border'}`} href={link.url}>
                                    {@html link.label}
                                </Link>
                            {:else}
                                <span class="btn btn-sm btn-light border disabled">{@html link.label}</span>
                            {/if}
                        {/each}
                    </nav>
                {/if}
            {:else}
                <div class="taguara-empty-state">
                    <Package size={34} />
                    <h4 class="h6 mb-1">No hay productos con estos filtros</h4>
                    <p class="text-secondary mb-0">Cuando registremos el formulario de productos, aqui quedara el flujo de alta del catalogo.</p>
                </div>
            {/if}
        </section>
    </div>
</AppLayout>
