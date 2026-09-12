import * as bootstrap from 'bootstrap';
import Alpine from 'alpinejs';
import Swal from 'sweetalert2';

window.bootstrap = bootstrap;
window.Alpine = Alpine;

window.Swal = Swal;
window.toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
});

document.addEventListener('DOMContentLoaded', () => Alpine.start(), { once: true });

document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm-title')) {
        return;
    }

    e.preventDefault();

    Swal.fire({
        title: form.getAttribute('data-confirm-title'),
        text: form.getAttribute('data-confirm-text') || undefined,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: form.getAttribute('data-confirm-button') || 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: 'var(--danger)',
        reverseButtons: true,
        allowOutsideClick: false,
    }).then(function (result) {
        if (result.isConfirmed) {
            if (form.dataset.submitted) return;
            form.dataset.submitted = '1';
            form.removeAttribute('data-confirm-title');
            form.submit();
        }
    });
});

// Previene doble envío por doble clic en cualquier form (POST/PUT/PATCH/DELETE).
// Los forms GET (filtros) y el checkout de POS (fetch async con `processing`)
// quedan excluidos de forma natural.
document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement) || form.method === 'get') return;
    if (e.submitter) e.submitter.disabled = true;
});

// Rehabilita botones al cerrar un modal sin enviar el form dentro.
document.addEventListener('hidden.bs.modal', function (e) {
    e.target.querySelectorAll('button[type="submit"]').forEach(function (btn) {
        btn.disabled = false;
    });
});
