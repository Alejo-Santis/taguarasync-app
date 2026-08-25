<script>
    import { page } from '@inertiajs/svelte';
    import { Toaster, toast } from 'svelte-sonner';

    let lastSignature = $state('');
    let lastErrorSignature = $state('');

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

    // Belt-and-suspenders: guarantees a visible error even when a page fails
    // to render a specific `form.errors` key inline (e.g. a nested/array
    // field the page forgot to bind), since every validation failure lands
    // in this shared `page.props.errors` regardless of what each page shows.
    $effect(() => {
        const errors = page.props.errors ?? {};
        const errorSignature = JSON.stringify(errors);

        if (!errorSignature || errorSignature === '{}' || errorSignature === lastErrorSignature) {
            return;
        }

        lastErrorSignature = errorSignature;

        const messages = Object.values(errors).flat().filter(Boolean);

        if (messages.length === 1) {
            toast.error(messages[0]);
        } else if (messages.length > 1) {
            toast.error(`Revisa los campos marcados: ${messages[0]}`, {
                description: `y ${messages.length - 1} error(es) mas.`,
            });
        }
    });
</script>

<Toaster
    position="top-right"
    richColors
    closeButton
    duration={4500}
/>
