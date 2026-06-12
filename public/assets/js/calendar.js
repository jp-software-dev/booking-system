document.addEventListener('DOMContentLoaded', async function() {
    const gridContainer = document.getElementById('agenda-grid-container');
    const miniCalWrapper = document.getElementById('mini-calendar-wrapper');
    const bookingForm = document.getElementById('bookingForm');
    if (!gridContainer) return;

    const DAYS = ['DOM', 'LUN', 'MAR', 'MIÉ', 'JUE', 'VIE', 'SÁB'];
    const MONTHS = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    
    const TIME_SLOTS = [
        '07:00', '07:30', '08:00', '08:30', '09:00', '09:30', '10:00', '10:30',
        '11:00', '11:30', '12:00', '12:30', '13:00', '13:30', '14:00', '14:30', 
        '15:00', '15:30', '16:00', '16:30', '17:00', '17:30', '18:00', '18:30',
        '19:00', '19:30', '20:00', '20:30', '21:00', '21:30', '22:00', '22:30',
        '23:00', '23:30'
    ];

    let baseDate = new Date();
    let selectedDate = new Date();
    let citasOcupadas = [];
    
    // LÓGICA GOOGLE CALENDAR RESPONSIVA
    let visibleDays = window.innerWidth <= 768 ? 2 : 6;

    // Escucha si voltean la pantalla
    window.addEventListener('resize', () => {
        const newVis = window.innerWidth <= 768 ? 2 : 6;
        if(newVis !== visibleDays) {
            visibleDays = newVis;
            updateAll();
        }
    });

    async function cargarCitas() {
        try {
            const data = await fetch('../api/get_citas.php').then(r => r.json());
            if (Array.isArray(data)) citasOcupadas = data.map(c => c.start.substring(0, 16));
        } catch (e) { console.error("Error cargando citas", e); }
    }

    function renderAgenda(date) {
        gridContainer.innerHTML = '';
        
        // Ajuste inteligente del lunes (si es móvil arranca el día actual, si es PC arranca el lunes de la semana)
        let startGridDate;
        if (visibleDays === 2) {
            startGridDate = new Date(date);
        } else {
            startGridDate = new Date(date.getFullYear(), date.getMonth(), date.getDate() - date.getDay() + (date.getDay()===0?-6:1));
        }
        
        const titleElement = document.getElementById('agenda-current-range');
        if (titleElement) {
            titleElement.innerHTML = `${MONTHS[startGridDate.getMonth()]} <span class="fw-normal">${startGridDate.getFullYear()}</span>`;
        }
        
        const today = new Date(); today.setHours(0,0,0,0);

        for (let i = 0; i < visibleDays; i++) {
            const colDate = new Date(startGridDate.getFullYear(), startGridDate.getMonth(), startGridDate.getDate() + i);
            const col = document.createElement('div'); col.className = 'agenda-col';
            if (colDate.toDateString() === today.toDateString()) col.classList.add('today-col');
            if (colDate.toDateString() === selectedDate.toDateString()) col.classList.add('active');
            
            const slotsContainer = document.createElement('div'); slotsContainer.className = 'w-100 d-flex flex-column align-items-center';
            if (colDate < today) {
                slotsContainer.innerHTML = '<div class="opacity-25 py-5"><i class="bi bi-dash-lg fs-3"></i></div>';
            } else {
                TIME_SLOTS.forEach(time => {
                    const slotDateTime = new Date(colDate); slotDateTime.setHours(...time.split(':'));
                    const isoStr = `${colDate.getFullYear()}-${String(colDate.getMonth()+1).padStart(2, '0')}-${String(colDate.getDate()).padStart(2, '0')}T${time}`;
                    const isBooked = citasOcupadas.includes(isoStr);
                    const btn = document.createElement('button'); btn.className = 'time-slot';
                    btn.type = 'button';
                    
                    if (slotDateTime < new Date()) { btn.disabled = true; btn.innerHTML = '—'; }
                    else if (isBooked) { btn.disabled = true; btn.classList.add('booked'); btn.innerHTML = '<i class="bi bi-x-circle d-none d-md-inline me-1"></i>Ocupado'; }
                    else {
                        btn.innerText = new Intl.DateTimeFormat('en-US', {hour: 'numeric', minute: 'numeric', hour12: true}).format(slotDateTime);
                        btn.onclick = () => { if(btn.disabled || btn.classList.contains('booked')) return; openBookingModal(colDate, time); };
                    }
                    slotsContainer.appendChild(btn);
                });
            }
            col.innerHTML = `<div class="day-label">${colDate.toLocaleString('es',{weekday:'short'})}</div><div class="date-circle">${colDate.getDate()}</div>`;
            col.appendChild(slotsContainer); gridContainer.appendChild(col);
        }
    }

    function renderMiniCalendar(date) {
        if (!miniCalWrapper) return;
        const year = date.getFullYear(), month = date.getMonth();
        const firstDay = new Date(year, month, 1).getDay(), lastDate = new Date(year, month + 1, 0).getDate();
        const today = new Date(); today.setHours(0,0,0,0);

        let html = `<div class="mini-cal"><div class="mini-cal-header"><span id="mini-month-year">${MONTHS[month]} ${year}</span><div><i class="bi bi-chevron-left" id="mini-prev-month"></i><i class="bi bi-chevron-right ms-2" id="mini-next-month"></i></div></div><div class="mini-cal-grid">${['D', 'L', 'M', 'M', 'J', 'V', 'S'].map(d => `<div class="mini-cal-day">${d}</div>`).join('')}`;
        for (let i = 0; i < firstDay; i++) html += '<div></div>';
        for (let d = 1; d <= lastDate; d++) {
            const cellDate = new Date(year, month, d);
            const isActive = cellDate.toDateString() === selectedDate.toDateString(), isToday = cellDate.toDateString() === today.toDateString(), isPast = cellDate < today, isSunday = cellDate.getDay() === 0;
            html += `<div class="mini-cal-date ${isActive?'active':''} ${isToday?'today':''} ${isPast||isSunday?'text-muted opacity-50':''}" ${(!isPast&&!isSunday)?`data-date="${year}-${month+1}-${d}"`:''}>${d}</div>`;
        }
        html += `</div></div>`;
        miniCalWrapper.innerHTML = html;

        document.querySelectorAll('.mini-cal-date[data-date]').forEach(el => {
            el.addEventListener('click', (e) => {
                const [y, m, d] = e.target.dataset.date.split('-').map(Number);
                const clicked = new Date(y, m - 1, d);
                selectedDate = new Date(clicked);
                baseDate = new Date(clicked);
                updateAll();
            });
        });
        
        document.getElementById('mini-prev-month').onclick = () => { baseDate.setMonth(baseDate.getMonth() - 1); updateAll(); };
        document.getElementById('mini-next-month').onclick = () => { baseDate.setMonth(baseDate.getMonth() + 1); updateAll(); };
    }

    function openBookingModal(date, time) {
        document.getElementById('booking_input_fecha').value = date.toISOString().split('T')[0];
        document.getElementById('booking_input_hora').value = time;
        new bootstrap.Modal(document.getElementById('bookingModal')).show();
    }

    function updateAll() { renderAgenda(baseDate); renderMiniCalendar(baseDate); }

    const prevWeekBtn = document.getElementById('btn-prev-week');
    if (prevWeekBtn) prevWeekBtn.onclick = () => { baseDate.setDate(baseDate.getDate() - visibleDays); selectedDate.setDate(selectedDate.getDate() - visibleDays); updateAll(); };
    
    const nextWeekBtn = document.getElementById('btn-next-week');
    if (nextWeekBtn) nextWeekBtn.onclick = () => { baseDate.setDate(baseDate.getDate() + visibleDays); selectedDate.setDate(selectedDate.getDate() + visibleDays); updateAll(); };

    await cargarCitas(); 
    updateAll();

    if (bookingForm) {
        bookingForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            btn.disabled = true; btn.innerHTML = 'Agendando...';
            try {
                const formData = new FormData(e.target);
                const bodyData = Object.fromEntries(formData.entries());
                const res = await fetch('../api/create_cita.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(bodyData) }).then(r => r.json());
                
                if (res && res.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('bookingModal')).hide();
                    alert('Cita agendada exitosamente');
                    await cargarCitas(); updateAll(); e.target.reset();
                    setTimeout(() => location.reload(), 1500);
                } else alert(res ? res.message : 'Error al agendar');
            } catch (error) { alert('Error de conexión'); } 
            finally { btn.disabled = false; btn.innerHTML = 'Agendar Cita'; }
        });
    }
});