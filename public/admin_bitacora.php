<?php

/**
 * VISTA: BITÁCORA DE AUDITORÍA (ADMIN)
 *
 * Listado paginado de las acciones registradas, con filtros por tipo de
 * acción, usuario (id, nombre o rol) y rango de fechas.
 *
 * Acceso: administrador.
 */

require_once __DIR__ . '/../config/config.php';

use App\Helpers\Paginacion;
use App\Helpers\ValidationHelper;
use App\Http\Sesion;
use App\Models\Auditoria;

Sesion::exigirRol(Sesion::ROL_ADMIN);

// === FILTROS ===
$leer = static fn (string $clave): string => is_string($_GET[$clave] ?? null) ? trim($_GET[$clave]) : '';

$filtros = [
    'accion'  => $leer('accion'),
    'usuario' => mb_substr($leer('usuario'), 0, 100),
    // Las fechas inválidas se ignoran en lugar de producir un error.
    'desde'   => ValidationHelper::validateDate($leer('desde')) ? $leer('desde') : '',
    'hasta'   => ValidationHelper::validateDate($leer('hasta')) ? $leer('hasta') : '',
];

// === DATOS ===
$auditoria = new Auditoria();
$acciones = $auditoria->accionesRegistradas();
$paginacion = new Paginacion($auditoria->contarConFiltro($filtros), $_GET);
$registros = $auditoria->listarConFiltro($filtros, $paginacion);

// === VISTA ===
require __DIR__ . '/../src/views/layout/header.php';
?>
<div class="container-fluid py-4 px-lg-5" style="min-height: 85vh;">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 px-4">
            <h5 class="fw-bold text-dark-blue mb-0"><i class="bi bi-journal-text me-2 text-primary"></i>Bitácora de Auditoría</h5>
        </div>

        <!-- === FILTROS === -->
        <div class="bg-light p-3 border-bottom">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label for="filtro_accion" class="form-label small fw-bold text-secondary mb-1">Tipo de acción</label>
                    <select name="accion" id="filtro_accion" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <?php foreach ($acciones as $accion): ?>
                            <option value="<?= e($accion) ?>" <?= $filtros['accion'] === $accion ? 'selected' : '' ?>><?= e($accion) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filtro_usuario" class="form-label small fw-bold text-secondary mb-1">Usuario</label>
                    <input type="text" name="usuario" id="filtro_usuario" class="form-control form-control-sm" value="<?= e($filtros['usuario']) ?>" placeholder="ID, nombre o rol">
                </div>
                <div class="col-md-3">
                    <label for="filtro_desde" class="form-label small fw-bold text-secondary mb-1">Fecha desde</label>
                    <input type="date" name="desde" id="filtro_desde" class="form-control form-control-sm" value="<?= e($filtros['desde']) ?>">
                </div>
                <div class="col-md-3">
                    <label for="filtro_hasta" class="form-label small fw-bold text-secondary mb-1">Fecha hasta</label>
                    <input type="date" name="hasta" id="filtro_hasta" class="form-control form-control-sm" value="<?= e($filtros['hasta']) ?>">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 fw-medium shadow-sm">Filtrar</button>
                    <a href="admin_bitacora.php" class="btn btn-light btn-sm rounded-pill px-3 fw-medium border">Limpiar</a>
                </div>
            </form>
        </div>

        <!-- === TABLA === -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Fecha</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Entidad</th>
                        <th>Detalle</th>
                        <th class="pe-4 text-end">Ver</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php foreach ($registros as $r): ?>
                        <?php
                        $usuario = '#' . $r['id_usuario'] . ($r['nombre_usuario'] ? ' — ' . $r['nombre_usuario'] : '');
                        $entidad = $r['entidad'] . ($r['id_entidad'] ? ' #' . $r['id_entidad'] : '');
                        ?>
                        <tr>
                            <td class="ps-4 text-secondary small" data-label="Fecha"><?= fecha_corta($r['fecha']) ?> <?= hora_corta($r['fecha']) ?></td>
                            <td data-label="Usuario">
                                <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis">#<?= (int) $r['id_usuario'] ?> (<?= e($r['rol']) ?>)</span>
                                <?php if (!empty($r['nombre_usuario'])): ?><div class="small text-secondary mt-1"><?= e($r['nombre_usuario']) ?></div><?php endif; ?>
                            </td>
                            <td class="fw-medium text-dark" data-label="Acción"><?= e($r['accion']) ?></td>
                            <td class="text-secondary" data-label="Entidad"><?= e($entidad) ?></td>
                            <td class="text-secondary small text-truncate" style="max-width: 220px;" title="<?= e($r['detalle']) ?>" data-label="Detalle"><?= e($r['detalle']) ?></td>
                            <td class="pe-4 text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" title="Ver detalle completo" data-ver-detalle
                                        data-fecha="<?= e(date('d/m/Y h:i:s A', (int) strtotime($r['fecha']))) ?>"
                                        data-usuario="<?= e($usuario) ?>"
                                        data-rol="<?= e($r['rol']) ?>"
                                        data-accion="<?= e($r['accion']) ?>"
                                        data-entidad="<?= e($entidad) ?>"
                                        data-detalle="<?= e($r['detalle'] ?? 'Sin detalle adicional.') ?>">
                                    <i class="bi bi-eye-fill"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($registros === []): ?>
                        <tr><td colspan="6" class="text-center text-muted py-5">No hay registros en la bitácora con los filtros aplicados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php
    $itemsMostrados = count($registros);
    require __DIR__ . '/../src/views/partials/pagination.php';
    ?>
</div>

<!-- === MODAL: DETALLE DEL REGISTRO === -->
<div class="modal fade" id="modalDetalleBitacora" tabindex="-1" aria-labelledby="modalDetalleBitacoraTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold" id="modalDetalleBitacoraTitulo"><i class="bi bi-journal-text me-2"></i>Detalle del Registro</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-6"><span class="text-secondary small d-block">Fecha exacta</span><span class="fw-medium" id="det_fecha"></span></div>
                    <div class="col-md-6"><span class="text-secondary small d-block">Rol</span><span class="fw-medium" id="det_rol"></span></div>
                    <div class="col-12"><span class="text-secondary small d-block">Usuario</span><span class="fw-medium" id="det_usuario"></span></div>
                    <div class="col-12"><span class="text-secondary small d-block">Acción</span><span class="fw-medium" id="det_accion"></span></div>
                    <div class="col-12"><span class="text-secondary small d-block">Entidad</span><span class="fw-medium" id="det_entidad"></span></div>
                    <div class="col-12">
                        <span class="text-secondary small d-block">Detalle completo</span>
                        <span class="fw-medium" id="det_detalle" style="white-space: pre-wrap;"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-4">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
// Llena el modal con los datos del registro (como texto, no como HTML) y lo abre.
document.querySelectorAll('[data-ver-detalle]').forEach((boton) => {
    boton.addEventListener('click', () => {
        ['fecha', 'usuario', 'rol', 'accion', 'entidad', 'detalle'].forEach((campo) => {
            document.getElementById(`det_${campo}`).textContent = boton.dataset[campo];
        });
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalleBitacora')).show();
    });
});
</script>

<?php require __DIR__ . '/../src/views/layout/footer.php'; ?>
