document.addEventListener('DOMContentLoaded', () => {
    // 1. Redirección del botón Cancelar
    const btnCancelar = document.getElementById('btn-cancelar');
    if (btnCancelar) {
        btnCancelar.addEventListener('click', () => {
            window.location.href = 'inicio.php';
        });
    }

    // 2. Mostrar nombre de la imagen seleccionada en la portada
    const inputPortada = document.getElementById('portada-torneo');
    const textoSubirArchivo = document.getElementById('texto-subir-archivo');

    if (inputPortada && textoSubirArchivo) {
        inputPortada.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                textoSubirArchivo.textContent = file.name;
            } else {
                textoSubirArchivo.textContent = 'Seleccionar Imagen';
            }
        });
    }

   // 4. Manejo de Disciplina "Otra"
    const disciplina = document.getElementById('disciplina');
    const otraDisciplina = document.getElementById('otra-disciplina');

    if (disciplina && otraDisciplina) {
        disciplina.addEventListener('change', function () {
            if (this.value === 'Otra') {
                otraDisciplina.style.display = 'block';
                otraDisciplina.required = true;
            } else {
                otraDisciplina.style.display = 'none';
                otraDisciplina.required = false;
                otraDisciplina.value = '';
            }
        });
    }

    // 5. Manejo de Modalidad (Individual / Equipos) y restricción de caracteres/valores
    const modalidad = document.getElementById('modalidad');
    const cantidad = document.getElementById('cantidad');
    const labelCantidad = document.getElementById('label-cantidad');
    const grupoParticipantesEquipo = document.getElementById('grupo-participantes-equipo');
    const participantesEquipo = document.getElementById('participantes_equipo');
    const cantidadRondas = document.getElementById('cantidad_rondas');

    if (modalidad && cantidad && labelCantidad && grupoParticipantesEquipo && participantesEquipo) {
        function actualizarModalidad() {
            if (modalidad.value === 'individual') {
                labelCantidad.textContent = 'Cantidad de participantes';
                cantidad.min = 2;
                cantidad.max = 128; // Máximo para torneos individuales
                grupoParticipantesEquipo.style.display = 'none';
                participantesEquipo.required = false;
                participantesEquipo.value = '';
            } else if (modalidad.value === 'equipos') {
                labelCantidad.textContent = 'Cantidad de Equipos';
                cantidad.min = 2;
                cantidad.max = 32; // Máximo para torneos por equipos
                participantesEquipo.min = 1;
                participantesEquipo.max = 26; // Máximo para participantes por equipo
                grupoParticipantesEquipo.style.display = 'flex';
                participantesEquipo.required = true;
            }
        }

        // Función que impide escribir más del límite máximo o dígitos extra
        function limitarEntradaNumerica(input) {
            if (!input.value) return;

            const maxValor = parseInt(input.max, 10);
            const minValor = parseInt(input.min, 10);
            let valorActual = parseInt(input.value, 10);

            // Si el valor tipeado supera el max permitido por el campo, ajusta al máximo
            if (maxValor && valorActual > maxValor) {
                input.value = maxValor;
            }

            // Opcional: Recorte por cantidad máxima de dígitos según el atributo max
            const digitosMaximos = maxValor ? maxValor.toString().length : 3;
            if (input.value.length > digitosMaximos) {
                input.value = input.value.slice(0, digitosMaximos);
            }
        }

        // Asignar los eventos 'input'
        cantidad.addEventListener('input', () => limitarEntradaNumerica(cantidad));
        participantesEquipo.addEventListener('input', () => limitarEntradaNumerica(participantesEquipo));
        if (cantidadRondas) {
            cantidadRondas.addEventListener('input', () => limitarEntradaNumerica(cantidadRondas));
        }

        modalidad.addEventListener('change', actualizarModalidad);
        actualizarModalidad();
    }
});     