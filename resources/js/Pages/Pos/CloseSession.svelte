<script>
    import { useForm } from '@inertiajs/svelte';
    import { AlertTriangle, CheckCircle2, CircleDollarSign, Lock, Monitor } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';

    let { auth, session } = $props();

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const fmt = (v) => money.format(v ?? 0);

    const form = useForm(() => ({
        actual_closing_amount: session.expected_closing,
        notes: '',
    }));

    const difference = $derived(Number(form.actual_closing_amount) - session.expected_closing);
    const isShort = $derived(difference < 0);
    const isOver = $derived(difference > 0);

    const submit = () => {
        form.post(`/pos/session/${session.uuid}/close`);
    };
</script>

<AppLayout title="Cerrar turno" activeSection="pos" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Punto de venta</p>
                <h2 class="h3 mb-2">Cerrar turno de caja</h2>
                <p class="text-secondary mb-0">Registra el conteo real de efectivo para calcular la diferencia del turno.</p>
            </div>
            <Monitor class="text-secondary" size={22} />
        </section>

        <div class="row g-3 justify-content-center">
            <div class="col-12 col-md-10 col-lg-8">
                <section class="row g-3 mb-3">
                    <div class="col-12 col-md-4">
                        <article class="taguara-kpi-card">
                            <span class="taguara-kpi-icon text-bg-success"><CircleDollarSign size={20} /></span>
                            <div>
                                <p class="text-secondary small mb-1">Ventas totales</p>
                                <p class="h5 mb-0">{fmt(session.cash_sales + session.card_sales + session.transfer_sales)}</p>
                                <p class="small text-secondary mb-0">{session.total_sales} transacciones</p>
                            </div>
                        </article>
                    </div>
                    <div class="col-12 col-md-4">
                        <article class="taguara-kpi-card">
                            <span class="taguara-kpi-icon text-bg-primary"><CircleDollarSign size={20} /></span>
                            <div>
                                <p class="text-secondary small mb-1">Efectivo recibido</p>
                                <p class="h5 mb-0">{fmt(session.cash_sales)}</p>
                                <p class="small text-secondary mb-0">Ventas en cash</p>
                            </div>
                        </article>
                    </div>
                    <div class="col-12 col-md-4">
                        <article class="taguara-kpi-card">
                            <span class="taguara-kpi-icon text-bg-info"><CircleDollarSign size={20} /></span>
                            <div>
                                <p class="text-secondary small mb-1">Tarjeta / Transferencia</p>
                                <p class="h5 mb-0">{fmt(session.card_sales + session.transfer_sales)}</p>
                                <p class="small text-secondary mb-0">Sin movimiento en caja</p>
                            </div>
                        </article>
                    </div>
                </section>

                <div class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Arqueo</p>
                            <h3 class="h5 mb-0">{session.register_name} · {session.cashier_name}</h3>
                        </div>
                        <span class="text-secondary small">Turno desde {session.opened_at}</span>
                    </div>

                    <div class="taguara-drawer-grid mb-4">
                        <span class="taguara-drawer-label">Saldo apertura</span>
                        <span>{fmt(session.opening_amount)}</span>
                        <span class="taguara-drawer-label">Efectivo en ventas</span>
                        <span>{fmt(session.cash_sales)}</span>
                        <span class="taguara-drawer-label">Cierre esperado</span>
                        <span class="fw-bold">{fmt(session.expected_closing)}</span>
                    </div>

                    <form class="vstack gap-4" onsubmit={(e) => { e.preventDefault(); submit(); }}>
                        <div>
                            <label class="form-label" for="actual">Efectivo contado en caja <span class="text-danger">*</span></label>
                            <input id="actual" class="form-control form-control-lg text-end" class:is-invalid={form.errors.actual_closing_amount} type="number" min="0" step="1000" bind:value={form.actual_closing_amount}>
                            {#if form.errors.actual_closing_amount}<div class="invalid-feedback">{form.errors.actual_closing_amount}</div>{/if}
                        </div>

                        {#if form.actual_closing_amount !== ''}
                            <div class={`alert d-flex align-items-center gap-3 ${isShort ? 'alert-danger' : isOver ? 'alert-warning' : 'alert-success'}`}>
                                {#if isShort}
                                    <AlertTriangle size={20} class="flex-shrink-0" />
                                    <div>
                                        <strong>Faltante: {fmt(Math.abs(difference))}</strong>
                                        <p class="mb-0 small">El efectivo contado es menor al esperado.</p>
                                    </div>
                                {:else if isOver}
                                    <AlertTriangle size={20} class="flex-shrink-0" />
                                    <div>
                                        <strong>Sobrante: {fmt(difference)}</strong>
                                        <p class="mb-0 small">El efectivo contado es mayor al esperado.</p>
                                    </div>
                                {:else}
                                    <CheckCircle2 size={20} class="flex-shrink-0" />
                                    <strong>Caja cuadrada. Sin diferencia.</strong>
                                {/if}
                            </div>
                        {/if}

                        <div>
                            <label class="form-label" for="close-notes">Notas del cierre (opcional)</label>
                            <textarea id="close-notes" class="form-control" rows="2" bind:value={form.notes} placeholder="Observaciones del turno..."></textarea>
                        </div>

                        <button class="btn btn-danger btn-lg w-100 d-inline-flex align-items-center justify-content-center gap-2" type="submit" disabled={form.processing}>
                            <Lock size={18} />
                            {form.processing ? 'Cerrando turno...' : 'Confirmar cierre de turno'}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</AppLayout>
