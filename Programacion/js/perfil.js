document.addEventListener('DOMContentLoaded', function () {

    // --- MANEJO DEL HISTORIAL DE TORNEOS ---
    const abrirHistorial = document.getElementById('abrir-historial');
    const cerrarHistorial = document.getElementById('cerrar-historial');
    const fondoHistorial = document.getElementById('fondo-historial');
    const seccionHistorial = document.getElementById('seccion-historial');

    if (abrirHistorial) {
        abrirHistorial.addEventListener('click', function (e) {
            e.preventDefault();
            if (fondoHistorial) fondoHistorial.classList.add('activo');
            if (seccionHistorial) {
                seccionHistorial.classList.add('activo');
                seccionHistorial.setAttribute('aria-hidden', 'false');
            }
        });
    }

    if (cerrarHistorial) {
        cerrarHistorial.addEventListener('click', function () {
            if (fondoHistorial) fondoHistorial.classList.remove('activo');
            if (seccionHistorial) {
                seccionHistorial.classList.remove('activo');
                seccionHistorial.setAttribute('aria-hidden', 'true');
            }
        });
    }

    if (fondoHistorial) {
        fondoHistorial.addEventListener('click', function () {
            fondoHistorial.classList.remove('activo');
            if (seccionHistorial) {
                seccionHistorial.classList.remove('activo');
                seccionHistorial.setAttribute('aria-hidden', 'true');
            }
        });
    }


    // --- MANEJO DE LOS MODALES DE TROFEOS ---
    document.addEventListener('click', function (e) {
        // Detectar si el clic proviene del botón de trofeos o del SVG interno
        const btnTrofeo = e.target.closest('.btn-trofeo-modal');

        if (btnTrofeo) {
            e.preventDefault();
            const targetId = btnTrofeo.getAttribute('data-target');
            const modalObjetivo = document.getElementById(targetId);
            const fondoTrofeos = document.getElementById('fondo-trofeos');

            if (modalObjetivo) {
                // Cerrar cualquier otro modal abierto previamente
                document.querySelectorAll('.modal-trofeo-estilo').forEach(function (m) {
                    m.classList.remove('activo');
                    m.setAttribute('aria-hidden', 'true');
                });

                // Abrir el modal correspondiente
                modalObjetivo.classList.add('activo');
                modalObjetivo.setAttribute('aria-hidden', 'false');

                if (fondoTrofeos) fondoTrofeos.classList.add('activo');
            }
        }

        // Cierre con el botón (X) o haciendo clic fuera (en el fondo oscuro)
        if (e.target.matches('.cerrar-modal-trofeo') || e.target.id === 'fondo-trofeos') {
            cerrarModalesTrofeos();
        }
    });

    function cerrarModalesTrofeos() {
        document.querySelectorAll('.modal-trofeo-estilo').forEach(function (modal) {
            modal.classList.remove('activo');
            modal.setAttribute('aria-hidden', 'true');
        });

        const fondoTrofeos = document.getElementById('fondo-trofeos');
        if (fondoTrofeos) fondoTrofeos.classList.remove('activo');
    }

});