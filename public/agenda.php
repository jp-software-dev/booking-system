<?php

/**
 * VISTA: AGENDA DE CITAS
 *
 * El usuario elige un médico y la agenda semanal (calendar.js) muestra los
 * horarios libres de ese médico. Al elegir un horario se abre el modal para
 * confirmar la cita.
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
$estilosExtra = ['css/calendar.css'];
$scriptsExtra = ['js/calendar.js'];
require __DIR__ . '/../src/views/layout/header.php';
?>
<div class="container py-5" style="min-height: 85vh;">
    <h3 class="fw-bold text-dark-blue mb-4"><?= $esAdmin ? 'Agendar Cita para un Paciente' : 'Reserva tu Cita' ?></h3>

    <div id="agendaAlert"></div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
        <div class="row g-0">
            <!-- === COLUMNA LATERAL: MÉDICO, MINI CALENDARIO Y LEYENDA === -->
            <div class="col-lg-4 col-md-5 bg-light p-4 border-end">
                <label for="agenda-medico" class="form-label fw-bold text-secondary small">1. Elige un médico</label>
                <select id="agenda-medico" class="form-select mb-4">
                    <option value="">Selecciona un especialista...</option>
                    <?php foreach ($doctores as $doctor): ?>
                        <option value="<?= (int) $doctor['id_doctor'] ?>" data-consultorio="<?= e($doctor['consultorio_nombre']) ?>">
                            Dr. <?= e($doctor['nombre'] . ' ' . $doctor['apellido_paterno']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <p class="form-label fw-bold text-secondary small mb-2">2. Elige el día y la hora</p>
                <div id="mini-calendar-wrapper" class="w-100 mb-4 mb-md-0"></div>

                <div class="mt-4">
                    <h6 class="fw-bold text-dark mb-3">Leyenda</h6>
                    <div class="d-flex align-items-center mb-2">
                        <div class="rounded-circle me-2" style="width: 12px; height: 12px; background-color: #10b981;"></div>
                        <span class="small text-secondary">Horario disponible</span>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <div class="rounded-circle me-2" style="width: 12px; height: 12px; background-color: #f3f4f6; border: 1px solid #e5e7eb;"></div>
                        <span class="small text-secondary">No disponible / pasado</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle me-2" style="width: 12px; height: 12px; background-color: #fee2e2; border: 1px solid #fca5a5;"></div>
                        <span class="small text-secondary">Horario reservado</span>
                    </div>
                </div>
            </div>

            <!-- === AGENDA SEMANAL === -->
            <div class="col-lg-8 col-md-7 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h5 id="agenda-current-range" class="fw-bold text-dark mb-0" style="font-size: 1.25rem;">Cargando...</h5>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" id="btn-prev-week" class="btn btn-white rounded-circle shadow-sm border d-flex justify-content-center align-items-center text-dark hover-effect" style="width: 42px; height: 42px;" aria-label="Semana anterior">
                            <i class="bi bi-chevron-left fs-5"></i>
                        </button>
                        <button type="button" id="btn-next-week" class="btn btn-white rounded-circle shadow-sm border d-flex justify-content-center align-items-center text-dark hover-effect" style="width: 42px; height: 42px;" aria-label="Semana siguiente">
                            <i class="bi bi-chevron-right fs-5"></i>
                        </button>
                    </div>
                </div>

                <div class="overflow-auto pb-3 w-100">
                    <!-- Los horarios y días hábiles salen de App\Helpers\Horario (fuente única de verdad). -->
                    <div id="agenda-grid-container" class="agenda-grid" style="min-height: 400px;"
                         data-slots="<?= e(json_encode(Horario::slots())) ?>"
                         data-dias-habiles="<?= e(json_encode(Horario::DIAS_HABILES)) ?>"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- === MODAL: CONFIRMAR CITA === -->
<div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold" id="bookingModalTitulo"><i class="bi bi-calendar-plus me-2"></i> Confirmar Cita</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="bookingForm">
                <input type="hidden" name="id_doctor" id="booking_input_doctor">
                <input type="hidden" name="fecha_cita" id="booking_input_fecha">
                <input type="hidden" name="hora_inicio" id="booking_input_hora">
                <div class="modal-body p-4">
                    <div id="bookingAlert"></div>

                    <div class="bg-light rounded-3 p-3 mb-3">
                        <div class="fw-bold text-dark" id="booking_resumen_medico"></div>
                        <div class="small text-secondary" id="booking_resumen_consultorio"></div>
                        <div class="small text-primary fw-medium mt-1" id="booking_resumen_fecha"></div>
                    </div>

                    <?php if ($esAdmin): ?>
                        <div class="mb-3">
                            <label for="booking_email" class="form-label fw-bold text-secondary small">Correo del paciente</label>
                            <input type="email" name="email" id="booking_email" class="form-control" maxlength="100" required>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="booking_motivo" class="form-label fw-bold text-secondary small">Motivo de la consulta</label>
                        <textarea name="motivo" id="booking_motivo" class="form-control" rows="3" maxlength="1000" placeholder="Escribe brevemente los síntomas..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">Agendar Cita</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../src/views/layout/footer.php'; ?>
