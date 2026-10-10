<?php

/**
 * VISTA: PACIENTES (ADMIN)
 *
 * Dos modos según la URL:
 *   - admin_pacientes.php          Listado paginado con búsqueda por nombre o correo.
 *   - admin_pacientes.php?ver=ID   Ficha del paciente con su historial completo
 *                                  (incluye las citas que el paciente ocultó de su vista).
 *
 * Acceso: administrador.
 */

require_once __DIR__ . '/../config/config.php';

use App\Helpers\Paginacion;
use App\Http\Sesion;
use App\Models\Cita;
use App\Models\Paciente;

Sesion::exigirRol(Sesion::ROL_ADMIN);

$pacientesModelo = new Paciente();
$idVer = isset($_GET['ver']) && ctype_digit((string) $_GET['ver']) ? (int) $_GET['ver'] : null;

// === DATOS: FICHA DE UN PACIENTE ===
if ($idVer !== null) {
    $paciente = $pacientesModelo->obtenerPorId($idVer);
    if ($paciente === null) {
        Sesion::redirigir('admin_pacientes.php');
    }
    $citas = (new Cita())->listarPorPaciente($idVer, true);
}

// === DATOS: LISTADO ===
if ($idVer === null) {
    $busqueda = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
    $estadoCuenta = in_array($_GET['estado_cuenta'] ?? '', ['activa', 'anonimizada'], true) ? $_GET['estado_cuenta'] : '';

    $paginacion = new Paginacion($pacientesModelo->contarConFiltro($busqueda, $estadoCuenta), $_GET);
    $pacientes = $pacientesModelo->listarConFiltro($busqueda, $estadoCuenta, $paginacion);
}

