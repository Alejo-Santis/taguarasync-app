<script>
    import { Link } from '@inertiajs/svelte';
    import {
        AlertCircle,
        AlertTriangle,
        ArrowLeft,
        FileSearch,
        Home,
        LogIn,
        ServerCrash,
        ShieldX,
        Timer,
        Wrench,
    } from '@lucide/svelte';
    import LogoMark from '../Components/UI/LogoMark.svelte';

    let { status, message = null } = $props();

    const catalog = {
        400: {
            icon:        AlertCircle,
            color:       '#d97706',
            bg:          '#fffbeb',
            border:      '#fde68a',
            title:       'Solicitud incorrecta',
            description: 'La solicitud enviada contiene datos inválidos o mal formateados.',
            hint:        'Vuelve atrás y revisa lo que enviaste.',
        },
        401: {
            icon:        LogIn,
            color:       '#2563eb',
            bg:          '#eff6ff',
            border:      '#bfdbfe',
            title:       'Inicia sesión',
            description: 'Necesitas autenticarte para acceder a esta sección.',
            hint:        'Inicia sesión con tu cuenta y vuelve a intentarlo.',
        },
        403: {
            icon:        ShieldX,
            color:       '#dc2626',
            bg:          '#fef2f2',
            border:      '#fecaca',
            title:       'Acceso denegado',
            description: 'No tienes los permisos necesarios para ver esta página.',
            hint:        'Si crees que esto es un error, contacta al administrador de tu farmacia.',
        },
        404: {
            icon:        FileSearch,
            color:       '#64748b',
            bg:          '#f8fafc',
            border:      '#e2e8f0',
            title:       'Página no encontrada',
            description: 'La página que buscas no existe, fue movida o fue eliminada.',
            hint:        'Verifica la dirección o usa el menú de navegación.',
        },
        422: {
            icon:        AlertTriangle,
            color:       '#d97706',
            bg:          '#fffbeb',
            border:      '#fde68a',
            title:       'Datos inválidos',
            description: 'Los datos enviados no pasan la validación del servidor.',
            hint:        'Vuelve atrás, corrige el formulario e intenta de nuevo.',
        },
        429: {
            icon:        Timer,
            color:       '#7c3aed',
            bg:          '#f5f3ff',
            border:      '#ddd6fe',
            title:       'Demasiadas solicitudes',
            description: 'Hiciste demasiadas peticiones en poco tiempo.',
            hint:        'Espera un momento y vuelve a intentarlo.',
        },
        500: {
            icon:        ServerCrash,
            color:       '#dc2626',
            bg:          '#fef2f2',
            border:      '#fecaca',
            title:       'Error interno del servidor',
            description: 'Algo salió mal en el servidor. El equipo técnico ya fue notificado.',
            hint:        'Intenta de nuevo en unos minutos. Si el problema persiste, contacta soporte.',
        },
        503: {
            icon:        Wrench,
            color:       '#d97706',
            bg:          '#fffbeb',
            border:      '#fde68a',
            title:       'Sistema en mantenimiento',
            description: 'El sistema está temporalmente fuera de servicio para mantenimiento programado.',
            hint:        'Vuelve en unos minutos. Gracias por tu paciencia.',
        },
    };

    const fallback = {
        icon:        AlertCircle,
        color:       '#64748b',
        bg:          '#f8fafc',
        border:      '#e2e8f0',
        title:       'Algo salió mal',
        description: 'Ocurrió un error inesperado.',
        hint:        'Intenta volver atrás o ir al panel principal.',
    };

    const info = $derived(catalog[status] ?? fallback);
    const Icon = $derived(info.icon);

    // Si el servidor envió un mensaje específico (solo en 4xx), lo mostramos como detalle
    const showDetail = $derived(message && status < 500);

    // El botón principal varía según el error
    const primaryAction = $derived(
        status === 401 ? { label: 'Iniciar sesión', href: '/login' } :
        status === 403 ? { label: 'Volver',         href: null      } :
                         { label: 'Ir al panel',    href: '/dashboard' }
    );
</script>

<svelte:head>
    <title>Error {status} — Taguara Sync</title>
</svelte:head>

