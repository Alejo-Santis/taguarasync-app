<script>
    import { Link, useForm } from '@inertiajs/svelte';
    import {
        AlertCircle,
        ArrowLeft,
        CheckCircle2,
        Download,
        FileSpreadsheet,
        Upload,
    } from '@lucide/svelte';
    import AppLayout from '../../Layouts/AppLayout.svelte';

    let { auth, importErrors = [] } = $props();

    const form = useForm({ file: null });

    const hasErrors = $derived(importErrors.length > 0);

    let fileInput = $state(null);
    let dragOver = $state(false);
    let selectedFileName = $state('');

    const handleFileChange = (event) => {
        const file = event.target.files?.[0];
        if (file) {
            form.file = file;
            selectedFileName = file.name;
        }
    };

    const handleDrop = (event) => {
        event.preventDefault();
        dragOver = false;
        const file = event.dataTransfer?.files?.[0];
        if (file) {
            form.file = file;
            selectedFileName = file.name;
        }
    };

    const submit = () => {
        form.post('/products/import', { forceFormData: true });
    };
</script>

<AppLayout title="Importar productos" activeSection="products" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Catalogo farmaceutico</p>
                <h2 class="h3 mb-2">Importar desde CSV</h2>
                <p class="text-secondary mb-0">Carga masiva de productos desde una hoja de calculo. Maximo 500 productos por archivo.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <Link class="btn btn-light border d-inline-flex align-items-center gap-2" href="/products">
                    <ArrowLeft size={18} />
                    Volver
                </Link>
            </div>
        </section>

        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <div class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Como importar</p>
                            <h3 class="h5 mb-0">Instrucciones</h3>
                        </div>
                    </div>
                    <ol class="ps-3 mb-0 vstack gap-3">
                        <li>
                            <span class="fw-semibold">Descarga la plantilla oficial</span>
                            <p class="text-secondary small mb-0">Contiene todas las columnas requeridas y una fila de ejemplo. No modifiques los encabezados.</p>
                        </li>
                        <li>
                            <span class="fw-semibold">Llena los datos en tu hoja de calculo</span>
                            <p class="text-secondary small mb-0">
                                Laboratorios y categorias deben existir previamente con el nombre exacto.
                                Usa los codigos de unidad registrados (ej: <code>und</code>, <code>caj</code>).
                                Escribe <code>si</code> o <code>no</code> para controlado.
                                Estado acepta: <code>activo</code>, <code>inactivo</code>, <code>descontinuado</code>.
                            </p>
                        </li>
                        <li>
                            <span class="fw-semibold">Guarda como CSV (separado por comas)</span>
                            <p class="text-secondary small mb-0">En Excel: Archivo → Guardar como → CSV UTF-8.</p>
                        </li>
                        <li>
                            <span class="fw-semibold">Sube el archivo e importa</span>
                            <p class="text-secondary small mb-0">El sistema valida todo antes de crear. Si hay errores, ningun producto se crea.</p>
                        </li>
                    </ol>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Plantilla</p>
                            <h3 class="h5 mb-0">Descargar</h3>
                        </div>
                        <FileSpreadsheet class="text-success" size={22} />
                    </div>
                    <p class="text-secondary small mb-3">Plantilla oficial con columnas requeridas y fila de ejemplo. Formato CSV UTF-8.</p>
                    <a
                        class="btn btn-taguara w-100 d-inline-flex align-items-center justify-content-center gap-2"
                        href="/products/import/template"
                    >
                        <Download size={17} />
                        Descargar plantilla
                    </a>
                </div>
            </div>
        </div>

        {#if hasErrors}
            <section class="taguara-panel">
                <div class="taguara-panel-header">
                    <div class="d-flex align-items-center gap-2">
                        <AlertCircle class="text-danger" size={20} />
                        <div>
                            <p class="text-uppercase small fw-semibold text-danger mb-1">Errores de validacion</p>
                            <h3 class="h5 mb-0">
                                {importErrors.length} {importErrors.length === 1 ? 'error encontrado' : 'errores encontrados'}
                            </h3>
                        </div>
                    </div>
                </div>
                <p class="text-secondary small mb-3">Corrige los errores en el CSV y vuelve a importar. Ningun producto fue creado.</p>
                <div class="taguara-table-wrapper">
                    <table class="taguara-table">
                        <thead>
                            <tr>
                                <th style="width: 72px">Fila</th>
                                <th>Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each importErrors as error}
                                <tr>
                                    <td>
                                        {#if error.row > 0}
                                            <span class="badge text-bg-danger">{error.row}</span>
                                        {:else}
                                            <span class="badge text-bg-secondary">Arch.</span>
                                        {/if}
                                    </td>
                                    <td class="text-secondary small">{error.message}</td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            </section>
        {/if}

        <section class="taguara-panel">
            <div class="taguara-panel-header">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Importar</p>
                    <h3 class="h5 mb-0">Subir archivo CSV</h3>
                </div>
                <Upload class="text-secondary" size={22} />
            </div>

            <form onsubmit={(e) => { e.preventDefault(); submit(); }}>
                <button
                    type="button"
                    class={`taguara-upload-zone${dragOver ? ' drag-over' : ''}${selectedFileName ? ' has-file' : ''}`}
                    aria-label="Zona de carga de archivo"
                    ondragover={(e) => { e.preventDefault(); dragOver = true; }}
                    ondragleave={() => { dragOver = false; }}
                    ondrop={handleDrop}
                    onclick={() => fileInput?.click()}
                >
                    {#if selectedFileName}
                        <CheckCircle2 class="text-success" size={28} />
                        <p class="fw-semibold mb-1">{selectedFileName}</p>
                        <p class="text-secondary small mb-0">Clic para cambiar el archivo</p>
                    {:else}
                        <Upload class="text-secondary" size={28} />
                        <p class="fw-semibold mb-1">Arrastra el CSV aqui o haz clic para seleccionar</p>
                        <p class="text-secondary small mb-0">Formato CSV UTF-8, maximo 2 MB, hasta 500 productos</p>
                    {/if}
                </button>

                <input
                    bind:this={fileInput}
                    type="file"
                    accept=".csv,text/csv"
                    class="d-none"
                    onchange={handleFileChange}
                />

                {#if form.errors.file}
                    <div class="alert alert-danger mt-2 mb-0">{form.errors.file}</div>
                {/if}

                <div class="d-flex justify-content-end mt-3">
                    <button
                        class="btn btn-taguara d-inline-flex align-items-center gap-2"
                        type="submit"
                        disabled={form.processing || !selectedFileName}
                    >
                        <Upload size={17} />
                        {form.processing ? 'Importando...' : 'Importar productos'}
                    </button>
                </div>
            </form>
        </section>
    </div>
</AppLayout>
