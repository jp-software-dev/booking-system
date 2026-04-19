<?php
/**
 * VISTA DE AGENDA DE CITAS
 *
 * Interfaz principal para que los pacientes agenden nuevas citas. Muestra un
 * calendario interactivo semanal con los horarios disponibles.
 *
 * @requires session_start
 * @requires config/Database.php
 * @requires paciente role
 * @redirect login_paciente.php si no está autenticado o si es admin.
 */

session_start();

require_once '../config/Database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login_paciente.php");
    exit();
}

$db = Database::getInstance();

if ($_SESSION['role'] === 'admin') {
    $paciente = ['email' => '', 'telefono' => ''];
} else {
    $stmt = $db->prepare("SELECT email, telefono FROM pacientes WHERE id_paciente = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $paciente = $stmt->fetch();
    
    if (!$paciente) {
        session_destroy();
        header("Location: login_paciente.php");
        exit();
    }
}

$doctores_query = "SELECT id_doctor, nombre, apellido_paterno FROM doctores WHERE estado = 1 ORDER BY nombre";
$doctores_stmt = $db->query($doctores_query);
$doctores = $doctores_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda - MediAgenda</title>
    
    <style>
        /* FIX: Previene que el mini-calendario se recorte */
        .booking-sidebar {
            min-width: 320px !important;
            padding-right: 20px !important;
        }
        .mini-cal {
            width: 100% !important;
            box-sizing: border-box;
        }
        .mini-cal-header {
            padding: 0 10px;
        }
    </style>
</head>
<body>
    <?php include '../src/views/layout/header.php'; ?>

    <div class="container-fluid py-4 bg-light">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold text-dark">Selecciona un horario para tu cita</h4>
                <span class="text-muted small">(GMT-06:00) Hora estándar central - CDMX</span>
            </div>

            <div class="booking-interface">
                <div class="booking-sidebar">
                    <div class="d-flex align-items-center mb-4">
                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" style="width: 50px; height: 50px;">
                            MA
                        </div>
                        <div class="ms-3">
                            <h5 class="fw-bold text-dark mb-0">Agenda Médica</h5>
                            <small class="text-muted">Servicio General</small>
                        </div>
                    </div>
                    
                    <div class="mb-4 text-muted small">
                        <div class="mb-2"><i class="bi bi-clock me-2 text-primary"></i> Citas de 30 min</div>
                        <div><i class="bi bi-geo-alt me-2 text-primary"></i> Consultorio Principal</div>
                    </div>

                    <div id="mini-calendar-wrapper"></div>
                </div>

                <div class="booking-main">
                    <div class="agenda-header d-flex justify-content-between align-items-center mb-4">
                        <button class="btn btn-outline-secondary btn-sm rounded-circle" id="btn-prev-week">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <h5 class="m-0 fw-bold" id="current-month-display">Cargando...</h5>
                        <button class="btn btn-outline-secondary btn-sm rounded-circle" id="btn-next-week">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>

                    <div class="agenda-grid" id="agenda-grid-container"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                <div class="modal-header border-0 bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-calendar-check me-2"></i> Confirmar cita</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="bookingForm">
                    <div class="modal-body p-4">
                        <div class="bg-light p-3 rounded-4 mb-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-calendar-event fs-2 text-primary me-3"></i>
                                <div>
                                    <span class="text-muted small">Fecha y hora seleccionadas</span>
                                    <div class="fw-bold text-dark" id="modal-date-text">--</div>
                                    <div class="text-muted" id="modal-time-text">--</div>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="fecha_cita" id="form_fecha">
                        <input type="hidden" name="hora_inicio" id="form_hora">

                        <div class="mb-3">
                            <label for="id_doctor" class="form-label fw-bold text-secondary">Especialista <span class="text-danger">*</span></label>
                            <select id="id_doctor" name="id_doctor" class="form-select" required>
                                <option value="" selected disabled>Seleccione un especialista</option>
                                <?php foreach ($doctores as $doc): ?>
                                    <option value="<?php echo $doc['id_doctor']; ?>">
                                        Dr. <?php echo htmlspecialchars($doc['nombre'] . ' ' . $doc['apellido_paterno']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label fw-bold text-secondary">Correo electrónico</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($paciente['email']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="telefono" class="form-label fw-bold text-secondary">Teléfono</label>
                                <input type="tel" id="telefono" name="telefono" class="form-control" value="<?php echo htmlspecialchars($paciente['telefono']); ?>" required>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label for="motivo" class="form-label fw-bold text-secondary">Motivo (opcional)</label>
                            <textarea id="motivo" name="motivo" class="form-control" rows="3" placeholder="Ej. Chequeo general..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">Agendar cita</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../src/views/layout/footer.php'; ?>
</body>
</html>