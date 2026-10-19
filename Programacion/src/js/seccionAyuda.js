document.addEventListener('DOMContentLoaded', () => {
    const btnAbrirAyuda = document.getElementById('btn-seccion-ayuda');
    const btnCerrarAyuda = document.getElementById('btn-cerrar-seccion-ayuda');
    const modalAyuda = document.getElementById('seccion-ayuda');
    const fondoOscuro = document.getElementById('fondo-seccion-nosotros');

    function abrirModalAyuda() {
        modalAyuda.classList.add('activa');
        fondoOscuro.classList.add('activo');
        modalAyuda.setAttribute('aria-hidden', 'false');
    }

    function cerrarModalAyuda() {
        modalAyuda.classList.remove('activa');
        fondoOscuro.classList.remove('activo');
        modalAyuda.setAttribute('aria-hidden', 'true');
    }

    if (btnAbrirAyuda) btnAbrirAyuda.addEventListener('click', abrirModalAyuda);
    if (btnCerrarAyuda) btnCerrarAyuda.addEventListener('click', cerrarModalAyuda);
    if (fondoOscuro) fondoOscuro.addEventListener('click', cerrarModalAyuda);
});