<script>
    import { router, useForm } from '@inertiajs/svelte';
    import { onDestroy, onMount } from 'svelte';
    import {
        AlertCircle,
        CheckCircle2,
        Loader,
        Pencil,
        Printer,
        PrinterCheck,
        PrinterX,
        RefreshCw,
        Save,
        X,
        Zap,
    } from '@lucide/svelte';
    import AppLayout from '../../../Layouts/AppLayout.svelte';
    import PrinterStatus from '../../../Components/UI/PrinterStatus.svelte';
    import QzPrinter from '../../../Services/QzPrinter.js';

    let { auth, registers } = $props();

    // ── Estado global de QZ ───────────────────────────────────────────────────
    let qzStatus = $state(QzPrinter.connected ? 'connected' : 'idle');
    let qzUnsub;

    onMount(() => {
        qzUnsub = QzPrinter.onStatus(({ connected, error }) => {
            qzStatus = error ? 'error' : connected ? 'connected' : 'idle';
            if (connected && modalOpen) {
                loadPrinters();
            }
        });
        qzStatus = QzPrinter.connected ? 'connected' : 'idle';
    });

    onDestroy(() => qzUnsub?.());

    // ── Listado de impresoras detectadas por QZ ───────────────────────────────
    let availablePrinters = $state([]);
    let loadingPrinters   = $state(false);
    let printerError      = $state('');

    async function loadPrinters() {
        printerError    = '';
        loadingPrinters = true;
        try {
            if (!QzPrinter.connected) {
                await QzPrinter.connect();
            }
            availablePrinters = await QzPrinter.getPrinters();
        } catch (err) {
            printerError = err?.message ?? '¿QZ Tray está corriendo?';
        } finally {
            loadingPrinters = false;
        }
    }

    // ── Modal de edición ──────────────────────────────────────────────────────
    let modalOpen     = $state(false);
    let editingReg    = $state(null);
    let testResult    = $state('');
    let printingTest  = $state(false);

    const form = useForm({
        printer_name: '',
        paper_width:  '80mm',
        copies:       1,
        auto_print:   false,
    });

    function openModal(reg) {
        editingReg        = reg;
        form.printer_name = reg.printer_name ?? '';
        form.paper_width  = reg.paper_width  ?? '80mm';
        form.copies       = reg.copies       ?? 1;
        form.auto_print   = reg.auto_print   ?? false;
        testResult        = '';
        modalOpen         = true;

        if (QzPrinter.connected) {
            loadPrinters();
        }
    }

    function closeModal() {
        modalOpen  = false;
        editingReg = null;
        form.reset();
        form.clearErrors();
        availablePrinters = [];
        printerError      = '';
        testResult        = '';
    }

    const handleKeydown = (e) => {
        if (e.key === 'Escape' && modalOpen) closeModal();
    };

    const submit = () => {
        form.put(`/settings/printer/${editingReg.id}`, {
            onSuccess: closeModal,
            preserveScroll: true,
        });
    };

    async function printTest() {
        if (!form.printer_name) {
            testResult = 'error:Selecciona una impresora primero.';
            return;
        }
        printingTest = true;
        testResult   = '';
        try {
            if (!QzPrinter.connected) await QzPrinter.connect();
            await QzPrinter.print(form.printer_name, [
                '\x1B@',
                '\x1Ba\x01',
                '\x1BE\x01TAGUARA SYNC\x1BE\x00\n',
                'Prueba de impresion\n',
                `Caja: ${editingReg?.name ?? ''}\n`,
                `Ancho: ${form.paper_width}\n`,
                `Copias: ${form.copies}\n`,
                '--------------------------------\n',
                'Impresora configurada OK\n',
                '\n\n\n',
                '\x1DV\x42\x00',
            ]);
            testResult = 'ok';
        } catch (err) {
            testResult = 'error:' + (err?.message ?? 'Error al imprimir.');
        } finally {
            printingTest = false;
        }
    }

    // ── Helpers de vista ──────────────────────────────────────────────────────
    const configuredCount = $derived(registers.filter((r) => r.printer_name).length);
</script>

<svelte:window onkeydown={handleKeydown} />

