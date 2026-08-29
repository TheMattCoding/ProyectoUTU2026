document.addEventListener('DOMContentLoaded', () => {
    // Referencia al estado de la vista y fecha
    let fechaActual = new Date();
    let modoVista = 'global'; // 'global' o 'propio'

    // Elementos del DOM
    const btnGlobal = document.getElementById('btn-tab-global');
    const btnPropio = document.getElementById('btn-tab-propio');
    const tituloMesAnio = document.getElementById('titulo-mes-anio');
    const grillaDias = document.getElementById('grilla-dias');
    const agendaMovil = document.getElementById('agenda-movil');
    const btnPrev = document.getElementById('btn-prev-mes');
    const btnNext = document.getElementById('btn-next-mes');

    const nombresMeses = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
    ];
    const nombresDiasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    // --- EVENT LISTENERS PARA BOTONES DE PESTAÑA ---
    if (btnGlobal) {
        btnGlobal.addEventListener('click', () => {
            modoVista = 'global';
            btnGlobal.classList.add('activo');
            if (btnPropio) btnPropio.classList.remove('activo');
            renderizarCalendario();
        });
    }

    if (btnPropio && !btnPropio.classList.contains('deshabilitado')) {
        btnPropio.addEventListener('click', () => {
            modoVista = 'propio';
            btnPropio.classList.add('activo');
            if (btnGlobal) btnGlobal.classList.remove('activo');
            renderizarCalendario();
        });
    }

    // --- NAVEGACIÓN DE MESES ---
    btnPrev.addEventListener('click', () => {
        fechaActual.setMonth(fechaActual.getMonth() - 1);
        renderizarCalendario();
    });

    btnNext.addEventListener('click', () => {
        fechaActual.setMonth(fechaActual.getMonth() + 1);
        renderizarCalendario();
    });

    // --- FUNCIÓN PRINCIPAL DE RENDERIZADO ---
    function renderizarCalendario() {
        const anio = fechaActual.getFullYear();
        const mes = fechaActual.getMonth();

        // Obtener el conjunto de datos de PHP según el modo activo
        const listaTorneos = (modoVista === 'global') 
            ? (window.torneosGlobales || []) 
            : (window.torneosPropios || []);

        // Actualizar título
        tituloMesAnio.textContent = `${nombresMeses[mes]} ${anio}`;

        // Limpiar contenedores
        grillaDias.innerHTML = '';
        agendaMovil.innerHTML = '';

        const primerDiaMes = new Date(anio, mes, 1).getDay();
        const totalDiasMes = new Date(anio, mes + 1, 0).getDate();
        const totalDiasMesAnterior = new Date(anio, mes, 0).getDate();
        const hoy = new Date();

        // 1. Días fuera de mes (Mes Anterior)
        for (let i = primerDiaMes - 1; i >= 0; i--) {
            const numeroDia = totalDiasMesAnterior - i;
            grillaDias.appendChild(crearCeldaDia(numeroDia, true));
        }

        // 2. Días del mes actual
        for (let dia = 1; dia <= totalDiasMes; dia++) {
            const esHoy = dia === hoy.getDate() && mes === hoy.getMonth() && anio === hoy.getFullYear();
            
            // Formato de comparación YYYY-MM-DD
            const mesStr = String(mes + 1).padStart(2, '0');
            const diaStr = String(dia).padStart(2, '0');
            const fechaString = `${anio}-${mesStr}-${diaStr}`;

            // Filtrar los torneos de la base de datos correspondientes a este día
            const torneosDelDia = listaTorneos.filter(t => t.fecha_inicio && t.fecha_inicio.startsWith(fechaString));

            // Renderizar celda Desktop
            const celda = crearCeldaDia(dia, false, esHoy, torneosDelDia);
            grillaDias.appendChild(celda);

            // Renderizar item Mobile (si hay torneos o si es el día de hoy)
            if (torneosDelDia.length > 0 || esHoy) {
                const fechaObj = new Date(anio, mes, dia);
                const nombreDiaSemana = nombresDiasSemana[fechaObj.getDay()];
                const grupoAgenda = crearGrupoAgenda(nombreDiaSemana, dia, esHoy, torneosDelDia);
                agendaMovil.appendChild(grupoAgenda);
            }
        }

        // 3. Días fuera de mes (Mes Siguiente para completar grilla de 7 columnas)
        const totalCeldasRellenadas = primerDiaMes + totalDiasMes;
        const diasSiguientes = (7 - (totalCeldasRellenadas % 7)) % 7;

        for (let i = 1; i <= diasSiguientes; i++) {
            grillaDias.appendChild(crearCeldaDia(i, true));
        }
    }

    // --- CREADOR DE CELDAS (DESKTOP) ---
    function crearCeldaDia(numeroDia, fueraMes, esHoy = false, torneosDia = []) {
        const divCelda = document.createElement('div');
        divCelda.className = 'celda-dia-calendario';
        if (fueraMes) divCelda.classList.add('fuera-mes');
        if (esHoy) divCelda.classList.add('es-hoy');

        let htmlEventos = '';
        if (torneosDia.length > 0) {
            htmlEventos = `<div class="contenedor-eventos-calendario">` +
                torneosDia.map(t => `
                    <a href="detalleTorneo.php?id=${t.id_torneo}" class="etiqueta-evento" style="text-decoration:none;">
                        ${t.nombre_torneo} 
                        <span class="hora-evento">${t.disciplina ? t.disciplina : ''}</span>
                    </a>
                `).join('') +
            `</div>`;
        }

        divCelda.innerHTML = `
            <div class="cabecera-dia-calendario">
                <span class="numero-dia">${numeroDia}</span>
            </div>
            ${htmlEventos}
        `;

        return divCelda;
    }

    // --- CREADOR DE GRUPOS DE AGENDA (MOBILE) ---
    function crearGrupoAgenda(nombreDia, numeroDia, esHoy, torneosDia) {
        const divGrupo = document.createElement('div');
        divGrupo.className = 'grupo-dia-agenda';
        if (esHoy) divGrupo.classList.add('es-grupo-hoy');

        let htmlTarjetas = '';
        if (torneosDia.length > 0) {
            htmlTarjetas = torneosDia.map(t => `
                <div class="tarjeta-evento-agenda">
                    <a href="detalleTorneo.php?id=${t.id_torneo}" class="titulo-evento-agenda" style="text-decoration:none; color:inherit;">
                        ${t.nombre_torneo}
                    </a>
                    <span class="hora-evento-agenda">${t.disciplina || 'General'}</span>
                </div>
            `).join('');
        } else {
            htmlTarjetas = `<p style="font-size: 0.85rem; color: #888;">Sin torneos programados</p>`;
        }

        const textoHoy = esHoy ? ' (Hoy)' : '';
        divGrupo.innerHTML = `
            <h3 class="cabecera-fecha-agenda">${nombreDia} ${numeroDia}${textoHoy}</h3>
            <div class="lista-eventos-agenda">
                ${htmlTarjetas}
            </div>
        `;

        return divGrupo;
    }

    // Inicializar la grilla con los datos cargados
    renderizarCalendario();
});