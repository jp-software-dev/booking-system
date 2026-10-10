<?php

/**
 * VISTA: HISTORIAL DE CITAS DEL PACIENTE
 *
 * Dos secciones:
 *   - Próximas citas activas: el paciente puede reprogramarlas o cancelarlas
 *     (si están pendientes o confirmadas).
 *   - Atenciones pasadas (completadas o canceladas): puede quitarlas de su vista.
 *
 * Acceso: paciente.
 */

require_once __DIR__ . '/../config/config.php';

use App\Helpers\AppointmentValidator;
use App\Helpers\Horario;
use App\Http\Sesion;
use App\Models\Cita;

Sesion::exigirRol(Sesion::ROL_PACIENTE);

// === DATOS ===
$citas = (new Cita())->listarPorPaciente((int) Sesion::usuarioId(), false);

$citasActivas = array_filter($citas, static fn (array $c): bool => in_array($c['estado_cita'], Cita::ESTADOS_ACTIVOS, true));
$citasPasadas = array_filter($citas, static fn (array $c): bool => !in_array($c['estado_cita'], Cita::ESTADOS_ACTIVOS, true));

// === VISTA ===
require __DIR__ . '/../src/views/layout/header.php';
?>
<div class="container py-5" style="min-height: 85vh;">
    <div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold text-dark-blue mb-2">Mi Historial de Citas</h2>
            <p class="text-secondary mb-0">Consulta el estado de tus citas y tus atenciones completadas.</p>
        </div>
        <a href="agenda.php" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm">
            <i class="bi bi-calendar-plus me-2"></i>Reservar Nueva Cita
        </a>
    </div>

    <div id="historialAlert"></div>

    <!-- === PRÓXIMAS CITAS ACTIVAS === -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="card-header bg-white border-bottom py-3 px-4">
            <h5 class="fw-bold text-dark-blue mb-0"><i class="bi bi-calendar-check text-primary me-2"></i> Próximas Citas Activas</h5>
        </div>
        <div class="table-responsive border-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Fecha y Hora</th>
                        <th>Médico Especialista</th>
                        <th>Consultorio</th>
                        <th class="d-none d-md-table-cell">Motivo de Consulta</th>
                        <th>Estado</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php foreach ($citasActivas as $cita): ?>
                        <?php $modificable = in_array($cita['estado_cita'], Cita::ESTADOS_MODIFICABLES, true); ?>
                        <tr>
                            <td class="ps-4" data-label="Fecha y hora">
                                <div class="text-dark fw-medium"><?= fecha_corta($cita['fecha_cita']) ?></div>
                                <small class="text-secondary"><?= hora_corta($cita['hora_inicio']) ?></small>
                            </td>
                            <td class="text-dark fw-medium" data-label="Médico">Dr. <?= e($cita['doctor_nombre'] . ' ' . $cita['doctor_apellido']) ?></td>
                            <td class="text-secondary" data-label="Consultorio"><?= e($cita['consultorio_nombre'] ?? 'Por asignar') ?></td>
                            <td class="d-none d-md-table-cell text-secondary text-truncate" style="max-width: 200px;" title="<?= e($cita['motivo_consulta']) ?>"><?= e($cita['motivo_consulta']) ?></td>
                            <td data-label="Estado"><span class="badge rounded-pill <?= clase_estado_cita($cita['estado_cita']) ?> px-3 py-1"><?= e($cita['estado_cita']) ?></span></td>
                            <td class="text-end pe-4">
                                <?php if ($modificable): ?>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-accion="reprogramar"
                                                data-id="<?= (int) $cita['id_cita'] ?>"
                                                data-fecha="<?= e($cita['fecha_cita']) ?>"
                                                data-hora="<?= e(substr($cita['hora_inicio'], 0, 5)) ?>"
                                                data-motivo="<?= e($cita['motivo_consulta']) ?>">
                                            <i class="bi bi-pencil-square me-1"></i>Reprogramar
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-accion="cancelar" data-id="<?= (int) $cita['id_cita'] ?>">
                                            <i class="bi bi-x-circle me-1"></i>Cancelar
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">Sin acciones</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($citasActivas === []): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No tienes citas programadas actualmente.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- === ATENCIONES PASADAS === -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 px-4">
            <h5 class="fw-bold text-dark-blue mb-0"><i class="bi bi-clock-history text-secondary me-2"></i> Historial de Atenciones Pasadas</h5>
        </div>
        <div class="table-responsive border-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Fecha y Hora</th>
                        <th>Médico Especialista</th>
                        <th>Consultorio</th>
                        <th class="d-none d-md-table-cell">Motivo de Consulta</th>
                        <th class="text-end">Estado Final</th>
                        <th class="text-end pe-4"></th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php foreach ($citasPasadas as $cita): ?>
                        <tr>
                            <td class="ps-4" data-label="Fecha y hora">
                                <div class="text-dark fw-medium"><?= fecha_corta($cita['fecha_cita']) ?></div>
                                <small class="text-secondary"><?= hora_corta($cita['hora_inicio']) ?></small>
                            </td>
                            <td class="text-dark fw-medium" data-label="Médico">Dr. <?= e($cita['doctor_nombre'] . ' ' . $cita['doctor_apellido']) ?></td>
                            <td class="text-secondary" data-label="Consultorio"><?= e($cita['consultorio_nombre'] ?? 'N/A') ?></td>
                            <td class="d-none d-md-table-cell text-secondary text-truncate" style="max-width: 200px;" title="<?= e($cita['motivo_consulta']) ?>"><?= e($cita['motivo_consulta']) ?></td>
                            <td class="text-end" data-label="Estado"><span class="badge rounded-pill <?= clase_estado_cita($cita['estado_cita']) ?> px-3 py-1"><?= e($cita['estado_cita']) ?></span></td>
                            <td class="text-end pe-4">
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-circle" title="Quitar de mi historial" data-accion="ocultar" data-id="<?= (int) $cita['id_cita'] ?>">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($citasPasadas === []): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No hay registros en tu historial de atención.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- === MODAL: REPROGRAMAR CITA === -->
<div class="modal fade" id="modalReprogramar" tabindex="-1" aria-labelledby="modalReprogramarTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold" id="modalReprogramarTitulo"><i class="bi bi-pencil-square me-2"></i>Reprogramar Cita</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formReprogramar">
                <input type="hidden" name="id_cita" id="rep_id_cita">
                <div class="modal-body p-4">
                    <div id="reprogramarAlert"></div>
                    <p class="small text-secondary">Al reprogramar, la cita vuelve a quedar pendiente de confirmación.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="rep_fecha" class="form-label fw-bold text-secondary small">Nueva fecha</label>
                            <input type="date" name="fecha_cita" id="rep_fecha" class="form-control" min="<?= e(date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="rep_hora" class="form-label fw-bold text-secondary small">Nueva hora</label>
                            <select name="hora_inicio" id="rep_hora" class="form-select" required>
                                <?php foreach (Horario::slots() as $slot): ?>
                                    <option value="<?= e($slot) ?>"><?= hora_corta($slot) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="rep_motivo" class="form-label fw-bold text-secondary small">Motivo de la consulta</label>
                            <textarea name="motivo" id="rep_motivo" class="form-control" rows="3" maxlength="<?= AppointmentValidator::MAX_MOTIVO ?>"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- === MODAL DE CONFIRMACIÓN (cancelar cita / quitar del historial) === -->
