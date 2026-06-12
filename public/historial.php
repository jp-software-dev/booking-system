<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'paciente') { header("Location: login.php"); exit(); }

$db = Database::getInstance();
$id_paciente = $_SESSION['user_id'];
$query = "SELECT c.id_cita, c.fecha_cita, c.hora_inicio, d.nombre AS doctor, d.apellido_paterno, c.motivo_consulta, c.estado_cita, co.nombre as consultorio_nombre 
          FROM citas c 
          JOIN doctores d ON c.id_doctor = d.id_doctor 
          LEFT JOIN consultorios co ON c.id_consultorio = co.id_consultorio 
          WHERE c.id_paciente = ? 
          ORDER BY c.fecha_cita DESC, c.hora_inicio DESC";
$stmt = $db->prepare($query);
$stmt->execute([$id_paciente]);
$todas_las_citas = $stmt->fetchAll();

$citas_activas = []; $citas_pasadas = [];
foreach ($todas_las_citas as $cita) {
    if (in_array($cita['estado_cita'], ['Pendiente', 'Confirmada', 'En Curso'])) { $citas_activas[] = $cita; } 
    else { $citas_pasadas[] = $cita; }
}

require_once __DIR__ . '/../src/views/layout/header.php';
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
                        <th class="text-end pe-4">Estado Actual</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php foreach ($citas_activas as $cita): 
                        $badge = match($cita['estado_cita']) { 'Pendiente' => 'bg-warning text-dark', 'Confirmada' => 'bg-info text-dark', 'En Curso' => 'bg-primary text-white', default => 'bg-secondary text-white' };
                    ?>
                    <tr>
                        <td class="ps-4">
                            <div class="text-dark fw-medium"><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></div>
                            <small class="text-secondary"><?php echo date('h:i A', strtotime($cita['hora_inicio'])); ?></small>
                        </td>
                        <td class="text-dark fw-medium">Dr. <?php echo htmlspecialchars($cita['doctor'] . ' ' . $cita['apellido_paterno']); ?></td>
                        <td class="text-secondary"><?php echo htmlspecialchars($cita['consultorio_nombre'] ?? 'Por asignar'); ?></td>
                        <td class="d-none d-md-table-cell text-secondary text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($cita['motivo_consulta'] ?? 'N/A'); ?>">
                            <?php echo htmlspecialchars($cita['motivo_consulta'] ?? 'N/A'); ?>
                        </td>
                        <td class="text-end pe-4"><span class="badge rounded-pill <?php echo $badge; ?> px-3 py-1"><?php echo $cita['estado_cita']; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($citas_activas)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No tienes citas programadas actualmente.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

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
                        <th class="text-end pe-4">Estado Final</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php foreach ($citas_pasadas as $cita): 
                        $claseEstado = $cita['estado_cita'] === 'Completada' ? 'bg-success text-white' : 'bg-danger text-white';
                    ?>
                    <tr>
                        <td class="ps-4">
                            <div class="text-dark fw-medium"><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></div>
                            <small class="text-secondary"><?php echo date('h:i A', strtotime($cita['hora_inicio'])); ?></small>
                        </td>
                        <td class="text-dark fw-medium">Dr. <?php echo htmlspecialchars($cita['doctor'] . ' ' . $cita['apellido_paterno']); ?></td>
                        <td class="text-secondary"><?php echo htmlspecialchars($cita['consultorio_nombre'] ?? 'N/A'); ?></td>
                        <td class="d-none d-md-table-cell text-secondary text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($cita['motivo_consulta'] ?? 'N/A'); ?>">
                            <?php echo htmlspecialchars($cita['motivo_consulta'] ?? 'N/A'); ?>
                        </td>
                        <td class="text-end pe-4"><span class="badge rounded-pill <?php echo $claseEstado; ?> px-3 py-1"><?php echo $cita['estado_cita']; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($citas_pasadas)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No hay registros en tu historial de atención.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../src/views/layout/footer.php'; ?>