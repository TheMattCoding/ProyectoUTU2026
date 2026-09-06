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

    // 3. Conmutador de Tema Oscuro / Claro
    const btnThemeToggle = document.getElementById('btn-theme-toggle');
    if (btnThemeToggle) {
        btnThemeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
        });
    }
});

        const disciplina = document.getElementById('disciplina');
        const otraDisciplina = document.getElementById('otra-disciplina');

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

        const modalidad = document.getElementById('modalidad');
        const labelCantidad = document.getElementById('label-cantidad');
        const grupoParticipantesEquipo = document.getElementById('grupo-participantes-equipo');
        const participantesEquipo = document.getElementById('participantes_equipo');

        function actualizarModalidad() {
            if (modalidad.value === 'individual') {
                labelCantidad.textContent = 'Cantidad de participantes';

                cantidad.min = 2;

                grupoParticipantesEquipo.style.display = 'none';
                participantesEquipo.required = false;
                participantesEquipo.value = '';

            } else if (modalidad.value === 'equipos') {
                labelCantidad.textContent = 'Cantidad de Equipos';

                cantidad.min = 2;
                participantesEquipo.min = 1;

                grupoParticipantesEquipo.style.display = 'flex';
                participantesEquipo.required = true;
            }
        }

        modalidad.addEventListener('change', actualizarModalidad);

        actualizarModalidad();