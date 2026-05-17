import Swal from 'sweetalert2';

const taguaraConfirmColor = '#146c43';
const taguaraCancelColor = '#6c757d';

export const confirmAction = async ({
    title = 'Confirmar accion',
    text = 'Esta accion requiere confirmacion.',
    confirmButtonText = 'Confirmar',
    cancelButtonText = 'Cancelar',
    icon = 'warning',
} = {}) => Swal.fire({
    title,
    text,
    icon,
    showCancelButton: true,
    confirmButtonText,
    cancelButtonText,
    confirmButtonColor: taguaraConfirmColor,
    cancelButtonColor: taguaraCancelColor,
    reverseButtons: true,
});

export const alertSuccess = async ({
    title = 'Listo',
    text = 'La accion se completo correctamente.',
} = {}) => Swal.fire({
    title,
    text,
    icon: 'success',
    confirmButtonColor: taguaraConfirmColor,
});

export const alertError = async ({
    title = 'No se pudo completar',
    text = 'Intenta nuevamente.',
} = {}) => Swal.fire({
    title,
    text,
    icon: 'error',
    confirmButtonColor: taguaraConfirmColor,
});

export const alertWarning = async ({
    title = 'Atencion',
    text = 'Revisa la informacion antes de continuar.',
} = {}) => Swal.fire({
    title,
    text,
    icon: 'warning',
    confirmButtonColor: taguaraConfirmColor,
});
