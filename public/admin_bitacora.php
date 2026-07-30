<?php
require_once __DIR__ . '/../config/config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: login.php"); exit(); }
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../src/views/layout/header.php';

$db = Database::getInstance();

$acciones = $db->query("SELECT DISTINCT accion FROM bitacora ORDER BY accion")->fetchAll(PDO::FETCH_COLUMN);

$busqAccion  = trim($_GET['accion'] ?? '');
$busqDesde   = trim($_GET['desde'] ?? '');
$busqHasta   = trim($_GET['hasta'] ?? '');
$busqUsuario = trim($_GET['usuario'] ?? '');

// Mapa id_usuario+rol -> nombre para poder mostrar y buscar por nombre en vez de
// solo por ID: bitacora.id_usuario referencia pacientes.id_paciente cuando
// rol='paciente', o usuarios.id_usuario cuando rol='admin'; no hay FK física
// porque son dos tablas de origen distintas.
$usuariosJoin = "
    (SELECT id_paciente AS id_usuario, 'paciente' AS rol, CONCAT(nombre, ' ', apellido_paterno) AS nombre_usuario FROM pacientes
     UNION ALL
     SELECT id_usuario, 'admin', nombre FROM usuarios) u
";

$where = [];
$params = [];

if ($busqAccion !== '') {
    $where[] = "b.accion = ?";
    $params[] = $busqAccion;
}
if ($busqUsuario !== '') {
    if (is_numeric($busqUsuario)) {
        $where[] = "b.id_usuario = ?";
        $params[] = (int)$busqUsuario;
    } else {
        $where[] = "(u.nombre_usuario LIKE ? OR b.rol LIKE ?)";
        $params[] = "%$busqUsuario%";
        $params[] = "%$busqUsuario%";
    }
}
if ($busqDesde !== '' && DateTime::createFromFormat('Y-m-d', $busqDesde)) {
    $where[] = "b.fecha >= ?";
    $params[] = $busqDesde . ' 00:00:00';
}
if ($busqHasta !== '' && DateTime::createFromFormat('Y-m-d', $busqHasta)) {
    $where[] = "b.fecha <= ?";
    $params[] = $busqHasta . ' 23:59:59';
}

$whereSql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
$fromSql = "FROM bitacora b LEFT JOIN $usuariosJoin ON u.id_usuario = b.id_usuario AND u.rol = b.rol";

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