// === VISTA ===
require __DIR__ . '/../src/views/layout/header.php';
?>
<?php if ($idVer !== null): ?>
    <!-- === FICHA DEL PACIENTE === -->
    <div class="container py-5" style="min-height: 85vh;">
        <a href="admin_pacientes.php" class="text-decoration-none fw-medium text-secondary mb-3 d-inline-block"><i class="bi bi-arrow-left me-1"></i> Volver al listado</a>

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-bottom py-3 px-4">
                <h5 class="fw-bold text-dark-blue mb-0"><i class="bi bi-person-vcard text-primary me-2"></i>Datos del Paciente</h5>
            </div>
            <div class="card-body p-4">
                <?php if ($paciente['cuenta_eliminada']): ?>
                    <span class="badge bg-danger-subtle text-danger-emphasis mb-3">Cuenta eliminada por el paciente</span>
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-4"><span class="text-secondary small d-block">Nombre completo</span><span class="fw-medium"><?= e(trim($paciente['nombre'] . ' ' . $paciente['apellido_paterno'] . ' ' . ($paciente['apellido_materno'] ?? ''))) ?></span></div>
                    <div class="col-md-4"><span class="text-secondary small d-block">Fecha de nacimiento</span><span class="fw-medium"><?= fecha_corta($paciente['fecha_nacimiento']) ?></span></div>
                    <div class="col-md-4"><span class="text-secondary small d-block">Género</span><span class="fw-medium"><?= e($paciente['genero']) ?></span></div>
                    <div class="col-md-4"><span class="text-secondary small d-block">Teléfono</span><span class="fw-medium"><?= e($paciente['telefono'] ?? 'N/A') ?></span></div>
                    <div class="col-md-4"><span class="text-secondary small d-block">Correo</span><span class="fw-medium"><?= e($paciente['email']) ?></span></div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white border-bottom py-3 px-4">
                <h5 class="fw-bold text-dark-blue mb-0"><i class="bi bi-clock-history text-secondary me-2"></i>Historial de Citas</h5>
            </div>
            <div class="table-responsive border-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Fecha y Hora</th>
                            <th>Médico Especialista</th>
                            <th>Consultorio</th>
                            <th class="d-none d-md-table-cell">Motivo de Consulta</th>
                            <th class="text-end pe-4">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="border-top-0">
                        <?php foreach ($citas as $cita): ?>
                            <tr>
                                <td class="ps-4" data-label="Fecha y hora">
                                    <div class="text-dark fw-medium"><?= fecha_corta($cita['fecha_cita']) ?></div>
                                    <small class="text-secondary"><?= hora_corta($cita['hora_inicio']) ?></small>
                                </td>
                                <td class="text-dark fw-medium" data-label="Médico">Dr. <?= e($cita['doctor_nombre'] . ' ' . $cita['doctor_apellido']) ?></td>
                                <td class="text-secondary" data-label="Consultorio"><?= e($cita['consultorio_nombre'] ?? 'N/A') ?></td>
                                <td class="d-none d-md-table-cell text-secondary text-truncate" style="max-width: 200px;"><?= e($cita['motivo_consulta']) ?></td>
                                <td class="text-end pe-4" data-label="Estado"><span class="badge rounded-pill <?= clase_estado_cita($cita['estado_cita']) ?> px-3 py-1"><?= e($cita['estado_cita']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($citas === []): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">Este paciente no tiene citas registradas.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- === LISTADO DE PACIENTES === -->
    <div class="container-fluid py-4 px-lg-5" style="min-height: 85vh;">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white border-bottom py-3 px-4">
                <h5 class="fw-bold text-dark-blue mb-0"><i class="bi bi-people me-2 text-primary"></i>Pacientes</h5>
            </div>

            <div class="bg-light p-3 border-bottom">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label for="busqueda" class="form-label small fw-bold text-secondary mb-1">Buscar por nombre o correo</label>
                        <input type="text" name="q" id="busqueda" class="form-control form-control-sm" placeholder="Ej. Juan Pérez, juan@correo.com" value="<?= e($busqueda) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="estado_cuenta" class="form-label small fw-bold text-secondary mb-1">Estado de cuenta</label>
                        <select name="estado_cuenta" id="estado_cuenta" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            <option value="activa" <?= $estadoCuenta === 'activa' ? 'selected' : '' ?>>Activa</option>
                            <option value="anonimizada" <?= $estadoCuenta === 'anonimizada' ? 'selected' : '' ?>>Eliminada / anonimizada</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 fw-medium shadow-sm">Buscar</button>
                        <a href="admin_pacientes.php" class="btn btn-light btn-sm rounded-pill px-3 fw-medium border">Limpiar</a>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Paciente</th>
                            <th>Correo</th>
                            <th>Teléfono</th>
                            <th class="text-end pe-4"></th>
                        </tr>
                    </thead>
                    <tbody class="border-top-0">
                        <?php foreach ($pacientes as $p): ?>
                            <tr>
                                <td class="ps-4" data-label="Paciente">
                                    <div class="fw-bold text-dark"><?= e(trim($p['nombre'] . ' ' . $p['apellido_paterno'] . ' ' . ($p['apellido_materno'] ?? ''))) ?></div>
                                    <?php if ($p['cuenta_eliminada']): ?><small class="badge bg-danger-subtle text-danger-emphasis">Cuenta eliminada</small><?php endif; ?>
                                </td>
                                <td class="text-secondary" data-label="Correo"><?= e($p['email']) ?></td>
                                <td class="text-secondary" data-label="Teléfono"><?= e($p['telefono'] ?? 'N/A') ?></td>
                                <td class="text-end pe-4">
                                    <a href="admin_pacientes.php?ver=<?= (int) $p['id_paciente'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">Ver historial</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($pacientes === []): ?>
                            <tr><td colspan="4" class="text-center text-muted py-5">No se encontraron pacientes.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php
        $itemsMostrados = count($pacientes);
        require __DIR__ . '/../src/views/partials/pagination.php';
        ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../src/views/layout/footer.php'; ?>
