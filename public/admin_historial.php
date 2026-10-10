<?php

/**
 * VISTA: HISTORIAL DE CITAS CERRADAS (ADMIN)
 *
 * Listado paginado y filtrable de las citas completadas o canceladas, con
 * exportación a PDF de los resultados filtrados.
 *
 * Acceso: administrador.
 */

require_once __DIR__ . '/../config/config.php';

use App\Helpers\FiltroCitas;
use App\Helpers\Paginacion;
use App\Http\Sesion;
use App\Models\Cita;
use App\Models\Doctor;

Sesion::exigirRol(Sesion::ROL_ADMIN);

// === DATOS ===
$citasModelo = new Cita();
// Se listan todos los médicos (también los dados de baja), porque aparecen en el historial.
$doctores = (new Doctor())->listarTodos();

$filtro = FiltroCitas::desdeConsulta($_GET, true);
$paginacion = new Paginacion($citasModelo->contarConFiltro($filtro), $_GET);
$citas = $citasModelo->listarConFiltro($filtro, $paginacion);

$urlPdf = '../api/generar_pdf.php?' . http_build_query($filtro->parametrosUrl());

// === VISTA ===
require __DIR__ . '/../src/views/layout/header.php';
?>
<div class="container-fluid py-4 px-lg-5" style="min-height: 85vh;">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h5 class="fw-bold text-dark-blue mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Historial de Citas Pasadas</h5>
            <a href="<?= e($urlPdf) ?>" class="btn btn-outline-danger rounded-pill px-4 fw-medium">
                <i class="bi bi-file-pdf me-1"></i> PDF
            </a>
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
                        <?php foreach ($doctores as $doctor): ?>
                            <option value="<?= (int) $doctor['id_doctor'] ?>" <?= $filtro->doctor === (string) $doctor['id_doctor'] ? 'selected' : '' ?>>
                                Dr. <?= e($doctor['nombre'] . ' ' . $doctor['apellido_paterno']) ?><?= $doctor['estado'] !== Doctor::ESTADO_ACTIVO ? ' (baja)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filtro_estado" class="form-label small fw-bold text-secondary mb-1">Estado</label>
                    <select name="estado" id="filtro_estado" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach (FiltroCitas::ESTADOS_HISTORIAL as $estado): ?>
                            <option value="<?= e($estado) ?>" <?= $filtro->estado === $estado ? 'selected' : '' ?>><?= e($estado) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 fw-medium shadow-sm">Filtrar</button>
                    <a href="admin_historial.php" class="btn btn-light btn-sm rounded-pill px-3 fw-medium border">Limpiar</a>
                </div>
            </form>
        </div>

        <!-- === TABLA === -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Paciente</th>
                        <th>Médico Especialista</th>
                        <th>Fecha y Hora</th>
                        <th>Motivo</th>
                        <th class="text-end pe-4">Estado</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php foreach ($citas as $cita): ?>
                        <tr>
                            <td class="ps-4 text-muted fw-bold" data-label="ID">#<?= (int) $cita['id_cita'] ?></td>
                            <td data-label="Paciente"><div class="fw-bold text-dark"><?= e($cita['paciente_nombre'] . ' ' . $cita['paciente_apellido']) ?></div></td>
                            <td data-label="Médico"><div class="text-dark">Dr. <?= e($cita['doctor_nombre'] . ' ' . $cita['doctor_apellido']) ?></div></td>
                            <td data-label="Fecha y hora">
                                <div class="fw-medium"><?= fecha_corta($cita['fecha_cita']) ?></div>
                                <small class="text-secondary"><?= hora_corta($cita['hora_inicio']) ?></small>
                            </td>
                            <td data-label="Motivo">
                                <small class="text-muted text-truncate d-inline-block" style="max-width: 250px;" title="<?= e($cita['motivo_consulta']) ?>"><?= e($cita['motivo_consulta']) ?></small>
                            </td>
                            <td class="text-end pe-4" data-label="Estado">
                                <span class="badge rounded-pill <?= clase_estado_cita($cita['estado_cita']) ?> px-3 py-2 shadow-sm"><?= e($cita['estado_cita']) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($citas === []): ?>
                        <tr><td colspan="6" class="text-center text-muted py-5">No se encontraron registros en el historial con los filtros aplicados.</td></tr>
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

<?php require __DIR__ . '/../src/views/layout/footer.php'; ?>
