<script>
    import { Link, useForm } from '@inertiajs/svelte';
    import { ArrowLeft, Monitor, Unlock } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';

    let { auth, registers } = $props();

    const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });

    const availableRegisters = $derived(registers.filter((r) => !r.has_open_session));

    const form = useForm(() => ({
        cash_register_id: availableRegisters[0]?.id ?? '',
        opening_amount: 0,
        notes: '',
    }));

    const submit = () => {
        form.post('/pos/session');
    };
</script>

<AppLayout title="Abrir turno" activeSection="pos" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Punto de venta</p>
                <h2 class="h3 mb-2">Abrir turno de caja</h2>
                <p class="text-secondary mb-0">Selecciona la caja, registra el saldo inicial en efectivo y comienza las ventas.</p>
            </div>
            <Link class="btn btn-light border d-inline-flex align-items-center gap-2" href="/dashboard">
                <ArrowLeft size={18} />
                Volver al panel
            </Link>
        </section>

        <div class="row g-3 justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Apertura</p>
                            <h3 class="h5 mb-0">Datos del turno</h3>
                        </div>
                        <Monitor class="text-success" size={22} />
                    </div>

                    {#if availableRegisters.length === 0}
                        <div class="alert alert-warning d-flex gap-2">
                            <Monitor size={18} class="flex-shrink-0 mt-1" />
                            <div>
                                <strong>Todas las cajas tienen un turno abierto.</strong>
                                <p class="mb-0 small">Cierra el turno de otra caja o crea una nueva en Configuracion → Cajas.</p>
                            </div>
                        </div>
                    {:else}
                        <form class="vstack gap-4" onsubmit={(e) => { e.preventDefault(); submit(); }}>
                            <div>
                                <label class="form-label" for="register">Caja <span class="text-danger">*</span></label>
                                <select id="register" class="form-select form-select-lg" class:is-invalid={form.errors.cash_register_id} bind:value={form.cash_register_id}>
                                    {#each availableRegisters as r}
                                        <option value={r.id}>{r.name} — <code>{r.code}</code></option>
                                    {/each}
                                </select>
                                {#if form.errors.cash_register_id}<div class="invalid-feedback">{form.errors.cash_register_id}</div>{/if}
                            </div>

                            <div>
                                <label class="form-label" for="opening">Saldo de apertura (efectivo en caja) <span class="text-danger">*</span></label>
                                <input id="opening" class="form-control form-control-lg text-end" class:is-invalid={form.errors.opening_amount} type="number" min="0" step="1000" bind:value={form.opening_amount} placeholder="0">
                                {#if form.opening_amount > 0}
                                    <div class="form-text text-success">{money.format(form.opening_amount)} en caja</div>
                                {/if}
                                {#if form.errors.opening_amount}<div class="invalid-feedback">{form.errors.opening_amount}</div>{/if}
                            </div>

                            <div>
                                <label class="form-label" for="notes">Notas (opcional)</label>
                                <input id="notes" class="form-control" type="text" bind:value={form.notes} placeholder="Observaciones del turno">
                            </div>

                            <button class="btn btn-taguara btn-lg w-100 d-inline-flex align-items-center justify-content-center gap-2" type="submit" disabled={form.processing}>
                                <Unlock size={18} />
                                {form.processing ? 'Abriendo turno...' : 'Abrir turno y comenzar'}
                            </button>
                        </form>
                    {/if}
                </div>

                {#if registers.some((r) => r.has_open_session)}
                    <div class="taguara-panel mt-3">
                        <p class="text-uppercase small fw-semibold text-secondary mb-2">Cajas con turno activo</p>
                        {#each registers.filter((r) => r.has_open_session) as r}
                            <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                                <span class="fw-semibold">{r.name}</span>
                                <span class="badge text-bg-success">Turno abierto</span>
                            </div>
                        {/each}
                    </div>
                {/if}
            </div>
        </div>
    </div>
</AppLayout>
