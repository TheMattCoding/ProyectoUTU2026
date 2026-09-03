document.addEventListener('DOMContentLoaded', () => {

    // --- FILTRADO Y ORDEN DE TORNEOS ---
    const inputBuscarTorneo = document.getElementById('buscar-torneo');
    const selectOrdenarTorneo = document.getElementById('ordenar-torneo');
    const tbodyTorneos = document.querySelector('#tabla-torneos tbody');

    function filtrarYOrdenarTorneos() {
        const texto = inputBuscarTorneo.value.toLowerCase().trim();
        const criterio = selectOrdenarTorneo.value;
        const filas = Array.from(tbodyTorneos.querySelectorAll('tr[data-nombre]'));

        filas.forEach(fila => {
            const nombre = fila.getAttribute('data-nombre') || '';
            fila.style.display = nombre.includes(texto) ? '' : 'none';
        });

        if (criterio !== 'defecto') {
            filas.sort((a, b) => {
                if (criterio === 'nombre-asc') {
                    return a.getAttribute('data-nombre').localeCompare(b.getAttribute('data-nombre'));
                } else if (criterio === 'nombre-desc') {
                    return b.getAttribute('data-nombre').localeCompare(a.getAttribute('data-nombre'));
                } else if (criterio === 'fecha-asc') {
                    return a.getAttribute('data-fecha').localeCompare(b.getAttribute('data-fecha'));
                } else if (criterio === 'fecha-desc') {
                    return b.getAttribute('data-fecha').localeCompare(a.getAttribute('data-fecha'));
                }
                return 0;
            });
            filas.forEach(fila => tbodyTorneos.appendChild(fila));
        }
    }

    if (inputBuscarTorneo && selectOrdenarTorneo) {
        inputBuscarTorneo.addEventListener('input', filtrarYOrdenarTorneos);
        selectOrdenarTorneo.addEventListener('change', filtrarYOrdenarTorneos);
    }

    // --- FILTRADO Y ORDEN DE INSCRIPCIONES ---
    const inputBuscarInscripcion = document.getElementById('buscar-inscripcion');
    const selectOrdenarInscripcion = document.getElementById('ordenar-inscripcion');
    const tbodyInscripciones = document.querySelector('#tabla-inscripciones tbody');

    function filtrarYOrdenarInscripciones() {
        const texto = inputBuscarInscripcion.value.toLowerCase().trim();
        const criterio = selectOrdenarInscripcion.value;
        const filas = Array.from(tbodyInscripciones.querySelectorAll('tr[data-torneo]'));

        filas.forEach(fila => {
            const torneo = fila.getAttribute('data-torneo') || '';
            const sujeto = fila.getAttribute('data-sujeto') || '';
            const coincide = torneo.includes(texto) || sujeto.includes(texto);
            fila.style.display = coincide ? '' : 'none';
        });

        if (criterio !== 'defecto') {
            filas.sort((a, b) => {
                if (criterio === 'torneo-asc') {
                    return a.getAttribute('data-torneo').localeCompare(b.getAttribute('data-torneo'));
                } else if (criterio === 'torneo-desc') {
                    return b.getAttribute('data-torneo').localeCompare(a.getAttribute('data-torneo'));
                } else if (criterio === 'participante-asc') {
                    return a.getAttribute('data-sujeto').localeCompare(b.getAttribute('data-sujeto'));
                }
                return 0;
            });
            filas.forEach(fila => tbodyInscripciones.appendChild(fila));
        }
    }

    if (inputBuscarInscripcion && selectOrdenarInscripcion) {
        inputBuscarInscripcion.addEventListener('input', filtrarYOrdenarInscripciones);
        selectOrdenarInscripcion.addEventListener('change', filtrarYOrdenarInscripciones);
    }
});