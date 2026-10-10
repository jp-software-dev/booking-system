<?php

/**
 * VISTA: PANEL DE CITAS (ADMIN)
 *
 * Listado paginado y filtrable de todas las citas. Desde aquí el administrador
 * cambia el estado de una cita, la reprograma, la elimina, crea citas a nombre
 * de un paciente y exporta el listado filtrado a PDF.
 *
 * Acceso: administrador.
 */

require_once __DIR__ . '/../config/config.php';

use App\Helpers\AppointmentValidator;
use App\Helpers\FiltroCitas;
use App\Helpers\Horario;
use App\Helpers\Paginacion;
use App\Http\Sesion;
use App\Models\Cita;
use App\Models\Doctor;

Sesion::exigirRol(Sesion::ROL_ADMIN);

// === DATOS ===
$citasModelo = new Cita();
$doctoresActivos = (new Doctor())->listarActivos();
$doctoresConConsultorio = array_filter($doctoresActivos, static fn (array $d): bool => $d['id_consultorio'] !== null);

$filtro = FiltroCitas::desdeConsulta($_GET);
$paginacion = new Paginacion($citasModelo->contarConFiltro($filtro), $_GET);
$citas = $citasModelo->listarConFiltro($filtro, $paginacion);
$totales = $citasModelo->totalesPorEstado();

$urlPdf = '../api/generar_pdf.php?' . http_build_query($filtro->parametrosUrl());
$horarios = Horario::slots();

