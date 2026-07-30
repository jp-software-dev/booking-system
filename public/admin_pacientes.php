<?php
require_once __DIR__ . '/../config/config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: login.php"); exit(); }
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../src/views/layout/header.php';

$db = Database::getInstance();

$id_ver = isset($_GET['ver']) && is_numeric($_GET['ver']) ? (int)$_GET['ver'] : null;

if ($id_ver) {
    // --- VISTA DE DETALLE: datos básicos + historial de citas de un paciente ---
    $stmtPac = $db->prepare("SELECT id_paciente, curp, nombre, apellido_paterno, apellido_materno, fecha_nacimiento, genero, telefono, email, cuenta_eliminada FROM pacientes WHERE id_paciente = ?");
    $stmtPac->execute([$id_ver]);
    $paciente = $stmtPac->fetch(PDO::FETCH_ASSOC);

    if (!$paciente) {
        header("Location: admin_pacientes.php");
        exit();
    }

    // Misma consulta que historial.php, parametrizada por id_paciente en vez de $_SESSION['user_id'].
    // Sin filtro de oculto_para_paciente: para el admin, el historial se ve completo.
    $stmtCitas = $db->prepare(
        "SELECT c.id_cita, c.fecha_cita, c.hora_inicio, d.nombre AS doctor, d.apellido_paterno, c.motivo_consulta, c.estado_cita, co.nombre as consultorio_nombre
         FROM citas c
         JOIN doctores d ON c.id_doctor = d.id_doctor
         LEFT JOIN consultorios co ON c.id_consultorio = co.id_consultorio
         WHERE c.id_paciente = ?
         ORDER BY c.fecha_cita DESC, c.hora_inicio DESC"
    );
    $stmtCitas->execute([$id_ver]);
    $citas = $stmtCitas->fetchAll(PDO::FETCH_ASSOC);
    ?>

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
                    <div class="col-md-4"><span class="text-secondary small d-block">Nombre completo</span><span class="fw-medium"><?php echo htmlspecialchars(trim($paciente['nombre'] . ' ' . $paciente['apellido_paterno'] . ' ' . ($paciente['apellido_materno'] ?? ''))); ?></span></div>
                    <div class="col-md-4"><span class="text-secondary small d-block">CURP</span><span class="fw-medium"><?php echo htmlspecialchars($paciente['curp'] ?? 'N/A'); ?></span></div>
                    <div class="col-md-4"><span class="text-secondary small d-block">Fecha de Nacimiento</span><span class="fw-medium"><?php echo date('d/m/Y', strtotime($paciente['fecha_nacimiento'])); ?></span></div>
                    <div class="col-md-4"><span class="text-secondary small d-block">Género</span><span class="fw-medium"><?php echo htmlspecialchars($paciente['genero']); ?></span></div>
                    <div class="col-md-4"><span class="text-secondary small d-block">Teléfono</span><span class="fw-medium"><?php echo htmlspecialchars($paciente['telefono'] ?? 'N/A'); ?></span></div>
                    <div class="col-md-4"><span class="text-secondary small d-block">Correo</span><span class="fw-medium"><?php echo htmlspecialchars($paciente['email']); ?></span></div>
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
                        <?php foreach ($citas as $cita):
                            $badge = match($cita['estado_cita']) {
                                'Pendiente' => 'bg-warning text-dark', 'Confirmada' => 'bg-info text-dark',
                                'En Curso' => 'bg-primary text-white', 'Completada' => 'bg-success text-white',
                                default => 'bg-danger text-white'
                            };
                        ?>
                        <tr>
                            <td class="ps-4">
                                <div class="text-dark fw-medium"><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></div>
                                <small class="text-secondary"><?php echo date('h:i A', strtotime($cita['hora_inicio'])); ?></small>
                            </td>
                            <td class="text-dark fw-medium">Dr. <?php echo htmlspecialchars($cita['doctor'] . ' ' . $cita['apellido_paterno']); ?></td>
                            <td class="text-secondary"><?php echo htmlspecialchars($cita['consultorio_nombre'] ?? 'N/A'); ?></td>
                            <td class="d-none d-md-table-cell text-secondary text-truncate" style="max-width: 200px;"><?php echo htmlspecialchars($cita['motivo_consulta'] ?? 'N/A'); ?></td>
                            <td class="text-end pe-4"><span class="badge rounded-pill <?php echo $badge; ?> px-3 py-1"><?php echo $cita['estado_cita']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($citas)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Este paciente no tiene citas registradas.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/../src/views/layout/footer.php'; ?>
    <?php
    return;
}

