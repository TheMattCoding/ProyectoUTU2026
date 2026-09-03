document.addEventListener('DOMContentLoaded', () => {
    const btnAbrirModal = document.getElementById('btn-abrir-modal');
    const btnCerrarModal = document.getElementById('btn-cerrar-modal');
    const modalInscripcion = document.getElementById('modal-inscripcion');
    const formCancelar = document.querySelector('.form-cancelar-inscripcion');

    // Abrir Modal
    if (btnAbrirModal && modalInscripcion) {
        btnAbrirModal.addEventListener('click', () => {
            modalInscripcion.style.display = 'flex';
        });
    }

    // Cerrar Modal al presionar el botón X
    if (btnCerrarModal && modalInscripcion) {
        btnCerrarModal.addEventListener('click', () => {
            modalInscripcion.style.display = 'none';
        });
    }

    // Cerrar Modal haciendo clic fuera del contenido
    if (modalInscripcion) {
        modalInscripcion.addEventListener('click', (e) => {
            if (e.target === modalInscripcion) {
                modalInscripcion.style.display = 'none';
            }
        });
    }

    // Confirmación al cancelar la inscripción (sin JS inline)
    if (formCancelar) {
        formCancelar.addEventListener('submit', (e) => {
            const confirmacion = confirm('¿Estás seguro de que deseas cancelar tu inscripción a este torneo?');
            if (!confirmacion) {
                e.preventDefault();
            }
        });
    }
});