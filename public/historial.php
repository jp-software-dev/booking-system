<?php
/**
 * VISTA DE HISTORIAL DE CITAS DEL PACIENTE
 *
 * Interfaz que muestra al paciente el listado de sus citas activas y pasadas,
 * permitiéndole gestionar sus próximas consultas.
 *
 * @requires session_start
 * @requires config/Database.php
 * @requires paciente role
 * @redirect login_paciente.php si no está autenticado.
 */

session_start();

require_once '../config/Database.php';
require_once '../src/views/layout/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'paciente') {
    header("Location: login_paciente.php");
    exit();
}

$db = Database::getInstance();
$id_paciente = $_SESSION['user_id'];

$query = "SELECT c.id_cita, c.fecha_cita, c.hora_inicio, d.nombre AS doctor, d.apellido_paterno, c.motivo_consulta, c.estado_cita
          FROM citas c
          JOIN doctores d ON c.id_doctor = d.id_doctor
          WHERE c.id_paciente = ?
          ORDER BY c.fecha_cita DESC, c.hora_inicio DESC";

$stmt = $db->prepare($query);
$stmt->execute([$id_paciente]);
$todas_las_citas = $stmt->fetchAll();

$citas_activas = [];
$citas_pasadas = [];

foreach ($todas_las_citas as $cita) {
    if (in_array($cita['estado_cita'], ['Pendiente', 'Confirmada'])) {
        $citas_activas[] = $cita;
    } else {
        $citas_pasadas[] = $cita;
    }
}
?>

<div class="container py-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold text-dark-blue">Mis Citas Médicas</h2>
            <p class="text-secondary">Gestiona tus próximas consultas y revisa tu historial</p>
        </div>
        <a href="agenda.php" class="btn btn-primary rounded-pill px-4 shadow-sm fw-bold">
            <i class="bi bi-plus-lg me-1"></i> Agendar Nueva Cita
        </a>
    </div>

    <h4 class="fw-bold text-dark-blue mb-3"><i class="bi bi-calendar2-check text-primary me-2"></i>Citas Activas</h4>
    
    <?php if (empty($citas_activas)): ?>
        <div class="card border-0 shadow-sm p-4 text-center mb-5" style="border-radius: 15px;">
            <p class="text-muted mb-0">No tienes citas próximas o en curso.</p>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm mb-5" style="border-radius: 15px; overflow: hidden;">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Folio</th>
                            <th>Fecha y Hora</th>
                            <th>Especialista</th>
                            <th>Motivo de Consulta</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($citas_activas as $cita): 
                            $claseEstado = $cita['estado_cita'] === 'Confirmada' ? 'bg-success' : 'bg-warning text-dark';
                        ?>
                        <tr>
                            <td class="ps-4 fw-bold text-primary">#<?php echo $cita['id_cita']; ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></div>
                                <small class="text-secondary"><?php echo date('h:i A', strtotime($cita['hora_inicio'])); ?></small>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-person-badge me-2 text-secondary"></i>
                                    Dr. <?php echo htmlspecialchars($cita['doctor'] . ' ' . $cita['apellido_paterno']); ?>
                                </div>
                            </td>
                            <td class="text-truncate text-secondary" style="max-width: 250px;">
                                <?php echo htmlspecialchars($cita['motivo_consulta'] ?? 'Sin motivo especificado'); ?>
                            </td>
                            <td>
                                <span class="badge rounded-pill <?php echo $claseEstado; ?> px-3 py-2">
                                    <?php echo $cita['estado_cita']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>


    <h4 class="fw-bold text-dark-blue mb-3"><i class="bi bi-clock-history text-secondary me-2"></i>Historial de Citas Pasadas</h4>
    
    <?php if (empty($citas_pasadas)): ?>
        <div class="card border-0 shadow-sm p-4 text-center" style="border-radius: 15px;">
            <p class="text-muted mb-0">Aún no hay registro de citas pasadas.</p>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm" style="border-radius: 15px; overflow: hidden; opacity: 0.9;">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Folio</th>
                            <th>Fecha y Hora</th>
                            <th>Especialista</th>
                            <th>Motivo de Consulta</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($citas_pasadas as $cita): 
                            $claseEstado = $cita['estado_cita'] === 'Completada' ? 'bg-primary' : 'bg-danger';
                        ?>
                        <tr>
                            <td class="ps-4 fw-bold text-secondary">#<?php echo $cita['id_cita']; ?></td>
                            <td>
                                <div class="text-dark"><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></div>
                                <small class="text-secondary"><?php echo date('h:i A', strtotime($cita['hora_inicio'])); ?></small>
                            </td>
                            <td class="text-secondary">
                                Dr. <?php echo htmlspecialchars($cita['doctor'] . ' ' . $cita['apellido_paterno']); ?>
                            </td>
                            <td class="text-truncate text-secondary" style="max-width: 250px;">
                                <?php echo htmlspecialchars($cita['motivo_consulta'] ?? 'Sin motivo especificado'); ?>
                            </td>
                            <td>
                                <span class="badge rounded-pill <?php echo $claseEstado; ?> px-3 py-2">
                                    <?php echo $cita['estado_cita']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../src/views/layout/footer.php'; ?>