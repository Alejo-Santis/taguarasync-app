<script>
    import { Link, router, useForm } from '@inertiajs/svelte';
    import { fade, fly } from 'svelte/transition';
    import {
        AlertCircle,
        Building2,
        CalendarClock,
        Eye,
        EyeOff,
        FileText,
        Hash,
        Pencil,
        Plus,
        Receipt,
        Shield,
        ToggleLeft,
        ToggleRight,
        Wifi,
        WifiOff,
        X,
    } from '@lucide/svelte';
    import AppLayout from '../../../Layouts/AppLayout.svelte';
    import SettingsNav from '../../../Components/Settings/SettingsNav.svelte';
    import CopyButton from '../../../Components/UI/CopyButton.svelte';

    let { auth, tenant, fe_config, resolutions, options } = $props();
    // ── Algoritmo DV colombiano (DIAN) ─────────────────────────────────────────
    const calculateDv = (nit) => {
        const primes = [71, 67, 59, 53, 47, 43, 41, 37, 29, 23, 19, 17, 13, 7, 3];
        const digits = String(nit ?? '').replace(/\D/g, '');
        if (!digits || digits.length > 15) return '';
        let sum = 0;
        const offset = primes.length - digits.length;
        for (let i = 0; i < digits.length; i++) {
            sum += parseInt(digits[i]) * primes[offset + i];
        }
        const r = sum % 11;
        return String(r === 0 || r === 1 ? r : 11 - r);
    };

    // ── Formulario unificado (tenant básico + fe_config) ──────────────────────
    const fiscalForm = useForm(() => ({
        // Datos básicos del tenant
        name: tenant.name ?? '',
        legal_name: tenant.legal_name ?? '',
        nit: tenant.nit ?? '',
        merchant_registration: tenant.merchant_registration ?? '',
        email: tenant.email ?? '',
        phone: tenant.phone ?? '',
        address: tenant.address ?? '',
        city: tenant.city ?? '',
        department: tenant.department ?? '',
        municipality_code: tenant.municipality_code ?? '',

        // Configuración FE (va a tenant_fe_configs)
        electronic_invoicing_enabled: fe_config.electronic_invoicing_enabled ?? false,
        identification_type_code: fe_config.identification_type_code ?? '',
        organization_type_code: fe_config.organization_type_code ?? '',
        regime_type_code: fe_config.regime_type_code ?? '',
        fiscal_responsibilities: fe_config.fiscal_responsibilities ?? [],
        economic_activity_code: fe_config.economic_activity_code ?? '',
        environment: fe_config.environment ?? 'test',
        api_token: '',
        software_id: fe_config.software_id ?? '',
    }));

    // DV reactivo
    const dv = $derived(calculateDv(fiscalForm.nit));

    const saveFiscal = () => {
        fiscalForm.put('/settings/fe', { preserveScroll: true });
    };

    let connectionCheck = $state(null);
    let connectionChecking = $state(false);
    let showStoredToken = $state(false);
    let showEditableToken = $state(false);

    const getCsrfToken = () => {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : '';
    };

    const testConnection = async () => {
        connectionChecking = true;
        connectionCheck = null;

        try {
            const response = await fetch(options.routes?.test_connection ?? '/settings/fe/test-connection', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ api_token: fiscalForm.api_token || null }),
            });

            const contentType = response.headers.get('content-type') ?? '';
            if (contentType.includes('application/json')) {
                connectionCheck = await response.json();
            } else {
                connectionCheck = {
                    ok: false,
                    message: response.status === 419
                        ? 'La sesión expiró. Recarga la página e intenta de nuevo.'
                        : `No se pudo leer la respuesta del servidor (HTTP ${response.status}).`,
                    status_code: response.status,
                    checked_at: new Date().toLocaleString(),
                };
            }
        } catch (error) {
            connectionCheck = {
                ok: false,
                message: 'No se pudo ejecutar la prueba de conexión.',
                checked_at: new Date().toLocaleString(),
            };
        } finally {
            connectionChecking = false;
        }
    };

    const onMunicipalityChange = (code) => {
        fiscalForm.municipality_code = code;
        const m = options.municipalities.find((m) => m.code === code);
        if (m) {
            fiscalForm.city = m.name;
            fiscalForm.department = m.department_name;
        }
    };

    // Derive municipality API ID for display (auto-resolved via dian_municipalities.api_id)
    const selectedMunicipalityApiId = $derived(
        options.municipalities.find((m) => m.code === fiscalForm.municipality_code)?.api_id ?? null
    );

    const onResponsibilitiesChange = (e) => {
        fiscalForm.fiscal_responsibilities = Array.from(e.target.selectedOptions).map((o) => o.value);
    };

    // ── Resoluciones DIAN ─────────────────────────────────────────────────────
    let drawerOpen = $state(false);
    let editingResolution = $state(null);

    const resolutionForm = useForm(() => ({
        code: '',
        type: 'invoice',
        prefix: '',
        resolution_number: '',
        resolution_date: '',
        technical_key: '',
        from_number: '',
        to_number: '',
        valid_from: '',
        valid_until: '',
        environment: fe_config.environment ?? 'test',
        next_document_number: '',
    }));

    $effect(() => {
        document.body.style.overflow = drawerOpen ? 'hidden' : '';
        return () => { document.body.style.overflow = ''; };
    });

    const handleKeydown = (e) => { if (e.key === 'Escape') closeDrawer(); };

    const openCreate = () => {
        editingResolution = null;
        resolutionForm.reset();
        resolutionForm.environment = fiscalForm.environment;
        drawerOpen = true;
    };

    const openEdit = (item) => {
        editingResolution = item;
        resolutionForm.code = item.code ?? '';
        resolutionForm.type = item.type;
        resolutionForm.prefix = item.prefix ?? '';
        resolutionForm.resolution_number = item.resolution_number;
        resolutionForm.resolution_date = item.resolution_date;
        resolutionForm.technical_key = item.technical_key;
        resolutionForm.from_number = item.from_number;
        resolutionForm.to_number = item.to_number;
        resolutionForm.next_document_number = item.next_document_number;
        resolutionForm.valid_from = item.valid_from;
        resolutionForm.valid_until = item.valid_until;
        resolutionForm.environment = item.environment;
        drawerOpen = true;
    };

    const closeDrawer = () => {
        drawerOpen = false;
        editingResolution = null;
        resolutionForm.reset();
        resolutionForm.clearErrors();
    };

    const saveResolution = () => {
        if (editingResolution) {
            resolutionForm.put(`/settings/fe/resolutions/${editingResolution.id}`, {
                onSuccess: closeDrawer,
                preserveScroll: true,
            });
        } else {
            resolutionForm.post('/settings/fe/resolutions', {
                onSuccess: closeDrawer,
                preserveScroll: true,
            });
        }
    };

    const toggleResolution = (item) => {
        router.patch(`/settings/fe/resolutions/${item.id}/toggle`, {}, { preserveScroll: true });
    };

    const resolutionTypeLabel = (type) =>
        options.resolution_types.find((t) => t.value === type)?.label ?? type;

    const environmentBadgeClass = (env) =>
        env === 'production' ? 'text-bg-success' : 'text-bg-warning text-dark';
