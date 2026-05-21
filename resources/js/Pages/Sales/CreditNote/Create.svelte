<script>
    import { Link, useForm } from '@inertiajs/svelte';
    import { ArrowLeft, FileX, Minus, Plus, ReceiptText } from '@lucide/svelte';
    import AppLayout from '../../../Layouts/AppLayout.svelte';

    let { auth, sale, discrepancy_reasons } = $props();

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const fmt = (v) => money.format(v ?? 0);

    // Inicia con todos los ítems de la venta original
    const form = useForm({
        discrepancy_reason_code: '',
        notes: '',
        items: sale.items.map((i) => ({
            sale_item_id: i.id,
            description: i.description,
            quantity: i.quantity,
            unit_price: i.unit_price,
            tax_rate: Number(i.tax_rate),
            included: true,
        })),
    });

    const subtotal = $derived(
        form.items.filter((i) => i.included)
            .reduce((s, i) => s + i.quantity * i.unit_price, 0)
    );
    const taxTotal = $derived(
        form.items.filter((i) => i.included)
            .reduce((s, i) => s + Math.round(i.quantity * i.unit_price * (i.tax_rate / 100)), 0)
    );
    const total = $derived(subtotal + taxTotal);

    const setQty = (index, delta) => {
        const max = sale.items[index].quantity;
        const newQty = Math.max(1, Math.min(max, form.items[index].quantity + delta));
        form.items[index] = { ...form.items[index], quantity: newQty };
        form.items = [...form.items];
    };

    const toggleItem = (index) => {
        form.items[index] = { ...form.items[index], included: !form.items[index].included };
        form.items = [...form.items];
    };

    const submit = () => {
        const payload = {
            discrepancy_reason_code: form.discrepancy_reason_code,
            notes: form.notes,
            items: form.items.filter((i) => i.included).map(({ sale_item_id, description, quantity, unit_price, tax_rate }) => ({
                sale_item_id, description, quantity, unit_price, tax_rate,
            })),
        };
        form.transform(() => payload).post(`/sales/${sale.uuid}/credit-notes`);
    };
</script>

<AppLayout title="Nueva nota crédito" activeSection="sales" {auth}>
    <div class="taguara-products">

        <section class="taguara-command-band">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <Link href="/sales" class="btn btn-sm btn-light border d-inline-flex align-items-center gap-1">
                        <ArrowLeft size={14} /> Ventas
                    </Link>
                </div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Nota crédito</p>
                <h2 class="h3 mb-1">Devolución / ajuste</h2>
                <p class="text-secondary mb-0">
                    Venta original: <strong>{sale.invoice_prefix ?? ''}{sale.document_number}</strong>
                    · {sale.customer_name} · {sale.created_at}
                </p>
            </div>
            <FileX class="text-secondary" size={22} />
        </section>

        {#if !sale.fe_cufe}
            <div class="alert alert-warning d-flex gap-2 align-items-center">
                <ReceiptText size={18} />
                <div>
                    La venta original no tiene CUFE. Emite primero la factura electrónica desde el listado de ventas antes de crear una nota crédito.
                </div>
            </div>
        {/if}

        <div class="row g-3">
            <!-- Ítems -->
            <div class="col-lg-8">
                <section class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Ítems a devolver</p>
                            <h3 class="h5 mb-0">Selecciona y ajusta las cantidades</h3>
                        </div>
                    </div>

                    <div class="taguara-table-wrapper">
                        <table class="taguara-table">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>Descripción</th>
                                    <th class="text-center">Cant.</th>
                                    <th class="text-end">Precio</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {#each form.items as item, index}
                                    <tr class={item.included ? '' : 'opacity-50'}>
                                        <td>
                                            <input
                                                type="checkbox"
                                                class="form-check-input"
                                                checked={item.included}
                                                onchange={() => toggleItem(index)}
                                            />
                                        </td>
                                        <td>
                                            <div class="taguara-table-name">{item.description}</div>
                                            {#if item.tax_rate > 0}
                                                <div class="taguara-table-sub">IVA {item.tax_rate}%</div>
                                            {/if}
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center justify-content-center gap-1">
                                                <button
                                                    class="btn btn-sm btn-light border px-1"
                                                    type="button"
                                                    disabled={!item.included || item.quantity <= 1}
                                                    onclick={() => setQty(index, -1)}
                                                >
                                                    <Minus size={12} />
                                                </button>
                                                <span class="fw-semibold" style="min-width:24px; text-align:center">{item.quantity}</span>
                                                <button
                                                    class="btn btn-sm btn-light border px-1"
                                                    type="button"
                                                    disabled={!item.included || item.quantity >= sale.items[index].quantity}
                                                    onclick={() => setQty(index, 1)}
                                                >
                                                    <Plus size={12} />
                                                </button>
                                            </div>
                                        </td>
                                        <td class="text-end text-secondary" style="font-size:.875rem">{fmt(item.unit_price)}</td>
                                        <td class="text-end fw-semibold" style="font-size:.875rem">
                                            {fmt(item.quantity * item.unit_price)}
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- Panel derecho -->
            <div class="col-lg-4">
                <section class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Datos de la nota</p>
                        </div>
                    </div>

                    <div class="vstack gap-3">
                        <div>
                            <label class="form-label" for="reason">Motivo <span class="text-danger">*</span></label>
                            <select
                                id="reason"
                                class="form-select"
                                class:is-invalid={form.errors.discrepancy_reason_code}
                                bind:value={form.discrepancy_reason_code}
                            >
                                <option value="">Seleccionar...</option>
                                {#each discrepancy_reasons as r}
                                    <option value={r.code}>{r.code}. {r.name}</option>
                                {/each}
                            </select>
                            {#if form.errors.discrepancy_reason_code}
                                <div class="invalid-feedback">{form.errors.discrepancy_reason_code}</div>
                            {/if}
                        </div>

                        <div>
                            <label class="form-label" for="notes">Observaciones</label>
                            <textarea
                                id="notes"
                                class="form-control"
                                rows="3"
                                placeholder="Descripción adicional de la devolución..."
                                bind:value={form.notes}
                            ></textarea>
                        </div>

                        <div class="border rounded p-3" style="background:var(--taguara-surface)">
                            <div class="d-flex justify-content-between small text-secondary mb-1">
                                <span>Subtotal</span><span>{fmt(subtotal)}</span>
                            </div>
                            <div class="d-flex justify-content-between small text-secondary mb-2">
                                <span>IVA</span><span>{fmt(taxTotal)}</span>
                            </div>
                            <div class="d-flex justify-content-between fw-bold">
                                <span>Total nota crédito</span><span class="text-success">{fmt(total)}</span>
                            </div>
                        </div>

                        <button
                            class="btn btn-taguara w-100"
                            type="button"
                            onclick={submit}
                            disabled={form.processing || !form.discrepancy_reason_code || total === 0 || !sale.fe_cufe}
                        >
                            {form.processing ? 'Creando...' : 'Crear nota crédito'}
                        </button>

                        {#if form.errors.fe}
                            <div class="alert alert-danger small py-2 mb-0">{form.errors.fe}</div>
                        {/if}
                        {#if form.errors.items}
                            <div class="alert alert-danger small py-2 mb-0">{form.errors.items}</div>
                        {/if}
                    </div>
                </section>
            </div>
        </div>
    </div>
</AppLayout>
