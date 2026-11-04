<?php

/**
 * VISTA: AGENDA DE CITAS
 *
 * El usuario elige un médico y la agenda semanal (calendar.js) muestra los
 * horarios libres de ese médico, coloreados por momento del día como las tapas
 * del pastillero. Al elegir un horario se abre el modal para confirmar la cita.
 *
 * - Paciente: agenda para sí mismo.
 * - Administrador: agenda a nombre del paciente cuyo correo escribe en el modal.
 *
 * Acceso: paciente o administrador.
 */

require_once __DIR__ . '/../config/config.php';

use App\Helpers\Horario;
use App\Http\Sesion;
use App\Models\Doctor;

Sesion::exigirRol(Sesion::ROL_PACIENTE, Sesion::ROL_ADMIN);

// === DATOS ===
// Solo los médicos activos con consultorio pueden recibir citas.
$doctores = (new Doctor())->listarActivos(true);
$esAdmin = Sesion::esAdmin();

// === VISTA ===
$tituloPagina = 'Agendar cita';
$estilosExtra = ['css/calendar.css'];
$scriptsExtra = ['js/calendar.js'];
require __DIR__ . '/../src/views/layout/header.php';
?>
<div class="container ma-pagina">
    <div class="ma-encabezado-pagina">
        <div>
            <h1 class="ma-titulo"><?= $esAdmin ? 'Agendar una cita para un paciente' : 'Agenda tu cita' ?></h1>
            <p class="ma-subtitulo">Elige un médico y después el día y la hora que te queden mejor.</p>
        </div>
    </div>

    <div id="agendaAlert"></div>

    <div class="row g-4">
        <!-- === COLUMNA LATERAL: MÉDICO, MINI CALENDARIO Y LEYENDA === -->
        <aside class="col-lg-4">
            <div class="ma-panel">
                <label for="agenda-medico" class="form-label">1. Médico</label>
                <select id="agenda-medico" class="form-select mb-4">
                    <option value="">Elige un especialista...</option>
                    <?php foreach ($doctores as $doctor): ?>
                        <option value="<?= (int) $doctor['id_doctor'] ?>" data-consultorio="<?= e($doctor['consultorio_nombre']) ?>">
                            Dr(a). <?= e($doctor['nombre'] . ' ' . $doctor['apellido_paterno']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <p class="form-label mb-2">2. Día</p>
                <div id="mini-calendar-wrapper" class="w-100 mb-3"></div>

                <div class="d-flex flex-wrap gap-3 small fw-bold text-secondary" aria-label="Colores de los horarios">
                    <span class="pastillero-momento manana">Mañana</span>
                    <span class="pastillero-momento tarde">Tarde</span>
                    <span class="pastillero-momento noche">Noche</span>
                    <span class="d-inline-flex align-items-center gap-1"><span class="rounded-1 d-inline-block" style="width: 12px; height: 12px; background: var(--ma-alerta-suave);"></span>Ocupado</span>
                </div>
            </div>
        </aside>

        <!-- === AGENDA SEMANAL === -->
        <section class="col-lg-8" aria-label="Horarios de la semana">
            <div class="ma-panel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 id="agenda-current-range" class="fs-5 fw-bold mb-0">Cargando...</h2>
                    <div class="d-flex align-items-center gap-2">
                        <span class="small fw-bold text-secondary me-1 d-none d-sm-inline">3. Hora</span>
                        <button type="button" id="btn-prev-week" class="btn btn-light" aria-label="Semana anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                        <button type="button" id="btn-next-week" class="btn btn-light" aria-label="Semana siguiente"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
                    </div>
                </div>

                <div class="overflow-auto w-100">
                    <!-- Los horarios y días hábiles salen de App\Helpers\Horario (fuente única de verdad). -->
                    <div id="agenda-grid-container" class="agenda-grid" style="min-height: 400px;"
                         data-slots="<?= e(json_encode(Horario::slots())) ?>"
                         data-dias-habiles="<?= e(json_encode(Horario::DIAS_HABILES)) ?>"></div>
                </div>
            </div>
        </section>
    </div>
</div>

<!-- === MODAL: CONFIRMAR CITA === -->
<div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="bookingModalTitulo">Confirma tu cita</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="bookingForm">
                <input type="hidden" name="id_doctor" id="booking_input_doctor">
                <input type="hidden" name="fecha_cita" id="booking_input_fecha">
                <input type="hidden" name="hora_inicio" id="booking_input_hora">
                <div class="modal-body">
                    <div id="bookingAlert"></div>

                    <div class="rounded-4 p-3 mb-3" style="background: var(--ma-superficie-2);">
                        <div class="fw-bold" id="booking_resumen_medico"></div>
                        <div class="small text-secondary" id="booking_resumen_consultorio"></div>
                        <div class="fw-bold text-primary mt-1" id="booking_resumen_fecha"></div>
                    </div>

                    <?php if ($esAdmin): ?>
                        <div class="mb-3">
                            <label for="booking_email" class="form-label">Correo del paciente</label>
                            <input type="email" name="email" id="booking_email" class="form-control" maxlength="100" required>
                        </div>
                    <?php endif; ?>

                    <div>
                        <label for="booking_motivo" class="form-label">Motivo de la consulta</label>
                        <textarea name="motivo" id="booking_motivo" class="form-control" rows="3" maxlength="1000" placeholder="Cuéntanos brevemente qué sientes" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Agendar cita</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../src/views/layout/footer.php'; ?>
