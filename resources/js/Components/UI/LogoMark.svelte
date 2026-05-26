<script>
    let { size = 38, variant = 'color' } = $props();

    const id = `ts-bg-${Math.random().toString(36).slice(2, 7)}`;
    const fill = $derived(variant === 'white' ? 'white' : `url(#${id})`);
    const mark = $derived(variant === 'white' ? 'rgba(20,108,67,0.95)' : 'white');
</script>

<svg
    xmlns="http://www.w3.org/2000/svg"
    width={size}
    height={size}
    viewBox="0 0 100 100"
    aria-label="Taguara Sync"
    role="img"
>
    <defs>
        <linearGradient id={id} x1="0" y1="0" x2="100" y2="100" gradientUnits="userSpaceOnUse">
            <stop offset="0%" stop-color="#1d9058"/>
            <stop offset="100%" stop-color="#0c4228"/>
        </linearGradient>
    </defs>

    <rect width="100" height="100" rx="22" {fill}/>

    <!-- T crossbar -->
    <rect x="15" y="17" width="70" height="14" rx="7" fill={mark}/>

    <!-- T stem -->
    <rect x="43" y="17" width="14" height="37" rx="7" fill={mark}/>

    <!-- Sync ring (270° arc, gap at top) -->
    <circle
        cx="50" cy="70" r="13"
        fill="none"
        stroke={mark}
        stroke-width="5"
        stroke-linecap="round"
        stroke-dasharray="61.3 20.4"
        stroke-dashoffset="-71.5"
    />

    <!-- Arrowhead at arc end pointing clockwise -->
    <polygon points="36,68 44,65 40,56" fill={mark}/>
</svg>