<AppLayout title="Impresoras" activeSection="printer" {auth}>
    <div class="taguara-products">

        <!-- Encabezado -->
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-secondary mb-2">Configuración</p>
                <h2 class="h3 mb-2">Impresoras por caja</h2>
                <p class="text-secondary mb-0">
                    Configura la impresora térmica de cada caja. Requiere QZ Tray instalado en cada PC cajero.
                </p>
            </div>
            <PrinterStatus />
        </section>

        <!-- Info QZ Tray -->
        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-primary mb-1">Requisito</p>
                    <h3 class="h5 mb-0">QZ Tray debe estar instalado en cada PC cajero</h3>
                </div>
                <Printer class="text-primary" size={22} />
            </div>
            <div class="row g-3 mt-1">
                <div class="col-md-4">
                    <div class="d-flex gap-2 align-items-start">
                        <span class="badge rounded-pill text-bg-primary mt-1">1</span>
                        <div>
                            <p class="fw-semibold mb-1 small">Descargar QZ Tray</p>
                            <p class="text-secondary small mb-0">Instala la app en Windows/Mac/Linux desde <strong>qz.io/download</strong></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-2 align-items-start">
                        <span class="badge rounded-pill text-bg-primary mt-1">2</span>
                        <div>
                            <p class="fw-semibold mb-1 small">Iniciar QZ Tray</p>
                            <p class="text-secondary small mb-0">Debe aparecer el ícono en la bandeja del sistema antes de imprimir.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-2 align-items-start">
                        <span class="badge rounded-pill text-bg-primary mt-1">3</span>
                        <div>
                            <p class="fw-semibold mb-1 small">Conectar y configurar</p>
                            <p class="text-secondary small mb-0">Haz clic en <strong>Editar</strong> en cada caja y selecciona la impresora local.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Tabla de cajas -->
        <section class="taguara-panel p-0 overflow-hidden">
            <div class="taguara-panel-header px-4 pt-4 pb-3 border-bottom">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Cajas</p>
                    <h3 class="h5 mb-0">Impresoras configuradas</h3>
                </div>
                <span class="badge text-bg-secondary rounded-pill">
                    {configuredCount} / {registers.length} configuradas
                </span>
            </div>

            {#if registers.length === 0}
                <div class="text-center py-5 text-secondary">
                    <Printer size={32} class="mb-2 opacity-50" />
                    <p class="mb-0">No hay cajas registradas. Crea cajas en Configuración → Cajas.</p>
                </div>
            {:else}
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Caja</th>
                                <th>Impresora</th>
                                <th>Papel</th>
                                <th>Copias</th>
                                <th>Auto-imprimir</th>
                                <th class="text-end pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each registers as reg (reg.id)}
                                <tr class={!reg.is_active ? 'opacity-50' : ''}>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="fw-semibold">{reg.name}</span>
                                            <span class="badge text-bg-light border small">{reg.code}</span>
                                            {#if !reg.is_active}
                                                <span class="badge text-bg-secondary small">inactiva</span>
                                            {/if}
                                        </div>
                                    </td>
                                    <td>
                                        {#if reg.printer_name}
                                            <div class="d-flex align-items-center gap-2">
                                                <PrinterCheck size={14} class="text-success flex-shrink-0" />
                                                <span class="small">{reg.printer_name}</span>
                                            </div>
                                        {:else}
                                            <div class="d-flex align-items-center gap-2 text-secondary">
                                                <PrinterX size={14} class="flex-shrink-0" />
                                                <span class="small fst-italic">Sin configurar</span>
                                            </div>
                                        {/if}
                                    </td>
                                    <td>
                                        {#if reg.printer_name}
                                            <span class="badge text-bg-light border">{reg.paper_width}</span>
                                        {:else}
                                            <span class="text-secondary small">—</span>
                                        {/if}
                                    </td>
                                    <td>
                                        {#if reg.printer_name}
                                            <span class="small">{reg.copies}</span>
                                        {:else}
                                            <span class="text-secondary small">—</span>
                                        {/if}
                                    </td>
                                    <td>
                                        {#if reg.printer_name}
                                            {#if reg.auto_print}
                                                <span class="badge text-bg-warning">
                                                    <Zap size={11} class="me-1" />Automática
                                                </span>
                                            {:else}
                                                <span class="text-secondary small">Manual</span>
                                            {/if}
                                        {:else}
                                            <span class="text-secondary small">—</span>
                                        {/if}
                                    </td>
                                    <td class="text-end pe-4">
                                        <button
                                            class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1"
                                            type="button"
                                            onclick={() => openModal(reg)}
                                        >
                                            <Pencil size={13} />
                                            Editar
                                        </button>
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            {/if}
        </section>
    </div>
</AppLayout>

<!-- Modal de edición -->
{#if modalOpen && editingReg}
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <!-- svelte-ignore a11y_click_events_have_key_events -->
    <div class="taguara-modal-backdrop" onclick={closeModal}></div>

    <div class="taguara-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <div class="taguara-modal-header">
            <div>
                <h4 class="h5 mb-0" id="modal-title">
                    <Printer size={18} class="me-2 text-primary" />
                    {editingReg.name}
                </h4>
                <p class="text-secondary small mb-0 mt-1">Configura la impresora de esta caja</p>
            </div>
            <button class="btn btn-sm btn-light border taguara-icon-button-sm" type="button" onclick={closeModal} aria-label="Cerrar">
                <X size={15} />
            </button>
        </div>

        <div class="taguara-modal-body">

            <!-- Selector de impresora -->
            <div class="mb-3">
                <label class="form-label fw-semibold small" for="modal-printer-name">Impresora</label>
                <div class="d-flex gap-2">
                    {#if availablePrinters.length > 0}
                        <select
                            id="modal-printer-name"
                            class="form-select form-select-sm {form.errors.printer_name ? 'is-invalid' : ''}"
                            bind:value={form.printer_name}
                        >
                            <option value="">— Sin impresora —</option>
                            {#each availablePrinters as p}
                                <option value={p}>{p}</option>
                            {/each}
                        </select>
                    {:else}
                        <input
                            id="modal-printer-name"
                            class="form-control form-control-sm {form.errors.printer_name ? 'is-invalid' : ''}"
                            type="text"
                            bind:value={form.printer_name}
                            placeholder="Ej. EPSON TM-T20III"
                        />
                    {/if}
                    <button
                        class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 flex-shrink-0"
                        type="button"
                        onclick={loadPrinters}
                        disabled={loadingPrinters}
                    >
                        {#if loadingPrinters}
                            <Loader size={13} class="spin" />
                        {:else}
                            <RefreshCw size={13} />
                        {/if}
                        Detectar
                    </button>
                </div>
                {#if printerError}
                    <div class="d-flex align-items-center gap-1 mt-1 text-danger" style="font-size:0.78rem">
                        <AlertCircle size={12} /> {printerError}
                    </div>
                {/if}
                {#if form.errors.printer_name}
                    <div class="invalid-feedback d-block">{form.errors.printer_name}</div>
                {/if}
                <div class="form-text">Haz clic en <strong>Detectar</strong> para listar las impresoras de este PC (requiere QZ Tray).</div>
            </div>

            <!-- Ancho de papel -->
            <div class="mb-3">
                <p class="form-label fw-semibold small mb-2">Ancho de papel</p>
                <div class="d-flex gap-2">
                    {#each ['58mm', '80mm'] as w}
                        <button
                            type="button"
                            class="btn btn-sm flex-fill {form.paper_width === w ? 'btn-taguara' : 'btn-outline-secondary'}"
                            onclick={() => (form.paper_width = w)}
                        >{w}</button>
                    {/each}
                </div>
            </div>

            <!-- Copias -->
            <div class="mb-3">
                <label class="form-label fw-semibold small" for="modal-copies">Copias por venta</label>
                <input
                    id="modal-copies"
                    class="form-control form-control-sm {form.errors.copies ? 'is-invalid' : ''}"
                    type="number"
                    min="1"
                    max="5"
                    bind:value={form.copies}
                    style="max-width:100px"
                />
                {#if form.errors.copies}
                    <div class="invalid-feedback">{form.errors.copies}</div>
                {/if}
            </div>

            <!-- Auto-print -->
            <div class="mb-4">
                <label class="form-label fw-semibold small d-flex align-items-center gap-2">
                    <Zap size={14} class="text-warning" />
                    Impresión automática
                </label>
                <div class="form-check form-switch">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        id="modal-auto-print"
                        bind:checked={form.auto_print}
                    />
                    <label class="form-check-label small" for="modal-auto-print">
                        {form.auto_print ? 'Activada — imprime al cerrar cada venta' : 'Desactivada — imprimir manualmente'}
                    </label>
                </div>
            </div>

            <!-- Resultado de prueba -->
            {#if testResult}
                <div class="alert {testResult.startsWith('ok') ? 'alert-success' : 'alert-danger'} small py-2 d-flex gap-2 mb-3">
                    {#if testResult.startsWith('ok')}
                        <CheckCircle2 size={14} class="flex-shrink-0 mt-1" />
                        Prueba enviada a <strong>{form.printer_name}</strong>.
                    {:else}
                        <AlertCircle size={14} class="flex-shrink-0 mt-1" />
                        {testResult.replace('error:', '')}
                    {/if}
                </div>
            {/if}
        </div>

        <div class="taguara-modal-footer">
            <button
                class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-2"
                type="button"
                onclick={printTest}
                disabled={printingTest || !form.printer_name}
            >
                {#if printingTest}
                    <Loader size={14} class="spin" />Imprimiendo...
                {:else}
                    <PrinterCheck size={14} />Imprimir prueba
                {/if}
            </button>

            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-light border" type="button" onclick={closeModal}>Cancelar</button>
                <button
                    class="btn btn-sm btn-taguara d-inline-flex align-items-center gap-2"
                    type="button"
                    onclick={submit}
                    disabled={form.processing}
                >
                    {#if form.processing}
                        <span class="spinner-border spinner-border-sm"></span>Guardando...
                    {:else}
                        <Save size={14} />Guardar
                    {/if}
                </button>
            </div>
        </div>
    </div>
{/if}

<style>
    :global(.spin) { animation: spin 1s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    .taguara-modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.45);
        z-index: 1040;
    }

    .taguara-modal {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 1050;
        width: min(520px, calc(100vw - 2rem));
        max-height: calc(100vh - 4rem);
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.18);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .taguara-modal-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--taguara-border);
    }

    .taguara-modal-body {
        padding: 1.25rem 1.5rem;
        overflow-y: auto;
        flex: 1;
    }

    .taguara-modal-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--taguara-border);
        background: #f8fafc;
    }
</style>