$countStmt = $db->prepare("SELECT COUNT(*) $fromSql $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$total_pages = $total > 0 ? (int)ceil($total / $per_page) : 0;
$page = $total_pages > 0 ? min($page, $total_pages) : 1;
$offset = ($page - 1) * $per_page;

$query = "SELECT b.id, b.id_usuario, b.rol, b.accion, b.entidad, b.id_entidad, b.detalle, b.fecha, u.nombre_usuario
          $fromSql
          $whereSql
          ORDER BY b.fecha DESC
          LIMIT ? OFFSET ?";
$stmt = $db->prepare($query);
$idx = 1;
foreach ($params as $p) { $stmt->bindValue($idx++, $p); }
$stmt->bindValue($idx++, $per_page, PDO::PARAM_INT);
$stmt->bindValue($idx++, $offset, PDO::PARAM_INT);
$stmt->execute();
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-4 px-lg-5" style="min-height: 85vh;">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 px-4">
            <h5 class="fw-bold text-dark-blue mb-0"><i class="bi bi-journal-text me-2 text-primary"></i>Bitácora de Auditoría</h5>
        </div>

        <div class="bg-light p-3 border-bottom">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-secondary mb-1">Tipo de Acción</label>
                    <select name="accion" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <?php foreach ($acciones as $acc): ?>
                            <option value="<?php echo htmlspecialchars($acc); ?>" <?php echo $busqAccion === $acc ? 'selected' : ''; ?>><?php echo htmlspecialchars($acc); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-secondary mb-1">Usuario</label>
                    <input type="text" name="usuario" class="form-control form-control-sm" value="<?php echo htmlspecialchars($busqUsuario); ?>" placeholder="ID, nombre o rol">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary mb-1">Fecha desde</label>
                    <input type="date" name="desde" class="form-control form-control-sm" value="<?php echo htmlspecialchars($busqDesde); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary mb-1">Fecha hasta</label>
                    <input type="date" name="hasta" class="form-control form-control-sm" value="<?php echo htmlspecialchars($busqHasta); ?>">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 fw-medium shadow-sm">Filtrar</button>
                    <a href="admin_bitacora.php" class="btn btn-light btn-sm rounded-pill px-3 fw-medium border">Limpiar</a>
                </div>
            </form>
        </div>

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
                    <tr>
                        <td class="ps-4 text-secondary small"><?php echo date('d/m/Y h:i A', strtotime($r['fecha'])); ?></td>
                        <td>
                            <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis">#<?php echo $r['id_usuario']; ?> (<?php echo htmlspecialchars($r['rol']); ?>)</span>
                            <?php if (!empty($r['nombre_usuario'])): ?><div class="small text-secondary mt-1"><?php echo htmlspecialchars($r['nombre_usuario']); ?></div><?php endif; ?>
                        </td>
                        <td class="fw-medium text-dark"><?php echo htmlspecialchars($r['accion']); ?></td>
                        <td class="text-secondary"><?php echo htmlspecialchars($r['entidad']); ?><?php echo $r['id_entidad'] ? ' #' . $r['id_entidad'] : ''; ?></td>
                        <td class="text-secondary small text-truncate d-inline-block" style="max-width: 220px; vertical-align: middle;" title="<?php echo htmlspecialchars($r['detalle'] ?? ''); ?>"><?php echo htmlspecialchars($r['detalle'] ?? ''); ?></td>
                        <td class="pe-4 text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3"
                                onclick="verDetalleBitacora(this)"
                                data-fecha="<?php echo htmlspecialchars(date('d/m/Y h:i:s A', strtotime($r['fecha']))); ?>"
                                data-usuario="<?php echo htmlspecialchars('#' . $r['id_usuario'] . ($r['nombre_usuario'] ? ' — ' . $r['nombre_usuario'] : '')); ?>"
                                data-rol="<?php echo htmlspecialchars($r['rol']); ?>"
                                data-accion="<?php echo htmlspecialchars($r['accion']); ?>"
                                data-entidad="<?php echo htmlspecialchars($r['entidad'] . ($r['id_entidad'] ? ' #' . $r['id_entidad'] : '')); ?>"
                                data-detalle="<?php echo htmlspecialchars($r['detalle'] ?? 'Sin detalle adicional.'); ?>"
                                title="Ver detalle completo">
                                <i class="bi bi-eye-fill"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($registros)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">No hay registros en la bitácora con los filtros aplicados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-4">
        <div class="text-secondary small fw-medium">
            Mostrando <?php echo count($registros); ?> de <?php echo $total; ?> registro(s). Página <?php echo $page; ?> de <?php echo $total_pages > 0 ? $total_pages : 1; ?>.
        </div>
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination mb-0 shadow-sm">
                <?php
                $query_params = $_GET;
                $query_params['page'] = $page - 1;
                $prev_disabled = $page <= 1 ? 'disabled' : '';
                echo "<li class='page-item $prev_disabled'><a class='page-link' href='?" . http_build_query($query_params) . "'>&laquo;</a></li>";
                for ($i = 1; $i <= $total_pages; $i++) {
                    $query_params['page'] = $i;
                    $active_class = ($i == $page) ? 'active' : '';
                    echo "<li class='page-item $active_class'><a class='page-link' href='?" . http_build_query($query_params) . "'>$i</a></li>";
                }
                $query_params['page'] = $page + 1;
                $next_disabled = $page >= $total_pages ? 'disabled' : '';
                echo "<li class='page-item $next_disabled'><a class='page-link' href='?" . http_build_query($query_params) . "'>&raquo;</a></li>";
                ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="modalDetalleBitacora" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-journal-text me-2"></i>Detalle del Registro</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
function verDetalleBitacora(btn) {
    const { fecha, usuario, rol, accion, entidad, detalle } = btn.dataset;
    document.getElementById('det_fecha').textContent = fecha;
    document.getElementById('det_usuario').textContent = usuario;
    document.getElementById('det_rol').textContent = rol;
    document.getElementById('det_accion').textContent = accion;
    document.getElementById('det_entidad').textContent = entidad;
    document.getElementById('det_detalle').textContent = detalle;
    new bootstrap.Modal(document.getElementById('modalDetalleBitacora')).show();
}
</script>

<?php require_once __DIR__ . '/../src/views/layout/footer.php'; ?>