</script>

<svelte:window onkeydown={handleKeydown} />

<AppLayout title="Facturación electrónica" activeSection="configuracion" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Configuracion</p>
                <h2 class="h3 mb-2">Facturación electrónica</h2>
                <p class="text-secondary mb-0">Configura los datos de la empresa y resoluciones DIAN para emitir facturas electrónicas.</p>
            </div>
            <Receipt class="text-secondary" size={22} />
        </section>

        <SettingsNav active="fe" />

        <!-- Datos de la empresa -->
        <section class="taguara-panel">
                <div class="taguara-panel-header align-items-start">
                    <div>
                        <p class="text-uppercase small fw-semibold text-success mb-1">Empresa</p>
                        <h3 class="h5 mb-0">Datos generales</h3>
                    </div>
                    <Building2 class="text-secondary flex-shrink-0" size={20} />
                </div>

                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label" for="name">Nombre comercial <span class="text-danger">*</span></label>
                        <input
                            id="name"
                            class="form-control form-control-sm"
                            class:is-invalid={fiscalForm.errors.name}
                            type="text"
                            placeholder="Farmacia La Salud"
                            bind:value={fiscalForm.name}
                        />
                        {#if fiscalForm.errors.name}
                            <div class="invalid-feedback">{fiscalForm.errors.name}</div>
                        {/if}
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="legal-name">Razón social</label>
                        <input
                            id="legal-name"
                            class="form-control form-control-sm"
                            class:is-invalid={fiscalForm.errors.legal_name}
                            type="text"
                            placeholder="Farmacia La Salud S.A.S."
                            bind:value={fiscalForm.legal_name}
                        />
                        {#if fiscalForm.errors.legal_name}
                            <div class="invalid-feedback">{fiscalForm.errors.legal_name}</div>
                        {/if}
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="nit">NIT</label>
                        <div class="input-group input-group-sm">
                            <input
                                id="nit"
                                class="form-control"
                                class:is-invalid={fiscalForm.errors.nit}
                                type="text"
                                placeholder="901234567"
                                bind:value={fiscalForm.nit}
                            />
                            {#if dv !== ''}
                                <span class="input-group-text fw-bold text-success" title="DV calculado">-{dv}</span>
                            {/if}
                        </div>
                        {#if fiscalForm.errors.nit}
                            <div class="text-danger" style="font-size:.8rem">{fiscalForm.errors.nit}</div>
                        {/if}
                    </div>

                    <div class="col-md-2">
                        <label class="form-label" for="merchant-reg">Reg. mercantil</label>
                        <input
                            id="merchant-reg"
                            class="form-control form-control-sm"
                            type="text"
                            maxlength="30"
                            placeholder="0000000-00"
                            bind:value={fiscalForm.merchant_registration}
                        />
                        {#if fiscalForm.errors.merchant_registration}
                            <div class="invalid-feedback">{fiscalForm.errors.merchant_registration}</div>
                        {/if}
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="company-email">Correo</label>
                        <input
                            id="company-email"
                            class="form-control form-control-sm"
                            class:is-invalid={fiscalForm.errors.email}
                            type="email"
                            placeholder="contacto@farmacia.com"
                            bind:value={fiscalForm.email}
                        />
                        {#if fiscalForm.errors.email}
                            <div class="invalid-feedback">{fiscalForm.errors.email}</div>
                        {/if}
                    </div>

                    <div class="col-md-2">
                        <label class="form-label" for="company-phone">Teléfono</label>
                        <input id="company-phone" class="form-control form-control-sm" type="text" placeholder="6017654321" bind:value={fiscalForm.phone} />
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="company-address">Dirección</label>
                        <input id="company-address" class="form-control form-control-sm" type="text" placeholder="Calle 72 # 10-34" bind:value={fiscalForm.address} />
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="company-municipality">Municipio</label>
                        <select
                            id="company-municipality"
                            class="form-select form-select-sm"
                            value={fiscalForm.municipality_code}
                            onchange={(e) => onMunicipalityChange(e.target.value)}
                        >
                            <option value="">Seleccionar...</option>
                            {#each options.municipalities as m}
                                <option value={m.code}>{m.department_name} – {m.name}</option>
                            {/each}
                        </select>
                        {#if fiscalForm.city}
                            <div class="form-text">
                                {fiscalForm.department}
                                {#if selectedMunicipalityApiId}
                                    · <span class="text-success">ID Nextpyme: {selectedMunicipalityApiId} ✓</span>
                                {/if}
                            </div>
                        {/if}
                    </div>
                </div>
        </section>

        <!-- Datos fiscales DIAN -->
        <section class="taguara-panel">
                <div class="taguara-panel-header align-items-start">
                    <div>
                        <p class="text-uppercase small fw-semibold text-success mb-1">Información fiscal</p>
                        <h3 class="h5 mb-0">Clasificación DIAN</h3>
                    </div>
                    <Shield class="text-secondary flex-shrink-0" size={20} />
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="id-type">Tipo de documento de identificación</label>
                        <select
                            id="id-type"
                            class="form-select"
                            class:is-invalid={fiscalForm.errors.identification_type_code}
                            bind:value={fiscalForm.identification_type_code}
                        >
                            <option value="">Seleccionar...</option>
                            {#each options.identification_types as t}
                                <option value={t.code}>{t.code} – {t.name}</option>
                            {/each}
                        </select>
                        {#if fiscalForm.errors.identification_type_code}
                            <div class="invalid-feedback">{fiscalForm.errors.identification_type_code}</div>
                        {/if}
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="org-type">Tipo de organización</label>
                        <select
                            id="org-type"
                            class="form-select"
                            class:is-invalid={fiscalForm.errors.organization_type_code}
                            bind:value={fiscalForm.organization_type_code}
                        >
                            <option value="">Seleccionar...</option>
                            {#each options.organization_types as t}
                                <option value={t.code}>{t.name}</option>
                            {/each}
                        </select>
                        {#if fiscalForm.errors.organization_type_code}
                            <div class="invalid-feedback">{fiscalForm.errors.organization_type_code}</div>
                        {/if}
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="regime">Régimen tributario</label>
                        <select
                            id="regime"
                            class="form-select"
                            class:is-invalid={fiscalForm.errors.regime_type_code}
                            bind:value={fiscalForm.regime_type_code}
                        >
                            <option value="">Seleccionar...</option>
                            {#each options.regime_types as t}
                                <option value={t.code}>{t.name}</option>
                            {/each}
                        </select>
                        {#if fiscalForm.errors.regime_type_code}
                            <div class="invalid-feedback">{fiscalForm.errors.regime_type_code}</div>
                        {/if}
                    </div>

                    <div class="col-md-5">
                        <label class="form-label" for="ciiu">Actividad económica (CIIU)</label>
                        <select
                            id="ciiu"
                            class="form-select"
                            class:is-invalid={fiscalForm.errors.economic_activity_code}
                            bind:value={fiscalForm.economic_activity_code}
                        >
                            <option value="">Seleccionar...</option>
                            {#each options.economic_activities as a}
                                <option value={a.code}>{a.code} – {a.name}</option>
                            {/each}
                        </select>
                        {#if fiscalForm.errors.economic_activity_code}
                            <div class="invalid-feedback">{fiscalForm.errors.economic_activity_code}</div>
                        {/if}
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="fe-env">Ambiente FE</label>
                        <select
                            id="fe-env"
                            class="form-select"
                            class:is-invalid={fiscalForm.errors.environment}
                            bind:value={fiscalForm.environment}
                        >
                            {#each options.environments as env}
                                <option value={env.value}>{env.label}</option>
                            {/each}
                        </select>
                        {#if fiscalForm.errors.environment}
                            <div class="invalid-feedback">{fiscalForm.errors.environment}</div>
                        {/if}
                    </div>

                    <div class="col-12">
                        {#if fe_config.api_token_value}
                            <div class="taguara-token-preview mb-3">
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                    <div>
                                        <p class="small fw-semibold mb-1">Token actual en uso</p>
                                        <p class="text-secondary small mb-0">
                                            Origen: {fe_config.api_token_source === 'tenant' ? 'configuración de la farmacia' : 'token global del servidor'}
                                        </p>
                                    </div>
                                    <div class="d-flex align-items-center gap-1">
                                        <CopyButton text={fe_config.api_token_value} label="Copiar token" />
                                        <button
                                            class="btn btn-sm btn-light border taguara-icon-button-sm"
                                            type="button"
                                            aria-label={showStoredToken ? 'Ocultar token actual' : 'Ver token actual'}
                                            onclick={() => showStoredToken = !showStoredToken}
                                        >
                                            {#if showStoredToken}<EyeOff size={15} />{:else}<Eye size={15} />{/if}
                                        </button>
                                    </div>
                                </div>
                                <input
                                    class="form-control font-monospace"
                                    type={showStoredToken ? 'text' : 'password'}
                                    value={fe_config.api_token_value}
                                    readonly
                                />
                            </div>
                        {/if}

                        <label class="form-label" for="fe-api-token">
                            Token API Nextpyme
                            {#if fe_config.api_token_set}
                                <span class="badge text-bg-success ms-1" style="font-size:.7rem">Configurado</span>
                            {:else}
                                <span class="badge text-bg-warning text-dark ms-1" style="font-size:.7rem">No configurado</span>
                            {/if}
                        </label>
                        <div class="taguara-token-input">
                            <input
                                id="fe-api-token"
                                class="form-control font-monospace"
                                class:is-invalid={fiscalForm.errors.api_token}
                                type={showEditableToken ? 'text' : 'password'}
                                placeholder={fe_config.api_token_set ? 'Dejar vacío para mantener el token actual' : 'Pegar el Bearer token de tu cuenta Nextpyme'}
                                autocomplete="off"
                                bind:value={fiscalForm.api_token}
                            />
                            <button
                                class="btn btn-light border taguara-token-eye"
                                type="button"
                                aria-label={showEditableToken ? 'Ocultar token nuevo' : 'Ver token nuevo'}
                                onclick={() => showEditableToken = !showEditableToken}
                            >
                                {#if showEditableToken}<EyeOff size={16} />{:else}<Eye size={16} />{/if}
                            </button>
                        </div>
                        {#if fiscalForm.errors.api_token}
                            <div class="invalid-feedback">{fiscalForm.errors.api_token}</div>
                        {:else}
                            <div class="form-text">
                                Se guarda cifrado. Si dejas este campo vacío, se usa el token global del servidor (FE_API_TOKEN en .env).
                            </div>
                        {/if}
                    </div>

                    <div class="col-12">
                        <div class="taguara-fe-connection">
                            <div class="d-flex align-items-start gap-3">
                                <span class={`taguara-fe-connection-icon ${connectionCheck?.ok ? 'is-ok' : connectionCheck ? 'is-down' : ''}`}>
                                    {#if connectionCheck?.ok}
                                        <Wifi size={18} />
                                    {:else}
                                        <WifiOff size={18} />
                                    {/if}
                                </span>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                                        <div>
                                            <p class="fw-semibold mb-1">Estado API Nextpyme</p>
                                            <p class="text-secondary small mb-0">
                                                {connectionCheck?.message ?? 'Prueba la conexión con el token guardado o con el token temporal escrito arriba.'}
                                            </p>
                                        </div>
                                        <button class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-2" type="button" onclick={testConnection} disabled={connectionChecking}>
                                            {#if connectionChecking}
                                                <span class="spinner-border spinner-border-sm"></span>
                                                Probando...
                                            {:else}
                                                <Wifi size={15} />
                                                Probar conexión
                                            {/if}
                                        </button>
                                    </div>

                                    {#if connectionCheck}
                                        <div class="taguara-fe-connection-grid">
                                            <span>
                                                <strong>{connectionCheck.ok ? 'Online' : 'Con error'}</strong>
                                                <small>Resultado</small>
                                            </span>
                                            <span>
                                                <strong>{connectionCheck.status_code ?? 'N/D'}</strong>
                                                <small>HTTP</small>
                                            </span>
                                            <span>
                                                <strong>{connectionCheck.duration_ms ?? 0} ms</strong>
                                                <small>Tiempo</small>
                                            </span>
                                            <span>
                                                <strong>{connectionCheck.token_source ?? 'N/D'}</strong>
                                                <small>Token</small>
                                            </span>
                                        </div>
                                        {#if connectionCheck.company?.name || connectionCheck.company?.identification_number}
                                            <div class="small text-secondary mt-2">
                                                Empresa: <strong>{connectionCheck.company.name ?? 'Sin nombre'}</strong>
                                                {#if connectionCheck.company.identification_number}
                                                    · NIT {connectionCheck.company.identification_number}
                                                {/if}
                                            </div>
                                        {/if}
                                    {/if}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="fiscal-resp">
                            Responsabilidades fiscales
                            <span class="text-secondary fw-normal" style="font-size:.8rem">(Ctrl+clic para seleccionar varias)</span>
                        </label>
                        <select
                            id="fiscal-resp"
                            class="form-select"
                            class:is-invalid={fiscalForm.errors.fiscal_responsibilities}
                            multiple
                            size="6"
                            onchange={onResponsibilitiesChange}
                        >
                            {#each options.fiscal_responsibilities as r}
                                <option
                                    value={r.code}
                                    selected={fiscalForm.fiscal_responsibilities.includes(r.code)}
                                >
                                    {r.code} – {r.name}
                                </option>
                            {/each}
                        </select>
                        {#if fiscalForm.errors.fiscal_responsibilities}
                            <div class="invalid-feedback">{fiscalForm.errors.fiscal_responsibilities}</div>
                        {/if}
                    </div>
                </div>
        </section>

        <div class="d-flex justify-content-end">
            <button class="btn btn-taguara px-5" type="button" onclick={saveFiscal} disabled={fiscalForm.processing}>
                {fiscalForm.processing ? 'Guardando...' : 'Guardar configuración'}
            </button>
        </div>

        <!-- Resoluciones DIAN -->
        <section class="taguara-panel">
            <div class="taguara-panel-header align-items-start">
                <div>
                    <p class="text-uppercase small fw-semibold text-success mb-1">Resoluciones DIAN</p>
                    <h3 class="h5 mb-0">{resolutions.length} resolución{resolutions.length !== 1 ? 'es' : ''} registrada{resolutions.length !== 1 ? 's' : ''}</h3>
                </div>
                <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="button" onclick={openCreate}>
                    <Plus size={17} />
                    Nueva resolución
                </button>
            </div>

            <div class="taguara-table-wrapper mt-2">
                <table class="taguara-table">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Prefijo / Resolución</th>
                            <th>Rango</th>
                            <th>Vigencia</th>
                            <th>Ambiente</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each resolutions as item}
                            <tr onclick={() => openEdit(item)}>
                                <td>
                                    <span class="taguara-table-name">{item.type_label}</span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        {#if item.prefix}
                                            <span class="badge text-bg-light border fw-semibold" style="font-size:.8rem">{item.prefix}</span>
                                        {/if}
                                        <span class="text-secondary" style="font-size:.875rem">{item.resolution_number}</span>
                                        <CopyButton text={item.resolution_number} label="Copiar número de resolución" />
                                    </div>
                                    <div class="taguara-table-sub">{item.resolution_date}</div>
                                </td>
                                <td>
                                    <div style="font-size:.875rem">
                                        <Hash size={12} class="text-secondary me-1" />{item.from_number.toLocaleString()} – {item.to_number.toLocaleString()}
                                    </div>
                                    <div class="taguara-table-sub">Actual: {item.current_number.toLocaleString()}</div>
                                    <div class="taguara-table-sub">Siguiente: {item.next_document_number.toLocaleString()}</div>
                                </td>
                                <td>
                                    <div style="font-size:.875rem">{item.valid_from} → {item.valid_until}</div>
                                    {#if item.is_expired}
                                        <div class="d-flex align-items-center gap-1 text-danger" style="font-size:.8rem">
                                            <AlertCircle size={11} /> Vencida
                                        </div>
                                    {:else}
                                        <div class="d-flex align-items-center gap-1 text-success" style="font-size:.8rem">
                                            <CalendarClock size={11} /> Vigente
                                        </div>
                                    {/if}
                                </td>
                                <td>
                                    <span class="badge {environmentBadgeClass(item.environment)}">{item.environment_label}</span>
                                </td>
                                <td>
                                    <span class="badge {item.is_active ? 'text-bg-success' : 'text-bg-secondary'}">
                                        {item.is_active ? 'Activa' : 'Inactiva'}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-1 justify-content-end">
                                        <button
                                            class="btn btn-sm btn-light border taguara-icon-button-sm"
                                            type="button"
                                            aria-label={item.is_active ? 'Desactivar' : 'Activar'}
                                            title={item.is_active ? 'Desactivar' : 'Activar'}
                                            onclick={(e) => { e.stopPropagation(); toggleResolution(item); }}
                                        >
                                            {#if item.is_active}
                                                <ToggleRight size={15} class="text-success" />
                                            {:else}
                                                <ToggleLeft size={15} class="text-secondary" />
                                            {/if}
                                        </button>
                                        <button
                                            class="btn btn-sm btn-light border taguara-icon-button-sm"
                                            type="button"
                                            aria-label="Editar"
                                            onclick={(e) => { e.stopPropagation(); openEdit(item); }}
                                        >
                                            <Pencil size={15} />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="7">
                                    <div class="taguara-empty-state">
                                        <FileText size={34} />
                                        <h4 class="h6 mb-1">Sin resoluciones registradas</h4>
                                        <p class="text-secondary mb-0">Agrega la resolución DIAN que autoriza tu numeración de facturas.</p>
                                    </div>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>

        </section>
    </div>

    <!-- Drawer resoluciones -->
    {#if drawerOpen}
        <div class="taguara-drawer-backdrop" transition:fade={{ duration: 150 }} onclick={closeDrawer} role="presentation"></div>
        <aside
            class="taguara-drawer"
            transition:fly={{ x: 480, duration: 220 }}
            aria-label={editingResolution ? 'Editar resolución' : 'Nueva resolución'}
        >
            <div class="taguara-drawer-header">
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <div>
                        <p class="text-uppercase small fw-semibold text-success mb-1">Resolución DIAN</p>
                        <h2 class="h5 mb-0">
                            {editingResolution ? resolutionTypeLabel(editingResolution.type) : 'Nueva resolución'}
                        </h2>
                    </div>
                    <button class="btn btn-light border taguara-icon-button flex-shrink-0" type="button" onclick={closeDrawer} aria-label="Cerrar">
                        <X size={17} />
                    </button>
                </div>
            </div>

            <div class="taguara-drawer-body">
                <form id="resolution-form" onsubmit={(e) => { e.preventDefault(); saveResolution(); }}>
                    <div class="vstack gap-3">

                        <div>
                            <label class="form-label" for="res-code">Código interno <span class="text-danger">*</span></label>
                            <input
                                id="res-code"
                                class="form-control font-monospace"
                                class:is-invalid={resolutionForm.errors.code}
                                type="text"
                                maxlength="30"
                                placeholder="FV-2024-PROD"
                                bind:value={resolutionForm.code}
                            />
                            {#if resolutionForm.errors.code}
                                <div class="invalid-feedback">{resolutionForm.errors.code}</div>
                            {:else}
                                <div class="form-text">Identificador único para búsquedas internas. Solo letras, números y guiones.</div>
                            {/if}
                        </div>

                        <div class="row g-3">
                            <div class="col-8">
                                <label class="form-label" for="res-type">Tipo de documento <span class="text-danger">*</span></label>
                                <select
                                    id="res-type"
                                    class="form-select"
                                    class:is-invalid={resolutionForm.errors.type}
                                    bind:value={resolutionForm.type}
                                >
                                    {#each options.resolution_types as t}
                                        <option value={t.value}>{t.label}</option>
                                    {/each}
                                </select>
                                {#if resolutionForm.errors.type}
                                    <div class="invalid-feedback">{resolutionForm.errors.type}</div>
                                {/if}
                            </div>
                            <div class="col-4">
                                <label class="form-label" for="res-prefix">Prefijo</label>
                                <input id="res-prefix" class="form-control" type="text" maxlength="10" placeholder="FV" bind:value={resolutionForm.prefix} />
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-8">
                                <label class="form-label" for="res-number">Número de resolución <span class="text-danger">*</span></label>
                                <input
                                    id="res-number"
                                    class="form-control"
                                    class:is-invalid={resolutionForm.errors.resolution_number}
                                    type="text"
                                    placeholder="18760000001"
                                    bind:value={resolutionForm.resolution_number}
                                />
                                {#if resolutionForm.errors.resolution_number}
                                    <div class="invalid-feedback">{resolutionForm.errors.resolution_number}</div>
                                {/if}
                            </div>
                            <div class="col-4">
                                <label class="form-label" for="res-date">Fecha <span class="text-danger">*</span></label>
                                <input
                                    id="res-date"
                                    class="form-control"
                                    class:is-invalid={resolutionForm.errors.resolution_date}
                                    type="date"
                                    bind:value={resolutionForm.resolution_date}
                                />
                                {#if resolutionForm.errors.resolution_date}
                                    <div class="invalid-feedback">{resolutionForm.errors.resolution_date}</div>
                                {/if}
                            </div>
                        </div>

                        <div>
                            <label class="form-label" for="res-key">Clave técnica <span class="text-danger">*</span></label>
                            <input
                                id="res-key"
                                class="form-control font-monospace"
                                class:is-invalid={resolutionForm.errors.technical_key}
                                type="text"
                                placeholder="fc8eac422eba16e22ffd..."
                                bind:value={resolutionForm.technical_key}
                            />
                            {#if resolutionForm.errors.technical_key}
                                <div class="invalid-feedback">{resolutionForm.errors.technical_key}</div>
                            {/if}
                        </div>

                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label" for="res-from">Número inicial <span class="text-danger">*</span></label>
                                <input
                                    id="res-from"
                                    class="form-control"
                                    class:is-invalid={resolutionForm.errors.from_number}
                                    type="number"
                                    min="1"
                                    placeholder="1"
                                    bind:value={resolutionForm.from_number}
                                />
                                {#if resolutionForm.errors.from_number}
                                    <div class="invalid-feedback">{resolutionForm.errors.from_number}</div>
                                {/if}
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="res-to">Número final <span class="text-danger">*</span></label>
                                <input
                                    id="res-to"
                                    class="form-control"
                                    class:is-invalid={resolutionForm.errors.to_number}
                                    type="number"
                                    min="1"
                                    placeholder="5000"
                                    bind:value={resolutionForm.to_number}
                                />
                                {#if resolutionForm.errors.to_number}
                                    <div class="invalid-feedback">{resolutionForm.errors.to_number}</div>
                                {/if}
                            </div>
                        </div>

                        <div>
                            <label class="form-label" for="res-next">Siguiente consecutivo a emitir</label>
                            <input
                                id="res-next"
                                class="form-control"
                                class:is-invalid={resolutionForm.errors.next_document_number}
                                type="number"
                                min={resolutionForm.from_number || 1}
                                max={resolutionForm.to_number || undefined}
                                placeholder={resolutionForm.from_number || '1'}
                                bind:value={resolutionForm.next_document_number}
                            />
                            {#if resolutionForm.errors.next_document_number}
                                <div class="invalid-feedback">{resolutionForm.errors.next_document_number}</div>
                            {:else}
                                <div class="form-text">Úsalo cuando la resolución ya viene avanzada. Si escribes 1520, la próxima factura será el prefijo + 1520.</div>
                            {/if}
                        </div>

                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label" for="res-valid-from">Vigencia desde <span class="text-danger">*</span></label>
                                <input
                                    id="res-valid-from"
                                    class="form-control"
                                    class:is-invalid={resolutionForm.errors.valid_from}
                                    type="date"
                                    bind:value={resolutionForm.valid_from}
                                />
                                {#if resolutionForm.errors.valid_from}
                                    <div class="invalid-feedback">{resolutionForm.errors.valid_from}</div>
                                {/if}
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="res-valid-until">Vigencia hasta <span class="text-danger">*</span></label>
                                <input
                                    id="res-valid-until"
                                    class="form-control"
                                    class:is-invalid={resolutionForm.errors.valid_until}
                                    type="date"
                                    bind:value={resolutionForm.valid_until}
                                />
                                {#if resolutionForm.errors.valid_until}
                                    <div class="invalid-feedback">{resolutionForm.errors.valid_until}</div>
                                {/if}
                            </div>
                        </div>

                        <div>
                            <label class="form-label" for="res-env">Ambiente</label>
                            <select id="res-env" class="form-select" bind:value={resolutionForm.environment}>
                                {#each options.environments as env}
                                    <option value={env.value}>{env.label}</option>
                                {/each}
                            </select>
                        </div>

                    </div>
                </form>
            </div>

            <div class="taguara-drawer-footer">
                <button class="btn btn-taguara w-100" type="submit" form="resolution-form" disabled={resolutionForm.processing}>
                    {resolutionForm.processing ? 'Guardando...' : (editingResolution ? 'Actualizar resolución' : 'Crear resolución')}
                </button>
            </div>
        </aside>
    {/if}
</AppLayout>