<div class="modal fade" id="modalConfirmar" tabindex="-1" aria-labelledby="modalConfirmarTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-danger text-white rounded-top-4">
                <h5 class="modal-title fw-bold" id="modalConfirmarTitulo"><i class="bi bi-exclamation-triangle-fill me-2"></i><span id="confirmarTitulo"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <p class="fw-medium mb-0" id="confirmarTexto"></p>
                <div id="confirmarAlert" class="mt-3"></div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Volver</button>
                <button type="button" class="btn btn-danger fw-bold" id="btnConfirmarAccion">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<script>
// === CONFIGURACIÓN DE LAS ACCIONES QUE PIDEN CONFIRMACIÓN ===
const accionesConfirmables = {
    cancelar: {
        titulo: 'Cancelar cita',
        texto: '¿Seguro que deseas cancelar esta cita? El horario quedará libre para otros pacientes.',
        endpoint: 'cancel_cita_user.php',
    },
    ocultar: {
        titulo: 'Quitar del historial',
        texto: 'El registro dejará de aparecer en tu historial. ¿Deseas continuar?',
        endpoint: 'delete_historial_paciente.php',
    },
};

let accionPendiente = null; // { endpoint, idCita }

// Los modales se obtienen al usarlos: Bootstrap se carga al final de la página (footer.php).
const modal = (id) => bootstrap.Modal.getOrCreateInstance(document.getElementById(id));

// === BOTONES DE LAS TABLAS ===
document.querySelectorAll('[data-accion]').forEach((boton) => {
    boton.addEventListener('click', () => {
        const { accion, id } = boton.dataset;

        if (accion === 'reprogramar') {
            document.getElementById('rep_id_cita').value = id;
            document.getElementById('rep_fecha').value = boton.dataset.fecha;
            document.getElementById('rep_hora').value = boton.dataset.hora;
            document.getElementById('rep_motivo').value = boton.dataset.motivo;
            limpiarAlerta('reprogramarAlert');
            modal('modalReprogramar').show();
            return;
        }

        const configuracion = accionesConfirmables[accion];
        accionPendiente = { endpoint: configuracion.endpoint, idCita: Number(id) };
        document.getElementById('confirmarTitulo').textContent = configuracion.titulo;
        document.getElementById('confirmarTexto').textContent = configuracion.texto;
        limpiarAlerta('confirmarAlert');
        modal('modalConfirmar').show();
    });
});

// === REPROGRAMAR ===
document.getElementById('formReprogramar').addEventListener('submit', async (evento) => {
    evento.preventDefault();

    const restaurar = bloquearBoton(evento.target.querySelector('button[type="submit"]'), 'Guardando...');
    const respuesta = await apiClient.post('update_cita_user.php', Object.fromEntries(new FormData(evento.target)));

    if (respuesta?.status === 'success') {
        location.reload();
        return;
    }

    mostrarAlerta('reprogramarAlert', 'danger', mensajeDeError(respuesta));
    restaurar();
});

// === CANCELAR / QUITAR DEL HISTORIAL ===
document.getElementById('btnConfirmarAccion').addEventListener('click', async (evento) => {
    if (!accionPendiente) {
        return;
    }

    const restaurar = bloquearBoton(evento.currentTarget, 'Procesando...');
    const respuesta = await apiClient.post(accionPendiente.endpoint, { id_cita: accionPendiente.idCita });

    if (respuesta?.status === 'success') {
        location.reload();
        return;
    }

    mostrarAlerta('confirmarAlert', 'danger', mensajeDeError(respuesta));
    restaurar();
});
</script>

<?php require __DIR__ . '/../src/views/layout/footer.php'; ?>
