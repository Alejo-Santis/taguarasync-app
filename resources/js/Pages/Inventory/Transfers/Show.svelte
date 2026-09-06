<script>
    import { router } from '@inertiajs/svelte';
    import { fade, fly } from 'svelte/transition';
    import { ArrowLeftRight, ArrowRight, CheckCircle, X, XCircle } from '@lucide/svelte';
    import AppLayout from '../../../Layouts/AppLayout.svelte';

    let { auth, transfer } = $props();

    let confirmAction = $state(null); // 'confirm' | 'cancel'
    let isProcessing = $state(false);

    const statusClass = (value) => {
        if (value === 'confirmed') return 'text-bg-success';
        if (value === 'cancelled') return 'text-bg-danger';
        return 'text-bg-secondary';
    };

    const openConfirm = (action) => { confirmAction = action; };
    const closeConfirm = () => { confirmAction = null; };

    const executeAction = () => {
        if (!confirmAction) return;
        const url = `/inventory/transfers/${transfer.uuid}/${confirmAction}`;
        isProcessing = true;
        router.post(url, {}, {
            onSuccess: closeConfirm,
            preserveScroll: true,
            onFinish: () => { isProcessing = false; },
        });
    };

    const handleKeydown = (e) => { if (e.key === 'Escape') closeConfirm(); };
</script>

<svelte:window onkeydown={handleKeydown} />