// === VISTA ===
require __DIR__ . '/../src/views/layout/header.php';
?>
<div class="container-fluid py-4 px-lg-5" style="min-height: 85vh;">
    <!-- === CONTADORES GLOBALES === -->
    <div class="d-flex flex-wrap gap-2 mb-3">
        <span class="badge bg-dark fs-6 fw-bold shadow-sm">TOTAL CITAS: <?= $totales['total'] ?></span>
        <span class="badge bg-warning text-dark fs-6 fw-bold shadow-sm">PENDIENTES: <?= $totales['pendientes'] ?></span>
        <span class="badge bg-info text-dark fs-6 fw-bold shadow-sm">CONFIRMADAS: <?= $totales['confirmadas'] ?></span>
        <span class="badge bg-success fs-6 fw-bold shadow-sm">COMPLETADAS: <?= $totales['completadas'] ?></span>
    </div>

    <div id="panelAlert"></div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h5 class="fw-bold text-dark-blue mb-0"><i class="bi bi-list-task me-2 text-primary"></i>Gestión de Citas</h5>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm fw-medium" data-bs-toggle="modal" data-bs-target="#modalNuevaCitaAdmin">
                    <i class="bi bi-plus-lg me-1"></i> Nueva Cita
                </button>
                <a href="<?= e($urlPdf) ?>" class="btn btn-outline-danger rounded-pill px-4 fw-medium">
                    <i class="bi bi-file-pdf me-1"></i> PDF
                </a>
            </div>
        </div>

        <!-- === FILTROS === -->
        <div class="bg-light p-3 border-bottom">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label for="filtro_id" class="form-label small fw-bold text-secondary mb-1">Buscar ID</label>
                    <input type="number" min="1" name="id_cita" id="filtro_id" class="form-control form-control-sm" placeholder="Ej. 42" value="<?= e($filtro->idCita) ?>">
                </div>
                <div class="col-md-3">
                    <label for="filtro_paciente" class="form-label small fw-bold text-secondary mb-1">Buscar Paciente</label>
                    <input type="text" name="paciente" id="filtro_paciente" class="form-control form-control-sm" placeholder="Nombre del paciente" value="<?= e($filtro->paciente) ?>">
                </div>
                <div class="col-md-3">
                    <label for="filtro_doctor" class="form-label small fw-bold text-secondary mb-1">Médico</label>
                    <select name="doctor" id="filtro_doctor" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($doctoresActivos as $doctor): ?>
                            <option value="<?= (int) $doctor['id_doctor'] ?>" <?= $filtro->doctor === (string) $doctor['id_doctor'] ? 'selected' : '' ?>>
                                Dr. <?= e($doctor['nombre'] . ' ' . $doctor['apellido_paterno']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filtro_estado" class="form-label small fw-bold text-secondary mb-1">Estado</label>
                    <select name="estado" id="filtro_estado" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach (Cita::ESTADOS as $estado): ?>
                            <option value="<?= e($estado) ?>" <?= $filtro->estado === $estado ? 'selected' : '' ?>><?= e($estado) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 fw-medium shadow-sm">Filtrar</button>
                    <a href="admin.php" class="btn btn-light btn-sm rounded-pill px-4 fw-bold border text-uppercase shadow-sm">Limpiar</a>
                </div>
            </form>
        </div>

        <!-- === TABLA DE CITAS === -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Paciente</th>
                        <th>Médico / Consultorio</th>
                        <th>Fecha y Hora</th>
                        <th>Motivo de Consulta</th>
                        <th>Estado</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php foreach ($citas as $cita): ?>
                        <?php $idCita = (int) $cita['id_cita']; ?>
                        <tr>
                            <td data-label="ID" class="ps-4 text-muted fw-bold">#<?= $idCita ?></td>
                            <td data-label="Paciente"><div class="fw-bold text-dark"><?= e($cita['paciente_nombre'] . ' ' . $cita['paciente_apellido']) ?></div></td>
                            <td data-label="Médico">
                                <div class="text-dark">Dr. <?= e($cita['doctor_nombre'] . ' ' . $cita['doctor_apellido']) ?></div>
                                <small class="text-primary fw-medium"><?= e($cita['consultorio_nombre'] ?? 'Sin asignar') ?></small>
                            </td>
                            <td data-label="Fecha y Hora">
                                <div class="fw-medium"><?= fecha_corta($cita['fecha_cita']) ?></div>
                                <small class="text-secondary"><?= hora_corta($cita['hora_inicio']) ?></small>
                            </td>
                            <td data-label="Motivo">
                                <div class="small text-secondary text-wrap text-break" style="max-width: 260px;"><?= e($cita['motivo_consulta']) ?></div>
                            </td>
                            <td data-label="Estado">
                                <select class="form-select form-select-sm status-badge <?= clase_estado_cita($cita['estado_cita']) ?>" style="width: 130px; cursor: pointer;"
                                        data-cambiar-estado="<?= $idCita ?>" data-estado-actual="<?= e($cita['estado_cita']) ?>" aria-label="Estado de la cita #<?= $idCita ?>">
                                    <?php foreach (Cita::ESTADOS as $estado): ?>
                                        <option value="<?= e($estado) ?>" <?= $cita['estado_cita'] === $estado ? 'selected' : '' ?>><?= e($estado) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td data-label="Acciones" class="text-end pe-4">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-light btn-sm text-primary border" title="Reprogramar cita"
                                            data-editar="<?= $idCita ?>" data-fecha="<?= e($cita['fecha_cita']) ?>" data-hora="<?= e(substr($cita['hora_inicio'], 0, 5)) ?>">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button type="button" class="btn btn-light btn-sm text-danger border" title="Eliminar cita" data-eliminar="<?= $idCita ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($citas === []): ?>
                        <tr><td colspan="7" class="text-center text-muted py-5">No se encontraron citas con los filtros aplicados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php
    $itemsMostrados = count($citas);
    require __DIR__ . '/../src/views/partials/pagination.php';
    ?>
</div>

<!-- === MODAL: NUEVA CITA === -->
<div class="modal fade" id="modalNuevaCitaAdmin" tabindex="-1" aria-labelledby="modalNuevaCitaTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold" id="modalNuevaCitaTitulo"><i class="bi bi-plus-circle me-2"></i>Nueva Cita</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formNuevaCita">
                <div class="modal-body p-4">
                    <div id="alertaNuevaCita"></div>
                    <div class="mb-3">
                        <label for="nc_email" class="form-label fw-bold text-secondary small">Correo del paciente</label>
                        <input type="email" name="email" id="nc_email" class="form-control" maxlength="100" required>
                    </div>
                    <div class="mb-3">
                        <label for="nc_doctor" class="form-label fw-bold text-secondary small">Médico especialista</label>
                        <select name="id_doctor" id="nc_doctor" class="form-select" required>
                            <option value="">Seleccione un médico...</option>
                            <?php foreach ($doctoresConConsultorio as $doctor): ?>
                                <option value="<?= (int) $doctor['id_doctor'] ?>">Dr. <?= e($doctor['nombre'] . ' ' . $doctor['apellido_paterno']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label for="nc_fecha" class="form-label fw-bold text-secondary small">Fecha</label>
                            <input type="date" name="fecha_cita" id="nc_fecha" class="form-control" min="<?= e(date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="nc_hora" class="form-label fw-bold text-secondary small">Hora</label>
                            <select name="hora_inicio" id="nc_hora" class="form-select" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($horarios as $slot): ?>
                                    <option value="<?= e($slot) ?>"><?= hora_corta($slot) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="nc_motivo" class="form-label fw-bold text-secondary small">Motivo de la cita</label>
                        <textarea name="motivo" id="nc_motivo" class="form-control" rows="2" maxlength="<?= AppointmentValidator::MAX_MOTIVO ?>" placeholder="Describa los síntomas o el motivo de consulta..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm"><i class="bi bi-check-lg me-1"></i> Crear Cita</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- === MODAL: REPROGRAMAR CITA === -->
<div class="modal fade" id="modalEditarCita" tabindex="-1" aria-labelledby="modalEditarCitaTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold" id="modalEditarCitaTitulo"><i class="bi bi-pencil-square me-2"></i>Reprogramar Cita</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formEditarCita">
                <input type="hidden" name="id_cita" id="edit_id_cita">
                <div class="modal-body p-4">
                    <div id="alertaEditarCita"></div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit_fecha" class="form-label fw-bold text-secondary small">Nueva fecha</label>
                            <input type="date" name="fecha_cita" id="edit_fecha" class="form-control" min="<?= e(date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_hora" class="form-label fw-bold text-secondary small">Nueva hora</label>
                            <select name="hora_inicio" id="edit_hora" class="form-select" required>
                                <?php foreach ($horarios as $slot): ?>
                                    <option value="<?= e($slot) ?>"><?= hora_corta($slot) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
/** Obtiene (o crea) la instancia de un modal. Bootstrap se carga al final de la página. */
const modal = (id) => bootstrap.Modal.getOrCreateInstance(document.getElementById(id));

// === CAMBIO DE ESTADO ===
document.querySelectorAll('[data-cambiar-estado]').forEach((selector) => {
    selector.addEventListener('change', async () => {
        selector.disabled = true;
        const respuesta = await apiClient.post('update_status.php', {
            id_cita: Number(selector.dataset.cambiarEstado),
            estado: selector.value,
        });

        if (respuesta?.status === 'success') {
            location.reload();
            return;
        }

        // Si falló, se regresa el selector al estado que tenía.
        selector.value = selector.dataset.estadoActual;
        selector.disabled = false;
        mostrarAlerta('panelAlert', 'danger', mensajeDeError(respuesta));
    });
});

// === ELIMINAR ===
document.querySelectorAll('[data-eliminar]').forEach((boton) => {
    boton.addEventListener('click', async () => {
        const idCita = Number(boton.dataset.eliminar);
        if (!confirm(`¿Eliminar definitivamente la cita #${idCita}? Esta acción no se puede deshacer.`)) {
            return;
        }

        const respuesta = await apiClient.post('delete_cita.php', { id_cita: idCita });
        if (respuesta?.status === 'success') {
            location.reload();
            return;
        }
        mostrarAlerta('panelAlert', 'danger', mensajeDeError(respuesta));
    });
});

// === NUEVA CITA ===
document.getElementById('formNuevaCita').addEventListener('submit', async (evento) => {
    evento.preventDefault();
    limpiarAlerta('alertaNuevaCita');

    const restaurar = bloquearBoton(evento.target.querySelector('button[type="submit"]'), 'Creando...');
    const respuesta = await apiClient.post('create_cita.php', Object.fromEntries(new FormData(evento.target)));

    if (respuesta?.status === 'success') {
        location.reload();
        return;
    }

    mostrarAlerta('alertaNuevaCita', 'danger', mensajeDeError(respuesta, 'Error al crear la cita'));
    restaurar();
});

// === REPROGRAMAR ===
document.querySelectorAll('[data-editar]').forEach((boton) => {
    boton.addEventListener('click', () => {
        document.getElementById('edit_id_cita').value = boton.dataset.editar;
        document.getElementById('edit_fecha').value = boton.dataset.fecha;

        // Si la hora guardada no está en la rejilla actual, se deja la primera opción.
        const selectorHora = document.getElementById('edit_hora');
        selectorHora.value = boton.dataset.hora;
        if (selectorHora.value !== boton.dataset.hora) {
            selectorHora.selectedIndex = 0;
        }

        limpiarAlerta('alertaEditarCita');
        modal('modalEditarCita').show();
    });
});

document.getElementById('formEditarCita').addEventListener('submit', async (evento) => {
    evento.preventDefault();

    const restaurar = bloquearBoton(evento.target.querySelector('button[type="submit"]'), 'Guardando...');
    const respuesta = await apiClient.post('update_cita_admin.php', Object.fromEntries(new FormData(evento.target)));

    if (respuesta?.status === 'success') {
        location.reload();
        return;
    }

    mostrarAlerta('alertaEditarCita', 'danger', mensajeDeError(respuesta, 'Error al guardar'));
    restaurar();
});
</script>

<?php require __DIR__ . '/../src/views/layout/footer.php'; ?>
