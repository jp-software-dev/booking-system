<?php
// VISTA DE AGENDA: Interfaz principal de reserva donde los pacientes seleccionan fecha, hora y especialista para sus citas médicas.

// GESTIÓN DE SESIÓN: Reanuda la sesión activa para validar la identidad del usuario antes de permitir el acceso al calendario.
session_start();

// DEPENDENCIAS: Carga la configuración de la base de datos para realizar las consultas de perfiles y especialistas.
require_once '../config/Database.php';

// PROTECCIÓN DE ACCESO: Si no hay un usuario autenticado, redirige inmediatamente al login de pacientes para proteger la privacidad del sistema.
if (!isset($_SESSION['user_id'])) {
    header("Location: login_paciente.php");
    exit();
}

// CONEXIÓN DB: Obtiene la instancia única de la base de datos (Singleton).
$db = Database::getInstance();

// PERFIL DE PACIENTE: Si el usuario es un paciente, extrae sus datos de contacto para precargar el formulario de agendamiento automáticamente.
if ($_SESSION['role'] === 'admin') {
    $paciente = ['email' => '', 'telefono' => ''];
} else {
    $stmt = $db->prepare("SELECT email, telefono FROM pacientes WHERE id_paciente = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $paciente = $stmt->fetch();
    
    // INTEGRIDAD DE SESIÓN: Si los datos del paciente no existen (ej. cuenta eliminada), destruye la sesión y redirige al login.
    if (!$paciente) {
        session_destroy();
        header("Location: login_paciente.php");
        exit();
    }
}

// LISTADO DE ESPECIALISTAS: Recupera los doctores con estado activo (1) para poblar el menú desplegable del modal de reserva.
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
</head>
<body>
    <?php 
    // CABECERA: Incluye el layout del header que gestiona la navegación y el estado de la sesión visualmente.
    include '../src/views/layout/header.php'; 
    ?>

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
                                <?php 
                                // BUCLE DE DOCTORES: Genera las opciones del select basadas en los médicos activos recuperados de la base de datos.
                                foreach ($doctores as $doc): 
                                ?>
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

    <?php 
    // PIE DE PÁGINA: Incluye el footer que contiene el cierre de etiquetas y la carga de calendar.js (el motor de esta vista).
    include '../src/views/layout/footer.php'; 
    ?>
</body>
</html>