<AppLayout title="Traslado" activeSection="transfers" {auth}>
    <div class="taguara-products">
        <section class="taguara-command-band">
            <div>
                <p class="text-uppercase small fw-semibold text-success mb-2">Inventario</p>
                <h2 class="h3 mb-2 d-flex align-items-center gap-2">
                    Traslado
                    <span class={`badge ${statusClass(transfer.status.value)}`}>{transfer.status.label}</span>
                </h2>
                <p class="text-secondary mb-0">
                    <span class="fw-semibold">{transfer.from_branch.name}</span>
                    <ArrowRight size={15} class="mx-1" />
                    <span class="fw-semibold">{transfer.to_branch.name}</span>
                    &nbsp;&middot;&nbsp; {transfer.created_at}
                </p>
            </div>
            {#if transfer.status.value === 'draft'}
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-danger d-inline-flex align-items-center gap-2" type="button" onclick={() => openConfirm('cancel')}>
                        <XCircle size={17} />
                        Cancelar traslado
                    </button>
                    <button class="btn btn-taguara d-inline-flex align-items-center gap-2" type="button" onclick={() => openConfirm('confirm')}>
                        <CheckCircle size={17} />
                        Confirmar traslado
                    </button>
                </div>
            {/if}
        </section>

        <div class="row g-3">
            <!-- Cabecera -->
            <div class="col-12 col-md-4">
                <section class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Detalle</p>
                            <h3 class="h6 mb-0">Informacion del traslado</h3>
                        </div>
                    </div>
                    <dl class="row g-2 small mb-0">
                        <dt class="col-5 text-secondary">Estado</dt>
                        <dd class="col-7 mb-0">
                            <span class={`badge ${statusClass(transfer.status.value)}`}>{transfer.status.label}</span>
                        </dd>
                        <dt class="col-5 text-secondary">Origen</dt>
                        <dd class="col-7 mb-0 fw-semibold">{transfer.from_branch.name}</dd>
                        <dt class="col-5 text-secondary">Destino</dt>
                        <dd class="col-7 mb-0 fw-semibold">{transfer.to_branch.name}</dd>
                        <dt class="col-5 text-secondary">Creado por</dt>
                        <dd class="col-7 mb-0">{transfer.user_name ?? '—'}</dd>
                        <dt class="col-5 text-secondary">Creado</dt>
                        <dd class="col-7 mb-0">{transfer.created_at}</dd>
                        {#if transfer.confirmed_at}
                            <dt class="col-5 text-secondary">Confirmado</dt>
                            <dd class="col-7 mb-0">{transfer.confirmed_at}</dd>
                        {/if}
                        {#if transfer.notes}
                            <dt class="col-5 text-secondary">Notas</dt>
                            <dd class="col-7 mb-0">{transfer.notes}</dd>
                        {/if}
                    </dl>
                </section>
            </div>

            <!-- Items -->
            <div class="col-12 col-md-8">
                <section class="taguara-panel">
                    <div class="taguara-panel-header">
                        <div>
                            <p class="text-uppercase small fw-semibold text-success mb-1">Items</p>
                            <h3 class="h6 mb-0">{transfer.items.length} lotes incluidos</h3>
                        </div>
                        <ArrowLeftRight class="text-secondary" size={20} />
                    </div>

                    <div class="taguara-table-wrapper">
                        <table class="taguara-table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Lote</th>
                                    <th>Vence</th>
                                    <th class="text-end">Stock actual</th>
                                    <th class="text-end">Cantidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                {#each transfer.items as item}
                                    <tr>
                                        <td>
                                            <div class="taguara-table-name">{item.product_name ?? '—'}</div>
                                        </td>
                                        <td class="text-secondary" style="font-size:.875rem">{item.lot_number}</td>
                                        <td class="text-secondary" style="font-size:.875rem">{item.expires_on ?? 'Sin venc.'}</td>
                                        <td class="text-end text-secondary" style="font-size:.875rem">{item.current_quantity}</td>
                                        <td class="text-end fw-semibold">{item.quantity}</td>
                                    </tr>
                                {:else}
                                    <tr>
                                        <td colspan="5">
                                            <div class="taguara-empty-state">
                                                <ArrowLeftRight size={28} />
                                                <p class="text-secondary small mb-0">Sin items en este traslado.</p>
                                            </div>
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <!-- Modal de confirmacion de accion -->
    {#if confirmAction}
        <div class="taguara-drawer-backdrop" transition:fade={{ duration: 150 }} onclick={closeConfirm} role="presentation"></div>
        <div class="taguara-pos-modal" transition:fly={{ y: -20, duration: 180 }}>
            <div class="taguara-pos-modal-header">
                <div>
                    <p class="text-uppercase small fw-semibold {confirmAction === 'confirm' ? 'text-success' : 'text-danger'} mb-1">Traslado</p>
                    <h2 class="h5 mb-0">
                        {confirmAction === 'confirm' ? 'Confirmar traslado' : 'Cancelar traslado'}
                    </h2>
                </div>
                <button class="btn btn-light border taguara-icon-button" type="button" aria-label="Cerrar" onclick={closeConfirm} disabled={isProcessing}>
                    <X size={17} />
                </button>
            </div>
            <div class="taguara-pos-modal-body">
                {#if confirmAction === 'confirm'}
                    <p class="text-secondary small mb-4">
                        Al confirmar se registraran los movimientos de inventario: salida en
                        <strong>{transfer.from_branch.name}</strong> y entrada en
                        <strong>{transfer.to_branch.name}</strong>. Esta accion no se puede deshacer.
                    </p>
                    <div class="d-flex gap-2">
                        <button class="btn btn-light border flex-fill" type="button" onclick={closeConfirm} disabled={isProcessing}>Cancelar</button>
                        <button class="btn btn-taguara flex-fill d-inline-flex align-items-center justify-content-center gap-2" type="button" onclick={executeAction} disabled={isProcessing}>
                            {#if isProcessing}
                                <span class="spinner-border spinner-border-sm" role="status"></span>
                            {:else}
                                <CheckCircle size={16} />
                            {/if}
                            Confirmar traslado
                        </button>
                    </div>
                {:else}
                    <p class="text-secondary small mb-4">
                        El traslado quedara cancelado y no se podra reactivar. Los lotes no se modificaran.
                    </p>
                    <div class="d-flex gap-2">
                        <button class="btn btn-light border flex-fill" type="button" onclick={closeConfirm} disabled={isProcessing}>Volver</button>
                        <button class="btn btn-danger flex-fill d-inline-flex align-items-center justify-content-center gap-2" type="button" onclick={executeAction} disabled={isProcessing}>
                            {#if isProcessing}
                                <span class="spinner-border spinner-border-sm" role="status"></span>
                            {:else}
                                <XCircle size={16} />
                            {/if}
                            Cancelar traslado
                        </button>
                    </div>
                {/if}
            </div>
        </div>
    {/if}
</AppLayout>
