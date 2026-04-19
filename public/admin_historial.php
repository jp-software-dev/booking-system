<?php
/**
 * VISTA DE AUDITORÍA DE CITAS (ADMIN)
 *
 * Interfaz administrativa que presenta el registro histórico inalterable de todas
 * las citas del sistema para fines de supervisión y control de calidad.
 *
 * @requires session_start
 * @requires config/Database.php
 * @requires admin role
 * @redirect login.php si no está autenticado.
 */

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once '../config/Database.php';
require_once '../src/views/layout/header.php';

$db = Database::getInstance();

// CONSULTA DE AUDITORÍA: Recupera todas las citas con JOIN para obtener nombres de pacientes y doctores.
$query = "SELECT c.id_cita, p.nombre AS paciente, d.nombre AS doctor, d.apellido_paterno AS doctor_ap,
                 c.fecha_cita, c.hora_inicio, c.motivo_consulta, c.estado_cita
          FROM citas c
          JOIN pacientes p ON c.id_paciente = p.id_paciente
          JOIN doctores d ON c.id_doctor = d.id_doctor
          ORDER BY c.fecha_cita DESC, c.hora_inicio DESC";
$stmt = $db->query($query);
$citas = $stmt->fetchAll();

// MÉTRICAS DE RESUMEN: Calcula total y pendientes para mostrar en el encabezado.
$total = count($citas);
$pendientes = count(array_filter($citas, fn($c) => $c['estado_cita'] === 'Pendiente'));
?>

<div class="container-fluid py-5 bg-light" style="min-height: 85vh;">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark-blue">Historial Global de Citas</h2>
                <p class="text-secondary">Registro completo y auditoría del sistema</p>
            </div>
            <div class="d-flex gap-3">
                <div class="bg-white border rounded-pill px-4 py-2 shadow-sm">
                    <span class="text-secondary small fw-bold me-2">TOTAL:</span>
                    <span class="badge bg-primary rounded-pill fs-6"><?php echo $total; ?></span>
                </div>
                <div class="bg-white border rounded-pill px-4 py-2 shadow-sm">
                    <span class="text-secondary small fw-bold me-2">PENDIENTES:</span>
                    <span class="badge bg-warning text-dark rounded-pill fs-6"><?php echo $pendientes; ?></span>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">ID / Turno</th>
                            <th>Paciente</th>
                            <th>Especialista</th>
                            <th>Fecha y Hora</th>
                            <th>Motivo</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($citas as $cita):
                            $claseEstado = match($cita['estado_cita']) {
                                'Confirmada' => 'bg-success text-white',
                                'Cancelada' => 'bg-danger text-white',
                                'Completada' => 'bg-primary text-white',
                                default => 'bg-warning text-dark'
                            };
                        ?>
                        <tr>
                            <td class="ps-4 fw-bold">#<?php echo $cita['id_cita']; ?></td>
                            <td><?php echo htmlspecialchars($cita['paciente']); ?></td>
                            <td>Dr. <?php echo htmlspecialchars($cita['doctor'] . ' ' . $cita['doctor_ap']); ?></td>
                            <td>
                                <div><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></div>
                                <small class="text-secondary"><?php echo date('h:i A', strtotime($cita['hora_inicio'])); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($cita['motivo_consulta'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge <?php echo $claseEstado; ?>"><?php echo $cita['estado_cita']; ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($citas)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-secondary">No hay citas registradas</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../src/views/layout/footer.php'; ?>