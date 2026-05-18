<script>
    import Create from './Create.svelte';

    let { auth, options, product } = $props();
</script>

<Create {auth} {options} {product} />