<div class="taguara-error-shell">

    <!-- Marca de agua con el código -->
    <span class="taguara-error-code" aria-hidden="true">{status}</span>

    <div class="taguara-error-card" style="--err-color:{info.color}; --err-bg:{info.bg}; --err-border:{info.border}">

        <!-- Logo -->
        <div class="taguara-error-brand">
            <LogoMark size={34} />
            <span class="taguara-error-brand-name">Taguara Sync</span>
        </div>

        <!-- Ícono -->
        <div class="taguara-error-icon-wrap">
            <Icon size={36} strokeWidth={1.5} />
        </div>

        <!-- Código + título -->
        <p class="taguara-error-status">{status}</p>
        <h1 class="taguara-error-title">{info.title}</h1>
        <p class="taguara-error-desc">{info.description}</p>

        <!-- Detalle del servidor (solo en 4xx con mensaje) -->
        {#if showDetail}
            <div class="taguara-error-detail">
                <AlertCircle size={13} />
                <span>{message}</span>
            </div>
        {/if}

        <!-- Hint -->
        <p class="taguara-error-hint">{info.hint}</p>

        <!-- Acciones -->
        <div class="taguara-error-actions">
            {#if primaryAction.href}
                <Link class="btn btn-taguara taguara-error-btn" href={primaryAction.href}>
                    <Home size={15} />
                    {primaryAction.label}
                </Link>
            {:else}
                <button class="btn btn-taguara taguara-error-btn" type="button" onclick={() => history.back()}>
                    <Home size={15} />
                    {primaryAction.label}
                </button>
            {/if}

            <button class="btn btn-light border taguara-error-btn" type="button" onclick={() => history.back()}>
                <ArrowLeft size={15} />
                Volver atrás
            </button>
        </div>
    </div>

</div>

<style>
    /* Shell ocupa toda la pantalla, centrado */
    .taguara-error-shell {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100dvh;
        background: #f8fafc;
        overflow: hidden;
        padding: 2rem 1rem;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    /* Número gigante de fondo */
    .taguara-error-code {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: clamp(10rem, 40vw, 22rem);
        font-weight: 900;
        color: rgba(0, 0, 0, 0.03);
        user-select: none;
        pointer-events: none;
        letter-spacing: -0.05em;
        white-space: nowrap;
    }

    /* Tarjeta principal */
    .taguara-error-card {
        position: relative;
        z-index: 1;
        width: 100%;
        max-width: 480px;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-top: 4px solid var(--err-color);
        border-radius: 16px;
        box-shadow: 0 4px 32px rgba(15, 23, 42, 0.08);
        padding: 2.5rem 2rem;
        text-align: center;
    }

    /* Marca */
    .taguara-error-brand {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        margin-bottom: 2rem;
        opacity: 0.5;
    }

    .taguara-error-brand-name {
        font-size: 0.9rem;
        font-weight: 600;
        color: #0f172a;
    }

    /* Ícono */
    .taguara-error-icon-wrap {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 72px;
        height: 72px;
        border-radius: 50%;
        background: var(--err-bg);
        border: 1px solid var(--err-border);
        color: var(--err-color);
        margin-bottom: 1.25rem;
    }

    /* Textos */
    .taguara-error-status {
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--err-color);
        margin-bottom: 0.5rem;
    }

    .taguara-error-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 0.75rem;
    }

    .taguara-error-desc {
        font-size: 0.9375rem;
        color: #475569;
        margin-bottom: 0;
        line-height: 1.6;
    }

    /* Detalle del servidor */
    .taguara-error-detail {
        display: flex;
        align-items: flex-start;
        gap: 0.375rem;
        margin-top: 1rem;
        padding: 0.625rem 0.875rem;
        background: var(--err-bg);
        border: 1px solid var(--err-border);
        border-radius: 8px;
        font-size: 0.8125rem;
        color: var(--err-color);
        text-align: left;
        line-height: 1.4;
    }

    /* Hint */
    .taguara-error-hint {
        margin-top: 1.25rem;
        font-size: 0.8125rem;
        color: #94a3b8;
        line-height: 1.5;
    }

    /* Botones */
    .taguara-error-actions {
        display: flex;
        gap: 0.75rem;
        justify-content: center;
        flex-wrap: wrap;
        margin-top: 1.75rem;
    }

    .taguara-error-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.875rem;
        padding: 0.5rem 1.25rem;
        border-radius: 8px;
        text-decoration: none;
    }

    @media (max-width: 480px) {
        .taguara-error-card { padding: 2rem 1.25rem; }
        .taguara-error-actions { flex-direction: column; }
        .taguara-error-btn { justify-content: center; }
    }
</style>
