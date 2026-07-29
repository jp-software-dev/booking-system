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

$where = [];
$params = [];

if ($busqAccion !== '') {
    $where[] = "accion = ?";
    $params[] = $busqAccion;
}
if ($busqUsuario !== '' && is_numeric($busqUsuario)) {
    $where[] = "id_usuario = ?";
    $params[] = (int)$busqUsuario;
}
if ($busqDesde !== '' && DateTime::createFromFormat('Y-m-d', $busqDesde)) {
    $where[] = "fecha >= ?";
    $params[] = $busqDesde . ' 00:00:00';
}
if ($busqHasta !== '' && DateTime::createFromFormat('Y-m-d', $busqHasta)) {
    $where[] = "fecha <= ?";
    $params[] = $busqHasta . ' 23:59:59';
}

$whereSql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

$countStmt = $db->prepare("SELECT COUNT(*) FROM bitacora $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$total_pages = $total > 0 ? (int)ceil($total / $per_page) : 0;
$page = $total_pages > 0 ? min($page, $total_pages) : 1;
$offset = ($page - 1) * $per_page;

$query = "SELECT id, id_usuario, rol, accion, entidad, id_entidad, detalle, fecha
          FROM bitacora $whereSql
          ORDER BY fecha DESC
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
                    <label class="form-label small fw-bold text-secondary mb-1">Usuario (ID)</label>
                    <input type="number" name="usuario" class="form-control form-control-sm" value="<?php echo htmlspecialchars($busqUsuario); ?>" placeholder="Ej. 12">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary mb-1">Desde</label>
                    <input type="date" name="desde" class="form-control form-control-sm" value="<?php echo htmlspecialchars($busqDesde); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary mb-1">Hasta</label>
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
                        <th class="pe-4">Detalle</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php foreach ($registros as $r): ?>
                    <tr>
                        <td class="ps-4 text-secondary small"><?php echo date('d/m/Y h:i A', strtotime($r['fecha'])); ?></td>
                        <td><span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis">#<?php echo $r['id_usuario']; ?> (<?php echo htmlspecialchars($r['rol']); ?>)</span></td>
                        <td class="fw-medium text-dark"><?php echo htmlspecialchars($r['accion']); ?></td>
                        <td class="text-secondary"><?php echo htmlspecialchars($r['entidad']); ?><?php echo $r['id_entidad'] ? ' #' . $r['id_entidad'] : ''; ?></td>
                        <td class="pe-4 text-secondary small text-truncate d-inline-block" style="max-width: 260px; vertical-align: middle;" title="<?php echo htmlspecialchars($r['detalle'] ?? ''); ?>"><?php echo htmlspecialchars($r['detalle'] ?? ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($registros)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-5">No hay registros en la bitácora con los filtros aplicados.</td></tr>
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

<?php require_once __DIR__ . '/../src/views/layout/footer.php'; ?>
