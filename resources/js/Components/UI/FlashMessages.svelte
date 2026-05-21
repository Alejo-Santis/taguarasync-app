<script>
    import { page } from '@inertiajs/svelte';
    import { Toaster, toast } from 'svelte-sonner';

    let lastSignature = $state('');

    $effect(() => {
        const flash = page.props.flash ?? {};
        const signature = JSON.stringify(flash);

        if (!signature || signature === '{}' || signature === lastSignature) {
            return;
        }

        lastSignature = signature;

        if (flash.success || flash.status) {
            toast.success(flash.success ?? flash.status);
        }
        if (flash.error) {
            toast.error(flash.error);
        }
        if (flash.warning) {
            toast.warning(flash.warning);
        }
        if (flash.info) {
            toast.info(flash.info);
        }
        if (flash.message) {
            toast(flash.message);
        }
    });
</script>

<Toaster
    position="top-right"
    richColors
    closeButton
    duration={4500}
/>
