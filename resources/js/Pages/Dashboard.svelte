<script>
    import AppLayout from '../Layouts/AppLayout.svelte';

    let { auth } = $props();
</script>

<AppLayout title="Panel principal" {auth}>
    <div class="row g-3">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="bg-white border rounded-2 p-3 h-100">
                <p class="text-secondary small mb-1">Fase actual</p>
                <p class="h5 mb-0">Base del sistema</p>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="bg-white border rounded-2 p-3 h-100">
                <p class="text-secondary small mb-1">Arquitectura</p>
                <p class="h5 mb-0">Multi-tenant</p>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="bg-white border rounded-2 p-3 h-100">
                <p class="text-secondary small mb-1">Inventario</p>
                <p class="h5 mb-0">Por movimientos</p>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="bg-white border rounded-2 p-3 h-100">
                <p class="text-secondary small mb-1">Offline</p>
                <p class="h5 mb-0">Preparado por diseno</p>
            </div>
        </div>
    </div>
</AppLayout>
