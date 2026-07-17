<?php
require_once __DIR__ . '/../config/config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: login"); exit(); }
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../src/views/layout/header.php';

$db = Database::getInstance();

$doctores = $db->query("SELECT id_doctor, nombre, apellido_paterno FROM doctores ORDER BY nombre")->fetchAll();

$busqId      = trim($_GET['id_cita']  ?? '');
$busqPac     = trim($_GET['paciente'] ?? '');
$busqDoc     = trim($_GET['doctor']   ?? '');
$busqEstado  = trim($_GET['estado']   ?? '');

$where  = ["c.estado_cita IN ('Completada', 'Cancelada')"];
$params = [];

if ($busqId !== '') {
    $where[]  = "c.id_cita = ?";
    $params[] = (int)$busqId;
}
if ($busqPac !== '') {
    $where[]  = "(p.nombre LIKE ? OR p.apellido_paterno LIKE ?)";
    $params[] = "%$busqPac%";
    $params[] = "%$busqPac%";
}
if ($busqDoc !== '') {
    $where[]  = "c.id_doctor = ?";
    $params[] = (int)$busqDoc;
}
if ($busqEstado !== '') {
    $where[]  = "c.estado_cita = ?";
    $params[] = $busqEstado;
}

$whereSql = implode(" AND ", $where);

// --- LÓGICA DE PAGINACIÓN ---
// 1. Contar el total de registros que coinciden con el filtro
$countQuery = "SELECT COUNT(c.id_cita) FROM citas c JOIN pacientes p ON c.id_paciente = p.id_paciente WHERE $whereSql";
$countStmt = $db->prepare($countQuery);
$countStmt->execute($params);
$total_records = (int)$countStmt->fetchColumn();

// 2. Definir variables de paginación
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Asegurar que la página no sea menor a 1
$records_per_page = 15; // Número de registros por página
$total_pages = $total_records > 0 ? ceil($total_records / $records_per_page) : 0;
$page = $total_pages > 0 ? min($page, $total_pages) : 1; // Asegurar que la página no exceda el total
$offset = ($page - 1) * $records_per_page;

$query = "SELECT c.id_cita, p.nombre AS paciente, p.apellido_paterno AS paciente_ap, p.curp, 
                 d.nombre AS doctor, d.apellido_paterno AS doctor_ap,
                 c.fecha_cita, c.hora_inicio, c.motivo_consulta, c.estado_cita
          FROM citas c
          JOIN pacientes p ON c.id_paciente = p.id_paciente
          JOIN doctores d ON c.id_doctor = d.id_doctor
          WHERE $whereSql
          ORDER BY c.fecha_cita DESC, c.hora_inicio DESC
          LIMIT ? OFFSET ?";

$stmt = $db->prepare($query);

// Se bindean los parámetros de forma manual para asegurar el tipo de dato en LIMIT y OFFSET
$param_index = 1;
foreach ($params as $param_value) { $stmt->bindValue($param_index++, $param_value); }
$stmt->bindValue($param_index++, $records_per_page, PDO::PARAM_INT);
$stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);
$stmt->execute();
$citas = $stmt->fetchAll();
?>

<div class="container-fluid py-4 px-lg-5" style="min-height: 85vh;">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h5 class="fw-bold text-dark-blue mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Historial de Citas Pasadas</h5>
        </div>
        
        <div class="bg-light p-3 border-bottom">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-secondary mb-1">Buscar ID</label>
                    <input type="number" name="id_cita" class="form-control form-control-sm" value="<?php echo htmlspecialchars($busqId); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary mb-1">Buscar Paciente</label>
                    <input type="text" name="paciente" class="form-control form-control-sm" value="<?php echo htmlspecialchars($busqPac); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary mb-1">Médico</label>
                    <select name="doctor" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($doctores as $doc): ?>
                            <option value="<?php echo $doc['id_doctor']; ?>" <?php echo $busqDoc == $doc['id_doctor'] ? 'selected' : ''; ?>>
                                Dr. <?php echo htmlspecialchars($doc['nombre'].' '.$doc['apellido_paterno']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-secondary mb-1">Estado</label>
                    <select name="estado" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="Completada" <?php echo $busqEstado === 'Completada' ? 'selected' : ''; ?>>Completada</option>
                        <option value="Cancelada" <?php echo $busqEstado === 'Cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 fw-medium shadow-sm">Filtrar</button>
                    <a href="admin_historial.php" class="btn btn-light btn-sm rounded-pill px-3 fw-medium border">Limpiar</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Paciente / CURP</th>
                        <th>Médico Especialista</th>
                        <th>Fecha y Hora</th>
                        <th>Motivo</th>
                        <th class="text-end pe-4">Estado Actual</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php foreach ($citas as $cita): 
                        $claseEstado = $cita['estado_cita'] === 'Completada' ? 'bg-success text-white' : 'bg-danger text-white';
                    ?>
                    <tr>
                        <td class="ps-4 text-muted fw-bold">#<?php echo $cita['id_cita']; ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($cita['paciente'].' '.$cita['paciente_ap']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($cita['curp'] ?? 'N/A'); ?></small>
                        </td>
                        <td>
                            <div class="text-dark">Dr. <?php echo htmlspecialchars($cita['doctor'].' '.$cita['doctor_ap']); ?></div>
                        </td>
                        <td>
                            <div class="fw-medium"><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></div>
                            <small class="text-secondary"><?php echo date('h:i A', strtotime($cita['hora_inicio'])); ?></small>
                        </td>
                        <td>
                            <small class="text-muted text-truncate d-inline-block" style="max-width:250px;" title="<?php echo htmlspecialchars($cita['motivo_consulta']); ?>">
                                <?php echo htmlspecialchars($cita['motivo_consulta'] ?? 'N/A'); ?>
                            </small>
                        </td>
                        <td class="text-end pe-4">
                            <span class="badge rounded-pill <?php echo $claseEstado; ?> px-3 py-2 shadow-sm">
                                <?php echo $cita['estado_cita']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($citas)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">No se encontraron registros en el historial con los filtros aplicados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="d-flex justify-content-between align-items-center mt-4">
        <div class="text-secondary small fw-medium">
            Mostrando <?php echo count($citas); ?> de <?php echo $total_records; ?> registro(s). Página <?php echo $page; ?> de <?php echo $total_pages > 0 ? $total_pages : 1; ?>.
        </div>

        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination mb-0 shadow-sm">
                <?php
                $query_params = $_GET;
                
                // Botón "Anterior"
                $query_params['page'] = $page - 1;
                $prev_disabled = $page <= 1 ? 'disabled' : '';
                echo "<li class='page-item $prev_disabled'><a class='page-link' href='?" . http_build_query($query_params) . "'>&laquo;</a></li>";

                // Botones de número de página
                for ($i = 1; $i <= $total_pages; $i++) {
                    $query_params['page'] = $i;
                    $active_class = ($i == $page) ? 'active' : '';
                    echo "<li class='page-item $active_class'><a class='page-link' href='?" . http_build_query($query_params) . "'>$i</a></li>";
                }

                // Botón "Siguiente"
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