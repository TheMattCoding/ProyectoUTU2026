document.addEventListener('DOMContentLoaded', () => {
    // --- NAVEGACIÓN POR PESTAÑAS (TABS) ---
    window.mostrarPestana = function (evt, nombrePestana) {
        const contenidos = document.querySelectorAll('.tab-content');
        contenidos.forEach(c => c.style.display = 'none');

        const botones = document.querySelectorAll('.tab-btn');
        botones.forEach(b => b.classList.remove('active'));

        const pestanaObjetivo = document.getElementById(`pestana-${nombrePestana}`);
        if (pestanaObjetivo) {
            pestanaObjetivo.style.display = 'block';
        }

        if (evt && evt.currentTarget) {
            evt.currentTarget.classList.add('active');
        }
    };

    // --- FILTRADO DE TABLAS EN PESTAÑA GESTIÓN ---
    window.filtrarTablaGestion = function (evt, seccion) {
        const secciones = document.querySelectorAll('.subseccion-gestion');
        const botones = document.querySelectorAll('.subtab-btn');

        // Desactivar estado activo de botones
        botones.forEach(b => b.classList.remove('active'));

        if (seccion === 'todos') {
            secciones.forEach(s => s.style.display = 'block');
        } else {
            secciones.forEach(s => s.style.display = 'none');
            const objetivo = document.getElementById(`subseccion-${seccion}`);
            if (objetivo) {
                objetivo.style.display = 'block';
            }
        }

        if (evt && evt.currentTarget) {
            evt.currentTarget.classList.add('active');
        }
    };

    // --- BUSCADOR Y ORDENAMIENTO EN TABLA TORNEOS ---
    const buscarTorneo = document.getElementById('buscar-torneo');
    const ordenarTorneo = document.getElementById('ordenar-torneo');
    const tablaTorneos = document.getElementById('tabla-torneos');

    if (buscarTorneo && tablaTorneos) {
        buscarTorneo.addEventListener('input', () => {
            const query = buscarTorneo.value.toLowerCase().trim();
            const filas = tablaTorneos.querySelectorAll('tbody tr[data-nombre]');

            filas.forEach(fila => {
                const nombre = fila.getAttribute('data-nombre') || '';
                fila.style.display = nombre.includes(query) ? '' : 'none';
            });
        });
    }

    if (ordenarTorneo && tablaTorneos) {
        ordenarTorneo.addEventListener('change', () => {
            const tbody = tablaTorneos.querySelector('tbody');
            const filas = Array.from(tbody.querySelectorAll('tr[data-nombre]'));
            const criterio = ordenarTorneo.value;

            filas.sort((a, b) => {
                const nombreA = a.getAttribute('data-nombre') || '';
                const nombreB = b.getAttribute('data-nombre') || '';
                const fechaA = a.getAttribute('data-fecha') || '';
                const fechaB = b.getAttribute('data-fecha') || '';

                switch (criterio) {
                    case 'nombre-asc':
                        return nombreA.localeCompare(nombreB);
                    case 'nombre-desc':
                        return nombreB.localeCompare(nombreA);
                    case 'fecha-asc':
                        return fechaA.localeCompare(fechaB);
                    case 'fecha-desc':
                        return fechaB.localeCompare(fechaA);
                    default:
                        return 0;
                }
            });

            filas.forEach(fila => tbody.appendChild(fila));
        });
    }

    // --- BUSCADOR Y ORDENAMIENTO EN TABLA INSCRIPCIONES ---
    const buscarInscripcion = document.getElementById('buscar-inscripcion');
    const ordenarInscripcion = document.getElementById('ordenar-inscripcion');
    const tablaInscripciones = document.getElementById('tabla-inscripciones');

    if (buscarInscripcion && tablaInscripciones) {
        buscarInscripcion.addEventListener('input', () => {
            const query = buscarInscripcion.value.toLowerCase().trim();
            const filas = tablaInscripciones.querySelectorAll('tbody tr[data-torneo]');

            filas.forEach(fila => {
                const torneo = fila.getAttribute('data-torneo') || '';
                const sujeto = fila.getAttribute('data-sujeto') || '';
                fila.style.display = (torneo.includes(query) || sujeto.includes(query)) ? '' : 'none';
            });
        });
    }

    if (ordenarInscripcion && tablaInscripciones) {
        ordenarInscripcion.addEventListener('change', () => {
            const tbody = tablaInscripciones.querySelector('tbody');
            const filas = Array.from(tbody.querySelectorAll('tr[data-torneo]'));
            const criterio = ordenarInscripcion.value;

            filas.sort((a, b) => {
                const torneoA = a.getAttribute('data-torneo') || '';
                const torneoB = b.getAttribute('data-torneo') || '';
                const sujetoA = a.getAttribute('data-sujeto') || '';
                const sujetoB = b.getAttribute('data-sujeto') || '';

                switch (criterio) {
                    case 'torneo-asc':
                        return torneoA.localeCompare(torneoB);
                    case 'torneo-desc':
                        return torneoB.localeCompare(torneoA);
                    case 'participante-asc':
                        return sujetoA.localeCompare(sujetoB);
                    default:
                        return 0;
                }
            });

            filas.forEach(fila => tbody.appendChild(fila));
        });
    }
});