// --- VISTA DE LISTADO: búsqueda por nombre / email / CURP ---
$busq = trim($_GET['q'] ?? '');
$busqEstadoCuenta = trim($_GET['estado_cuenta'] ?? '');

$where = [];
$params = [];
if ($busq !== '') {
    $where[] = "(nombre LIKE ? OR apellido_paterno LIKE ? OR apellido_materno LIKE ? OR email LIKE ? OR curp LIKE ?)";
    for ($i = 0; $i < 5; $i++) { $params[] = "%$busq%"; }
}
if ($busqEstadoCuenta === 'activa') {
    $where[] = "cuenta_eliminada = 0";
} elseif ($busqEstadoCuenta === 'anonimizada') {
    $where[] = "cuenta_eliminada = 1";
}
$whereSql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;

$countStmt = $db->prepare("SELECT COUNT(*) FROM pacientes $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$total_pages = $total > 0 ? (int)ceil($total / $per_page) : 0;
$page = $total_pages > 0 ? min($page, $total_pages) : 1;
$offset = ($page - 1) * $per_page;

$query = "SELECT id_paciente, curp, nombre, apellido_paterno, apellido_materno, email, telefono, cuenta_eliminada
          FROM pacientes $whereSql
          ORDER BY nombre ASC
          LIMIT ? OFFSET ?";
$stmt = $db->prepare($query);
$idx = 1;
foreach ($params as $p) { $stmt->bindValue($idx++, $p); }
$stmt->bindValue($idx++, $per_page, PDO::PARAM_INT);
$stmt->bindValue($idx++, $offset, PDO::PARAM_INT);
$stmt->execute();
$pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-4 px-lg-5" style="min-height: 85vh;">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 px-4">
            <h5 class="fw-bold text-dark-blue mb-0"><i class="bi bi-people me-2 text-primary"></i>Pacientes</h5>
        </div>

        <div class="bg-light p-3 border-bottom">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-secondary mb-1">Buscar por nombre, email o CURP</label>
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="Ej. Juan Pérez, juan@correo.com" value="<?php echo htmlspecialchars($busq); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary mb-1">Estado de cuenta</label>
                    <select name="estado_cuenta" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <option value="activa" <?php echo $busqEstadoCuenta === 'activa' ? 'selected' : ''; ?>>Activa</option>
                        <option value="anonimizada" <?php echo $busqEstadoCuenta === 'anonimizada' ? 'selected' : ''; ?>>Eliminada / anonimizada</option>
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
                        <th>CURP</th>
                        <th>Correo</th>
                        <th>Teléfono</th>
                        <th class="text-end pe-4"></th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php foreach ($pacientes as $p): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars(trim($p['nombre'] . ' ' . $p['apellido_paterno'] . ' ' . ($p['apellido_materno'] ?? ''))); ?></div>
                            <?php if ($p['cuenta_eliminada']): ?><small class="badge bg-danger-subtle text-danger-emphasis">Cuenta eliminada</small><?php endif; ?>
                        </td>
                        <td class="text-secondary"><?php echo htmlspecialchars($p['curp'] ?? 'N/A'); ?></td>
                        <td class="text-secondary"><?php echo htmlspecialchars($p['email']); ?></td>
                        <td class="text-secondary"><?php echo htmlspecialchars($p['telefono'] ?? 'N/A'); ?></td>
                        <td class="text-end pe-4">
                            <a href="admin_pacientes.php?ver=<?php echo $p['id_paciente']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">Ver historial</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($pacientes)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-5">No se encontraron pacientes.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-4">
        <div class="text-secondary small fw-medium">
            Mostrando <?php echo count($pacientes); ?> de <?php echo $total; ?> paciente(s). Página <?php echo $page; ?> de <?php echo $total_pages > 0 ? $total_pages : 1; ?>.
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
