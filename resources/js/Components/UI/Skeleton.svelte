<script>
    let { width = '100%', height = '1rem', radius = '6px', class: className = '' } = $props();
</script>

<span
    class="taguara-skeleton {className}"
    style="width:{width}; height:{height}; border-radius:{radius};"
    aria-hidden="true"
></span>
