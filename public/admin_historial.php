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
$query = "SELECT c.id_cita, p.nombre AS paciente, p.apellido_paterno AS paciente_ap, p.curp, 
                 d.nombre AS doctor, d.apellido_paterno AS doctor_ap,
                 c.fecha_cita, c.hora_inicio, c.motivo_consulta, c.estado_cita
          FROM citas c
          JOIN pacientes p ON c.id_paciente = p.id_paciente
          JOIN doctores d ON c.id_doctor = d.id_doctor
          WHERE $whereSql
          ORDER BY c.fecha_cita DESC, c.hora_inicio DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
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
    
    <div class="mt-3 text-secondary small fw-medium">
        Mostrando <?php echo count($citas); ?> registro(s)
    </div>
</div>

<?php require_once __DIR__ . '/../src/views/layout/footer.php'; ?>