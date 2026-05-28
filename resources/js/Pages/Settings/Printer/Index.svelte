<script>
    import { useForm } from '@inertiajs/svelte';
    import { AlertCircle, CheckCircle2, Printer, PrinterCheck, RefreshCw, Ruler, Save, Zap } from '@lucide/svelte';
    import AppLayout from '../../../Layouts/AppLayout.svelte';
    import PrinterStatus from '../../../Components/UI/PrinterStatus.svelte';
    import QzPrinter from '../../../Services/QzPrinter.js';

    let { auth, printerSettings } = $props();

    const form = useForm(() => ({
        printer_name: printerSettings.printer_name ?? '',
        paper_width:  printerSettings.paper_width  ?? '80mm',
        copies:       printerSettings.copies       ?? 1,
        auto_print:   printerSettings.auto_print   ?? false,
    }));

    // ── Detección de impresoras ───────────────────────────────────────────
    let printers = $state([]);
    let loadingPrinters = $state(false);
    let printerError = $state('');

    async function loadPrinters() {
        printerError    = '';
        loadingPrinters = true;
        try {
            if (!QzPrinter.connected) {
                await QzPrinter.connect();
            }
            printers = await QzPrinter.getPrinters();
        } catch (err) {
            printerError = err?.message ?? 'No se pudo obtener la lista de impresoras. ¿QZ Tray está corriendo?';
        } finally {
            loadingPrinters = false;
        }
    }

    // ── Impresión de prueba ───────────────────────────────────────────────
    let printingTest = $state(false);
    let testResult   = $state('');

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
                '\x1B@',                              // reset
                '\x1Ba\x01',                          // center
                '\x1BE\x01TAGUARA SYNC\x1BE\x00\n',  // bold
                'Prueba de impresion\n',
                `Ancho: ${form.paper_width}\n`,
                `Copias: ${form.copies}\n`,
                '--------------------------------\n',
                'Impresora configurada OK\n',
                '\n\n\n',
                '\x1DV\x42\x00',                      // corte
            ]);
            testResult = 'ok';
        } catch (err) {
            testResult = 'error:' + (err?.message ?? 'Error al imprimir.');
        } finally {
            printingTest = false;
        }
    }

    const submit = () => {
        form.put('/settings/printer');
    };
</script>

<AppLayout title="Configuración de impresora" activeSection="configuracion" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-secondary mb-2">Configuración</p>
                <h2 class="h3 mb-2">Impresora térmica</h2>
                <p class="text-secondary mb-0">
                    Configura la conexión con QZ Tray para imprimir recibos directamente sin diálogos del navegador.
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
                            <p class="text-secondary small mb-0">Usa el botón "Conectar" arriba, selecciona la impresora y guarda.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Formulario -->
        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Ajustes</p>
                    <h3 class="h5 mb-0">Configuración de la farmacia</h3>
                </div>
                <Ruler class="text-success" size={22} />
            </div>

            <div class="row g-3 mt-1">
                <!-- Selección de impresora -->
                <div class="col-12">
                    <label class="form-label fw-semibold" for="printer-name">
                        Impresora
                    </label>
                    <div class="d-flex gap-2">
                        {#if printers.length > 0}
                            <select
                                id="printer-name"
                                class="form-select {form.errors.printer_name ? 'is-invalid' : ''}"
                                bind:value={form.printer_name}
                            >
                                <option value="">— Sin impresora configurada —</option>
                                {#each printers as p}
                                    <option value={p}>{p}</option>
                                {/each}
                            </select>
                        {:else}
                            <input
                                id="printer-name"
                                class="form-control {form.errors.printer_name ? 'is-invalid' : ''}"
                                type="text"
                                bind:value={form.printer_name}
                                placeholder="Ej. EPSON TM-T20III"
                            />
                        {/if}
                        <button
                            class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 flex-shrink-0"
                            type="button"
                            onclick={loadPrinters}
                            disabled={loadingPrinters}
                        >
                            <RefreshCw size={15} class={loadingPrinters ? 'spin' : ''} />
                            {loadingPrinters ? 'Detectando...' : 'Detectar'}
                        </button>
                    </div>
                    {#if printerError}
                        <div class="d-flex align-items-center gap-1 mt-1 text-danger small">
                            <AlertCircle size={13} /> {printerError}
                        </div>
                    {/if}
                    {#if form.errors.printer_name}
                        <div class="invalid-feedback d-block">{form.errors.printer_name}</div>
                    {/if}
                    <div class="form-text">
                        Haz clic en <strong>Detectar</strong> para listar las impresoras instaladas en este PC (requiere QZ Tray conectado).
                    </div>
                </div>

                <!-- Ancho de papel -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Ancho de papel</label>
                    <div class="d-flex gap-2">
                        {#each ['58mm', '80mm'] as w}
                            <button
                                type="button"
                                class="btn flex-fill {form.paper_width === w ? 'btn-taguara' : 'btn-outline-secondary'}"
                                onclick={() => form.paper_width = w}
                            >
                                {w}
                            </button>
                        {/each}
                    </div>
                    <div class="form-text">58 mm es el más estrecho; 80 mm es el estándar.</div>
                </div>

                <!-- Copias -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold" for="copies">Copias por venta</label>
                    <input
                        id="copies"
                        class="form-control {form.errors.copies ? 'is-invalid' : ''}"
                        type="number"
                        min="1"
                        max="5"
                        bind:value={form.copies}
                    />
                    {#if form.errors.copies}
                        <div class="invalid-feedback">{form.errors.copies}</div>
                    {/if}
                </div>

                <!-- Auto-print -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold d-flex align-items-center gap-2">
                        <Zap size={15} class="text-warning" />
                        Impresión automática
                    </label>
                    <div class="form-check form-switch mt-1">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="auto-print"
                            bind:checked={form.auto_print}
                        />
                        <label class="form-check-label" for="auto-print">
                            {form.auto_print ? 'Activada — imprime al cerrar cada venta' : 'Desactivada — imprimir manualmente'}
                        </label>
                    </div>
                </div>
            </div>

            <!-- Prueba de impresión -->
            {#if testResult}
                <div class="alert {testResult.startsWith('ok') ? 'alert-success' : 'alert-danger'} small py-2 d-flex gap-2 mt-3">
                    {#if testResult.startsWith('ok')}
                        <CheckCircle2 size={15} class="flex-shrink-0 mt-1" />
                        Prueba enviada correctamente a <strong>{form.printer_name}</strong>.
                    {:else}
                        <AlertCircle size={15} class="flex-shrink-0 mt-1" />
                        {testResult.replace('error:', '')}
                    {/if}
                </div>
            {/if}

            <div class="d-flex gap-2 mt-4">
                <button
                    class="btn btn-outline-secondary d-inline-flex align-items-center gap-2"
                    type="button"
                    onclick={printTest}
                    disabled={printingTest || !form.printer_name}
                >
                    <PrinterCheck size={16} />
                    {printingTest ? 'Imprimiendo...' : 'Imprimir prueba'}
                </button>

                <button
                    class="btn btn-taguara d-inline-flex align-items-center gap-2"
                    type="button"
                    onclick={submit}
                    disabled={form.processing}
                >
                    {#if form.processing}
                        <span class="spinner-border spinner-border-sm"></span>
                        Guardando...
                    {:else}
                        <Save size={16} />
                        Guardar configuración
                    {/if}
                </button>
            </div>
        </section>
    </div>
</AppLayout>

<style>
    :global(.spin) { animation: spin 1s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
</style>
