document.addEventListener('DOMContentLoaded', function () {

    const abrirHistorial = document.getElementById('abrir-historial');
    const cerrarHistorial = document.getElementById('cerrar-historial');
    const fondoHistorial = document.getElementById('fondo-historial');
    const seccionHistorial = document.getElementById('seccion-historial');

    abrirHistorial.addEventListener('click', function (e) {
        e.preventDefault();

        fondoHistorial.classList.add('activo');
        seccionHistorial.classList.add('activo');
        seccionHistorial.setAttribute('aria-hidden', 'false');
    });

    cerrarHistorial.addEventListener('click', function () {
        fondoHistorial.classList.remove('activo');
        seccionHistorial.classList.remove('activo');
        seccionHistorial.setAttribute('aria-hidden', 'true');
    });

    fondoHistorial.addEventListener('click', function () {
        fondoHistorial.classList.remove('activo');
        seccionHistorial.classList.remove('activo');
        seccionHistorial.setAttribute('aria-hidden', 'true');
    });

});
