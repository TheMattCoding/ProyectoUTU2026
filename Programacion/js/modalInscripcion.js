document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('modal-inscripcion');
    const btnAbrir = document.getElementById('btn-abrir-modal');
    const btnCerrar = document.getElementById('btn-cerrar-modal');

    if (btnAbrir && modal) {
        btnAbrir.addEventListener('click', () => {
            modal.style.display = 'flex';
        });
    }

    if (btnCerrar && modal) {
        btnCerrar.addEventListener('click', () => {
            modal.style.display = 'none';
        });
    }

    // Cerrar al hacer clic fuera de la ventana
    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
});