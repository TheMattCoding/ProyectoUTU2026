document.addEventListener('DOMContentLoaded', function () {
    const btnSeccionNosotros = document.getElementById('btn-seccion-nosotros');
    const btnCerrarSeccion = document.getElementById('btn-cerrar-seccion-nosotros');
    const seccionSobreNosotros = document.getElementById('seccion-sobre-nosotros');
    const fondoSeccionNosotros = document.getElementById('fondo-seccion-nosotros');

    function abrirSeccion() {
        if (seccionSobreNosotros && fondoSeccionNosotros) {
            seccionSobreNosotros.classList.add('activa');
            fondoSeccionNosotros.classList.add('activo');
            seccionSobreNosotros.setAttribute('aria-hidden', 'false');
        }
    }

    function cerrarSeccion() {
        if (seccionSobreNosotros && fondoSeccionNosotros) {
            seccionSobreNosotros.classList.remove('activa');
            fondoSeccionNosotros.classList.remove('activo');
            seccionSobreNosotros.setAttribute('aria-hidden', 'true');
        }
    }

    if (btnSeccionNosotros) {
        btnSeccionNosotros.addEventListener('click', abrirSeccion);
    }

    if (btnCerrarSeccion) {
        btnCerrarSeccion.addEventListener('click', cerrarSeccion);
    }

    if (fondoSeccionNosotros) {
        fondoSeccionNosotros.addEventListener('click', cerrarSeccion);
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            cerrarSeccion();
        }
    });
});