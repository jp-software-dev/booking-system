/**
 * AGENDA DE CITAS (agenda.php)
 *
 * Dibuja el mini calendario mensual y la agenda semanal con los horarios del
 * médico elegido, y envía la reserva a api/create_cita.php.
 *
 * - Los horarios y días hábiles vienen del servidor (atributos data-slots y
 *   data-dias-habiles), definidos en App\Helpers\Horario.
 * - Los horarios ocupados vienen de api/get_citas.php y se filtran por médico:
 *   un horario solo aparece "Ocupado" si ese médico ya tiene cita a esa hora.
 * - El servidor vuelve a validar todo al reservar; esta pantalla solo orienta.
 */
document.addEventListener('DOMContentLoaded', async () => {
    const contenedorAgenda = document.getElementById('agenda-grid-container');
    if (!contenedorAgenda) {
        return;
    }

    // === CONFIGURACIÓN Y ELEMENTOS ===
    const HORARIOS = JSON.parse(contenedorAgenda.dataset.slots || '[]');
    const DIAS_HABILES = JSON.parse(contenedorAgenda.dataset.diasHabiles || '[]'); // 1 = lunes ... 7 = domingo
    const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const ANCHO_MOVIL = 768;

    const selectorMedico = document.getElementById('agenda-medico');
    const miniCalendario = document.getElementById('mini-calendar-wrapper');
    const tituloRango = document.getElementById('agenda-current-range');
    const formularioReserva = document.getElementById('bookingForm');
    const modalReserva = new bootstrap.Modal(document.getElementById('bookingModal'));

    // === ESTADO ===
    let fechaBase = new Date();          // Fecha a partir de la cual se dibuja la agenda.
    let fechaSeleccionada = new Date();  // Día resaltado en el mini calendario.
    let horariosOcupados = new Set();    // Claves "idMedico|AAAA-MM-DD|HH:MM".
    let diasVisibles = calcularDiasVisibles();

    // === UTILIDADES DE FECHA ===

    /** En móvil se muestran 2 días; en escritorio, la semana de lunes a sábado. */
    function calcularDiasVisibles() {
        return window.innerWidth <= ANCHO_MOVIL ? 2 : 6;
    }

    /** Fecha local en formato AAAA-MM-DD (sin convertir a UTC, para no cambiar de día). */
    function formatearFecha(fecha) {
        const mes = String(fecha.getMonth() + 1).padStart(2, '0');
        const dia = String(fecha.getDate()).padStart(2, '0');
        return `${fecha.getFullYear()}-${mes}-${dia}`;
    }

    /** Fecha a medianoche (sin hora), para comparar días. */
    function soloFecha(fecha) {
        return new Date(fecha.getFullYear(), fecha.getMonth(), fecha.getDate());
    }

    /** Indica si en ese día se agendan citas (getDay() usa 0 = domingo). */
    function esDiaHabil(fecha) {
        const dia = fecha.getDay() === 0 ? 7 : fecha.getDay();
        return DIAS_HABILES.includes(dia);
    }

    /** Hora "HH:MM" en formato de 12 horas (ej. "9:30 AM"). */
    function formatearHora(fecha) {
        return new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }).format(fecha);
    }

    // === DATOS ===

    /** Descarga los horarios ocupados de todos los médicos desde hoy. */
    async function cargarHorariosOcupados() {
        const datos = await apiClient.get('get_citas.php');
        if (Array.isArray(datos)) {
            horariosOcupados = new Set(datos.map((c) => `${c.id_doctor}|${c.fecha}|${c.hora}`));
        }
    }

    // === AGENDA SEMANAL ===

    /** Primer día que se dibuja: el día elegido (móvil) o el lunes de esa semana (escritorio). */
    function primerDiaVisible() {
        if (diasVisibles === 2) {
            return soloFecha(fechaBase);
        }
        const desfaseLunes = fechaBase.getDay() === 0 ? -6 : 1 - fechaBase.getDay();
        return new Date(fechaBase.getFullYear(), fechaBase.getMonth(), fechaBase.getDate() + desfaseLunes);
    }

    /** Crea el botón de un horario según su estado (pasado, ocupado o libre). */
    function crearBotonHorario(dia, hora, idMedico) {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'time-slot';

        const [horas, minutos] = hora.split(':').map(Number);
        const fechaHora = new Date(dia.getFullYear(), dia.getMonth(), dia.getDate(), horas, minutos);
        const fechaTexto = formatearFecha(dia);

        if (fechaHora <= new Date()) {
            boton.disabled = true;
            boton.textContent = '—';
        } else if (horariosOcupados.has(`${idMedico}|${fechaTexto}|${hora}`)) {
            boton.disabled = true;
            boton.classList.add('booked');
            boton.textContent = 'Ocupado';
        } else {
            boton.textContent = formatearHora(fechaHora);
            boton.addEventListener('click', () => abrirModalReserva(dia, hora));
        }

        return boton;
    }

    /** Dibuja las columnas de días con sus horarios. */
    function dibujarAgenda() {
        contenedorAgenda.replaceChildren();

        const inicio = primerDiaVisible();
        tituloRango.textContent = `${MESES[inicio.getMonth()]} ${inicio.getFullYear()}`;

        const idMedico = selectorMedico.value;
        if (!idMedico) {
            const aviso = document.createElement('div');
            aviso.className = 'text-center text-secondary py-5 w-100';
            aviso.innerHTML = '<i class="bi bi-person-badge fs-1 d-block mb-2"></i>';
            aviso.append('Selecciona un médico para ver sus horarios disponibles.');
            contenedorAgenda.appendChild(aviso);
            return;
        }

        const hoy = soloFecha(new Date());

        for (let i = 0; i < diasVisibles; i++) {
            const dia = new Date(inicio.getFullYear(), inicio.getMonth(), inicio.getDate() + i);

            const columna = document.createElement('div');
            columna.className = 'agenda-col';
            columna.classList.toggle('today-col', dia.getTime() === hoy.getTime());
            columna.classList.toggle('active', dia.toDateString() === fechaSeleccionada.toDateString());

            const etiquetaDia = document.createElement('div');
            etiquetaDia.className = 'day-label';
            etiquetaDia.textContent = dia.toLocaleString('es', { weekday: 'short' });

            const circuloFecha = document.createElement('div');
            circuloFecha.className = 'date-circle';
            circuloFecha.textContent = dia.getDate();

            const horarios = document.createElement('div');
            horarios.className = 'w-100 d-flex flex-column align-items-center';

            if (dia < hoy || !esDiaHabil(dia)) {
                horarios.innerHTML = '<div class="opacity-25 py-5"><i class="bi bi-dash-lg fs-3"></i></div>';
            } else {
                HORARIOS.forEach((hora) => horarios.appendChild(crearBotonHorario(dia, hora, idMedico)));
            }

            columna.append(etiquetaDia, circuloFecha, horarios);
            contenedorAgenda.appendChild(columna);
        }
    }

    // === MINI CALENDARIO MENSUAL ===

    /** Dibuja el mes de fechaBase con los días pasados y no hábiles deshabilitados. */
    function dibujarMiniCalendario() {
        const anio = fechaBase.getFullYear();
        const mes = fechaBase.getMonth();
        const primerDiaSemana = new Date(anio, mes, 1).getDay();
        const diasDelMes = new Date(anio, mes + 1, 0).getDate();
        const hoy = soloFecha(new Date());

        const contenedor = document.createElement('div');
        contenedor.className = 'mini-cal';
        contenedor.innerHTML = `
            <div class="mini-cal-header">
                <span>${MESES[mes]} ${anio}</span>
                <div>
                    <i class="bi bi-chevron-left" id="mini-prev-month" role="button" aria-label="Mes anterior"></i>
                    <i class="bi bi-chevron-right ms-2" id="mini-next-month" role="button" aria-label="Mes siguiente"></i>
                </div>
            </div>
            <div class="mini-cal-grid">${['D', 'L', 'M', 'M', 'J', 'V', 'S'].map((d) => `<div class="mini-cal-day">${d}</div>`).join('')}</div>`;

        const cuadricula = contenedor.querySelector('.mini-cal-grid');
        for (let i = 0; i < primerDiaSemana; i++) {
            cuadricula.appendChild(document.createElement('div'));
        }

        for (let d = 1; d <= diasDelMes; d++) {
            const dia = new Date(anio, mes, d);
            const celda = document.createElement('div');
            celda.className = 'mini-cal-date';
            celda.textContent = d;
            celda.classList.toggle('active', dia.toDateString() === fechaSeleccionada.toDateString());
            celda.classList.toggle('today', dia.getTime() === hoy.getTime());

            if (dia < hoy || !esDiaHabil(dia)) {
                celda.classList.add('text-muted', 'opacity-50');
            } else {
                celda.addEventListener('click', () => {
                    fechaSeleccionada = new Date(dia);
                    fechaBase = new Date(dia);
                    actualizarTodo();
                });
            }
            cuadricula.appendChild(celda);
        }

        miniCalendario.replaceChildren(contenedor);

        document.getElementById('mini-prev-month').addEventListener('click', () => moverMes(-1));
        document.getElementById('mini-next-month').addEventListener('click', () => moverMes(1));
    }

    /** Cambia el mes mostrado (en el día 1 para no saltar meses cortos). */
    function moverMes(delta) {
        fechaBase = new Date(fechaBase.getFullYear(), fechaBase.getMonth() + delta, 1);
        actualizarTodo();
    }

    /** Avanza o retrocede la agenda tantos días como columnas visibles. */
    function moverSemana(direccion) {
        const salto = diasVisibles === 2 ? 2 : 7;
        fechaBase = new Date(fechaBase.getFullYear(), fechaBase.getMonth(), fechaBase.getDate() + direccion * salto);
        fechaSeleccionada = new Date(fechaBase);
        actualizarTodo();
    }

    function actualizarTodo() {
        dibujarAgenda();
        dibujarMiniCalendario();
    }

    // === MODAL DE RESERVA ===

    /** Llena el resumen del modal y lo abre. */
    function abrirModalReserva(dia, hora) {
        const opcionMedico = selectorMedico.selectedOptions[0];
        const [horas, minutos] = hora.split(':').map(Number);

        document.getElementById('booking_input_doctor').value = selectorMedico.value;
        document.getElementById('booking_input_fecha').value = formatearFecha(dia);
        document.getElementById('booking_input_hora').value = hora;

        document.getElementById('booking_resumen_medico').textContent = opcionMedico.textContent.trim();
        document.getElementById('booking_resumen_consultorio').textContent = opcionMedico.dataset.consultorio
            ? `Consultorio: ${opcionMedico.dataset.consultorio}`
            : '';
        document.getElementById('booking_resumen_fecha').textContent =
            `${dia.toLocaleDateString('es', { weekday: 'long', day: 'numeric', month: 'long' })} · ${formatearHora(new Date(2000, 0, 1, horas, minutos))}`;

        limpiarAlerta('bookingAlert');
        modalReserva.show();
    }

    formularioReserva.addEventListener('submit', async (evento) => {
        evento.preventDefault();

        const restaurar = bloquearBoton(formularioReserva.querySelector('button[type="submit"]'), 'Agendando...');
        const respuesta = await apiClient.post('create_cita.php', Object.fromEntries(new FormData(formularioReserva)));
        restaurar();

        if (respuesta?.status === 'success') {
            modalReserva.hide();
            formularioReserva.reset();
            mostrarAlerta('agendaAlert', 'success', respuesta.message);
            await cargarHorariosOcupados();
            dibujarAgenda();
            return;
        }

        mostrarAlerta('bookingAlert', 'danger', mensajeDeError(respuesta, 'No se pudo agendar la cita'));
        // Se refresca la agenda por si el horario se ocupó mientras el modal estaba abierto.
        await cargarHorariosOcupados();
        dibujarAgenda();
    });

    // === EVENTOS ===
    selectorMedico.addEventListener('change', dibujarAgenda);
    document.getElementById('btn-prev-week').addEventListener('click', () => moverSemana(-1));
    document.getElementById('btn-next-week').addEventListener('click', () => moverSemana(1));

    window.addEventListener('resize', () => {
        const nuevosDias = calcularDiasVisibles();
        if (nuevosDias !== diasVisibles) {
            diasVisibles = nuevosDias;
            dibujarAgenda();
        }
    });

    // === INICIO ===
    await cargarHorariosOcupados();
    actualizarTodo();
});
