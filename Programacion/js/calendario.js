document.addEventListener('DOMContentLoaded', () => {
    // Array de eventos de ejemplo (puedes reemplazarlo trayendo datos via AJAX/Fetch desde PHP)
    const eventos = [
        { fecha: '2026-08-04', titulo: 'Torneo de Rugby', hora: '7:00 pm' },
        { fecha: '2026-08-07', titulo: 'Torneo de Handball', hora: '7:00 pm' },
        { fecha: '2026-08-14', titulo: 'Torneo de Damas', hora: '7:00 pm' },
        { fecha: '2026-08-19', titulo: 'Torneo de Baseball', hora: '9:00 am' },
        { fecha: '2026-08-19', titulo: 'Torneo de Ping-Pong', hora: '3:00 pm' },
        { fecha: '2026-08-25', titulo: 'Torneo de Ajedrez', hora: '5:00 pm' },
        { fecha: '2026-08-27', titulo: 'Torneo de Basketball', hora: '11:00 am' },
        { fecha: '2026-08-31', titulo: 'Torneo de Fútbol', hora: '7:00 pm' }
    ];

    const nombresMeses = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
    ];

    const nombresDiasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    let fechaActual = new Date(); // Fecha en uso para el calendario

    const tituloMesAnio = document.getElementById('titulo-mes-anio');
    const grillaDias = document.getElementById('grilla-dias');
    const agendaMovil = document.getElementById('agenda-movil');
    const btnPrev = document.getElementById('btn-prev-mes');
    const btnNext = document.getElementById('btn-next-mes');

    function renderizarCalendario() {
        const anio = fechaActual.getFullYear();
        const mes = fechaActual.getMonth();

        // Actualizar cabecera
        tituloMesAnio.textContent = `${nombresMeses[mes]} ${anio}`;

        // Limpiar contenedores
        grillaDias.innerHTML = '';
        agendaMovil.innerHTML = '';

        // Cálculos de días
        const primerDiaMes = new Date(anio, mes, 1).getDay(); // Día de la semana (0 - 6)
        const totalDiasMes = new Date(anio, mes + 1, 0).getDate();
        const totalDiasMesAnterior = new Date(anio, mes, 0).getDate();

        const hoy = new Date();

        // 1. Días del mes anterior (fuera del mes)
        for (let i = primerDiaMes - 1; i >= 0; i--) {
            const numeroDia = totalDiasMesAnterior - i;
            grillaDias.appendChild(crearCeldaDia(numeroDia, true));
        }

        // 2. Días del mes actual
        for (let dia = 1; dia <= totalDiasMes; dia++) {
            const esHoy = dia === hoy.getDate() && mes === hoy.getMonth() && anio === hoy.getFullYear();
            
            // Formato YYYY-MM-DD
            const fechaString = `${anio}-${String(mes + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
            const eventosDelDia = eventos.filter(e => e.fecha === fechaString);

            // Render en grilla
            const celda = crearCeldaDia(dia, false, esHoy, eventosDelDia);
            grillaDias.appendChild(celda);

            // Render en agenda móvil (solo si hay eventos o si es hoy)
            if (eventosDelDia.length > 0 || esHoy) {
                const fechaObj = new Date(anio, mes, dia);
                const nombreDiaSemana = nombresDiasSemana[fechaObj.getDay()];
                const grupoAgenda = crearGrupoAgenda(nombreDiaSemana, dia, esHoy, eventosDelDia);
                agendaMovil.appendChild(grupoAgenda);
            }
        }

        // 3. Días del mes siguiente (para rellenar la grilla a un múltiplo de 7)
        const totalCeldasRellenadas = primerDiaMes + totalDiasMes;
        const diasSiguientes = (7 - (totalCeldasRellenadas % 7)) % 7;

        for (let i = 1; i <= diasSiguientes; i++) {
            grillaDias.appendChild(crearCeldaDia(i, true));
        }
    }

    function crearCeldaDia(numeroDia, fueraMes, esHoy = false, eventosDia = []) {
        const divCelda = document.createElement('div');
        divCelda.className = 'celda-dia-calendario';
        if (fueraMes) divCelda.classList.add('fuera-mes');
        if (esHoy) divCelda.classList.add('es-hoy');

        let htmlEventos = '';
        if (eventosDia.length > 0) {
            htmlEventos = `<div class="contenedor-eventos-calendario">` +
                eventosDia.map(e => `
                    <span class="etiqueta-evento">
                        ${e.titulo} <span class="hora-evento">${e.hora}</span>
                    </span>
                `).join('') +
            `</div>`;
        }

        divCelda.innerHTML = `
            <div class="cabecera-dia-calendario">
                <span class="numero-dia">${numeroDia}</span>
                <button class="btn-agregar-evento" aria-label="Agregar evento">+</button>
            </div>
            ${htmlEventos}
        `;

        return divCelda;
    }

    function crearGrupoAgenda(nombreDia, numeroDia, esHoy, eventosDia) {
        const divGrupo = document.createElement('div');
        divGrupo.className = 'grupo-dia-agenda';
        if (esHoy) divGrupo.classList.add('es-grupo-hoy');

        let htmlTarjetas = '';
        if (eventosDia.length > 0) {
            htmlTarjetas = eventosDia.map(e => `
                <div class="tarjeta-evento-agenda">
                    <span class="titulo-evento-agenda">${e.titulo}</span>
                    <span class="hora-evento-agenda">${e.hora}</span>
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

    // Controles de navegación de mes
    btnPrev.addEventListener('click', () => {
        fechaActual.setMonth(fechaActual.getMonth() - 1);
        renderizarCalendario();
    });

    btnNext.addEventListener('click', () => {
        fechaActual.setMonth(fechaActual.getMonth() + 1);
        renderizarCalendario();
    });

    // Inicializar
    renderizarCalendario();
});