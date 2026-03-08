// MOTOR DEL CALENDARIO: Script principal que gestiona la lógica de navegación semanal, renderizado de horarios y el proceso de reserva asíncrono.
document.addEventListener('DOMContentLoaded', async function() {
    
    // CAPTURA DE NODOS: Identifica los elementos del DOM necesarios para inyectar la cuadrícula de horarios, el mini-calendario y los controles de navegación.
    const gridContainer = document.getElementById('agenda-grid-container');
    const monthDisplay = document.getElementById('current-month-display');
    const miniCalWrapper = document.getElementById('mini-calendar-wrapper');
    const prevWeekBtn = document.getElementById('btn-prev-week');
    const nextWeekBtn = document.getElementById('btn-next-week');
    const bookingForm = document.getElementById('bookingForm');

    // PROTECCIÓN DE EJECUCIÓN: Detiene el script si no se encuentra el contenedor principal, evitando errores en páginas que no requieren calendario.
    if (!gridContainer) return;

    // CONSTANTES DE TIEMPO: Define los nombres de días, meses y la lista estricta de intervalos de 30 minutos disponibles para la consulta.
    const DAYS = ['DOM', 'LUN', 'MAR', 'MIÉ', 'JUE', 'VIE', 'SÁB'];
    const MONTHS = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const TIME_SLOTS = [
        '08:00', '08:30', '09:00', '09:30', '10:00', '10:30',
        '11:00', '11:30', '12:00', '12:30', '13:00', '13:30',
        '14:00', '14:30', '15:00', '15:30', '16:00', '16:30',
        '17:00', '17:30', '18:00', '18:30', '19:00', '19:30',
        '20:00', '20:30', '21:00', '21:30', '22:00'
    ];

    // ESTADO DE LA APP: Almacena la fecha de referencia para la navegación y la lista de citas ocupadas recuperada del servidor.
    let baseDate = new Date();
    let citasOcupadas = [];

    // NOTIFICADOR VISUAL: Crea y muestra globos de texto (toasts) dinámicos para informar al usuario sobre el éxito o error de sus acciones.
    function showToast(message, type = 'success') {
        let toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            document.body.appendChild(toastContainer);
        }
        const toastId = 'toast-' + Date.now();
        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        toastContainer.insertAdjacentHTML('beforeend', toastHtml);
        const toastEl = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
        toast.show();
        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    }

    // CONSUMO DE API: Solicita al backend la lista de citas activas para marcar los horarios ocupados en la interfaz visual.
    async function cargarCitas() {
        try {
            const data = await apiClient.get('get_citas.php');
            if (Array.isArray(data)) {
                // FORMATEO DE FECHAS: Limpia las cadenas de tiempo recibidas para facilitar la comparación rápida durante el renderizado.
                citasOcupadas = data.map(c => c.start.substring(0, 16));
            }
        } catch (e) {
            console.error('Error al cargar citas:', e);
        }
    }

    // LÓGICA DE CALENDARIO: Calcula el primer día de la semana (lunes) basándose en cualquier fecha proporcionada por el usuario.
    function getMonday(date) {
        const d = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        const day = d.getDay();
        const diff = d.getDate() - day + (day === 0 ? -6 : 1);
        return new Date(d.getFullYear(), d.getMonth(), diff);
    }

    // RENDERIZADOR SEMANAL: Genera dinámicamente las 7 columnas de la agenda y pobla cada una con botones de tiempo (slots) disponibles.
    function renderAgenda(date) {
        gridContainer.innerHTML = '';
        const monday = getMonday(date);
        monthDisplay.innerHTML = `<span>${MONTHS[monday.getMonth()]}</span> <small class="text-muted fw-light">${monday.getFullYear()}</small>`;

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        for (let i = 0; i < 7; i++) {
            const colDate = new Date(monday.getFullYear(), monday.getMonth(), monday.getDate() + i);
            const col = document.createElement('div');
            col.className = 'agenda-col';
            if (colDate.toDateString() === today.toDateString()) {
                col.classList.add('active'); // RESALTADO: Marca visualmente el día actual del paciente.
            }
            col.innerHTML = `
                <div class="day-label">${DAYS[colDate.getDay()]}</div>
                <div class="date-circle">${colDate.getDate()}</div>
            `;

            const slotsContainer = document.createElement('div');
            slotsContainer.className = 'w-100 d-flex flex-column align-items-center';

            // RESTRICCIÓN TEMPORAL: Bloquea completamente la selección de días que ya han pasado.
            const isPastDay = colDate < today;

            if (isPastDay) {
                slotsContainer.innerHTML = '<div class="opacity-25 py-5"><i class="bi bi-lock-fill"></i></div>';
            } else {
                TIME_SLOTS.forEach(time => {
                    const [h, m] = time.split(':').map(Number);
                    const slotDateTime = new Date(colDate);
                    slotDateTime.setHours(h, m, 0, 0);
                    const isoStr = `${colDate.getFullYear()}-${String(colDate.getMonth()+1).padStart(2, '0')}-${String(colDate.getDate()).padStart(2, '0')}T${time}`;
                    const isBooked = citasOcupadas.includes(isoStr);
                    const now = new Date();
                    const isPastHour = slotDateTime < now;

                    // GENERACIÓN DE BOTÓN: Crea el botón de horario aplicando clases de estado (Ocupado, Pasado o Disponible).
                    const btn = document.createElement('button');
                    btn.className = 'time-slot';
                    if (isBooked || isPastHour) {
                        btn.disabled = true;
                        if (isBooked) btn.classList.add('booked');
                        btn.innerHTML = isBooked ? '<i class="bi bi-calendar-x me-1"></i> Ocupado' : '—';
                    } else {
                        btn.innerText = formatTimeAMPM(time);
                        btn.onclick = () => openBookingModal(colDate, time); // EVENTO DE CLICK: Dispara el modal de confirmación.
                    }
                    slotsContainer.appendChild(btn);
                });
            }
            col.appendChild(slotsContainer);
            gridContainer.appendChild(col);
        }
    }

    // MINI CALENDARIO: Renderiza una vista mensual compacta en el sidebar para permitir saltos rápidos de fecha.
    function renderMiniCalendar(date) {
        if (!miniCalWrapper) return;
        const year = date.getFullYear();
        const month = date.getMonth();
        const firstDay = new Date(year, month, 1).getDay();
        const lastDate = new Date(year, month + 1, 0).getDate();
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        let html = `
            <div class="mini-cal">
                <div class="mini-cal-header">
                    <span>${MONTHS[month]} ${year}</span>
                    <div>
                        <i class="bi bi-chevron-left" id="mini-prev-month"></i>
                        <i class="bi bi-chevron-right ms-2" id="mini-next-month"></i>
                    </div>
                </div>
                <div class="mini-cal-grid">
                    ${['D', 'L', 'M', 'M', 'J', 'V', 'S'].map(d => `<div class="mini-cal-day">${d}</div>`).join('')}
        `;

        // RELLENO DE ESPACIOS: Genera huecos vacíos para alinear correctamente los días de la semana.
        for (let i = 0; i < firstDay; i++) {
            html += '<div></div>';
        }

        for (let d = 1; d <= lastDate; d++) {
            const cellDate = new Date(year, month, d);
            const isActive = cellDate.toDateString() === date.toDateString();
            const isToday = cellDate.toDateString() === today.toDateString();
            const isPast = cellDate < today;
            const activeClass = isActive ? 'active' : (isToday ? 'today' : '');
            const pastClass = isPast ? 'text-muted opacity-50' : '';
            const dataAttr = !isPast ? `data-date="${year}-${month+1}-${d}"` : '';
            html += `<div class="mini-cal-date ${activeClass} ${pastClass}" ${dataAttr}>${d}</div>`;
        }
        html += `</div></div>`;

        miniCalWrapper.innerHTML = html;

        // EVENTOS MINI CAL: Permite cambiar la semana principal al hacer clic en un día específico del mes.
        document.querySelectorAll('.mini-cal-date[data-date]').forEach(el => {
            el.addEventListener('click', (e) => {
                const [y, m, d] = e.target.dataset.date.split('-').map(Number);
                baseDate = new Date(y, m-1, d);
                updateAll();
            });
        });

        document.getElementById('mini-prev-month')?.addEventListener('click', () => {
            baseDate.setMonth(baseDate.getMonth() - 1);
            updateAll();
        });
        document.getElementById('mini-next-month')?.addEventListener('click', () => {
            baseDate.setMonth(baseDate.getMonth() + 1);
            updateAll();
        });
    }

    // SINCRONIZADOR: Función central que refresca ambos componentes del calendario cuando cambia la fecha base.
    function updateAll() {
        renderAgenda(baseDate);
        renderMiniCalendar(baseDate);
    }

    // FORMATO DE USUARIO: Transforma las horas militares (24h) a un formato amigable de 12 horas con AM/PM.
    function formatTimeAMPM(time) {
        const [hour, minute] = time.split(':').map(Number);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const hour12 = hour % 12 || 12;
        return `${hour12}:${minute.toString().padStart(2, '0')} ${ampm}`;
    }

    // PREPARACIÓN DE RESERVA: Inyecta los datos de fecha y hora seleccionados en los campos ocultos del formulario modal.
    function openBookingModal(date, time) {
        const localDate = new Date(date.getTime() - (date.getTimezoneOffset() * 60000));
        const fechaStr = localDate.toISOString().split('T')[0];
        const displayDate = `${DAYS[date.getDay()]}, ${date.getDate()} de ${MONTHS[date.getMonth()]} ${date.getFullYear()}`;
        const displayTime = formatTimeAMPM(time);

        document.getElementById('form_fecha').value = fechaStr;
        document.getElementById('form_hora').value = time;
        document.getElementById('modal-date-text').innerText = displayDate;
        document.getElementById('modal-time-text').innerText = displayTime;

        const modal = new bootstrap.Modal(document.getElementById('bookingModal'));
        modal.show();
    }

    // NAVEGACIÓN SEMANAL: Actualiza la fecha base saltando 7 días hacia atrás o adelante.
    prevWeekBtn?.addEventListener('click', () => {
        baseDate.setDate(baseDate.getDate() - 7);
        updateAll();
    });

    nextWeekBtn?.addEventListener('click', () => {
        baseDate.setDate(baseDate.getDate() + 7);
        updateAll();
    });

    // INICIO DE CARGA: Ejecuta la recuperación inicial de datos y dibuja la interfaz por primera vez.
    await cargarCitas();
    updateAll();

    // PROCESAMIENTO DE RESERVA: Gestiona el envío asíncrono del formulario de cita, maneja estados de carga y refresca el calendario tras el éxito.
    if (bookingForm) {
        bookingForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            btn.disabled = true; // PREVENCIÓN: Evita múltiples clics mientras se procesa la solicitud.
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Agendando...';

            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData.entries());

            try {
                // ENVÍO DE DATOS: Utiliza el cliente API para registrar la nueva cita en el backend de MediAgenda.
                const res = await apiClient.post('create_cita.php', data);
                if (res && res.status === 'success') {
                    // FINALIZACIÓN: Oculta el modal, notifica éxito y actualiza la cuadrícula para mostrar el nuevo espacio ocupado.
                    bootstrap.Modal.getInstance(document.getElementById('bookingModal')).hide();
                    showToast('✅ Cita agendada exitosamente', 'success');
                    await cargarCitas();
                    updateAll();
                    e.target.reset();
                } else {
                    showToast('❌ ' + (res ? res.message : 'Error al agendar'), 'danger');
                }
            } catch (error) {
                showToast('❌ Error de conexión', 'danger');
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Agendar cita';
            }
        });
    }
});