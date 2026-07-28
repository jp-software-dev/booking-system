<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$db = Database::getInstance();

if ($_SESSION['role'] === 'admin') {
    $paciente = ['email' => '', 'telefono' => ''];
} else {
    $stmt = $db->prepare("SELECT email, telefono FROM pacientes WHERE id_paciente = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $paciente = $stmt->fetch();
    if (!$paciente) { session_destroy(); header("Location: login.php"); exit(); }
}
$doctores = $db->query("SELECT id_doctor, nombre, apellido_paterno FROM doctores WHERE estado = 1 AND id_consultorio IS NOT NULL ORDER BY nombre")->fetchAll();

require_once __DIR__ . '/../src/views/layout/header.php';
?>

<div class="container py-5" style="min-height: 85vh;">
    <h3 class="fw-bold text-dark-blue mb-4">Reserva tu Cita</h3>
    
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
        <div class="row g-0">
            <div class="col-lg-4 col-md-5 bg-light p-4 border-end">
                <div id="mini-calendar-wrapper" class="w-100 mb-4 mb-md-0"></div>
                <div class="mt-4">
                    <h6 class="fw-bold text-dark mb-3">Leyenda de Estados</h6>
                    <div class="d-flex align-items-center mb-2">
                        <div class="rounded-circle me-2" style="width: 12px; height: 12px; background-color: #10b981;"></div>
                        <span class="small text-secondary">Horarios disponibles</span>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <div class="rounded-circle me-2" style="width: 12px; height: 12px; background-color: #f3f4f6; border: 1px solid #e5e7eb;"></div>
                        <span class="small text-secondary">No disponible / Pasado</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle me-2" style="width: 12px; height: 12px; background-color: #fee2e2; border: 1px solid #fca5a5;"></div>
                        <span class="small text-secondary">Horario reservado</span>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8 col-md-7 p-4">
                
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h5 id="agenda-current-range" class="fw-bold text-dark mb-0" style="font-size: 1.25rem;">Cargando...</h5>
                    <div class="d-flex align-items-center gap-2">
                        <button id="btn-prev-week" class="btn btn-white rounded-circle shadow-sm border d-flex justify-content-center align-items-center text-dark hover-effect" style="width:42px; height:42px;">
                            <i class="bi bi-chevron-left fs-5"></i>
                        </button>
                        <button id="btn-next-week" class="btn btn-white rounded-circle shadow-sm border d-flex justify-content-center align-items-center text-dark hover-effect" style="width:42px; height:42px;">
                            <i class="bi bi-chevron-right fs-5"></i>
                        </button>
                    </div>
                </div>
                
                <div class="overflow-auto pb-3 w-100">
                    <div id="agenda-grid-container" class="agenda-grid" style="min-height: 400px;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-calendar-plus me-2"></i> Confirmar Cita</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="bookingForm">
                <input type="hidden" name="fecha_cita" id="booking_input_fecha">
                <input type="hidden" name="hora_inicio" id="booking_input_hora">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary small">Médico Especialista</label>
                        <select name="id_doctor" id="booking_select_doctor" class="form-select" required>
                            <option value="">Selecciona un especialista...</option>
                            <?php foreach ($doctores as $doc): ?>
                                <option value="<?php echo $doc['id_doctor']; ?>">Dr. <?php echo htmlspecialchars($doc['nombre'] . ' ' . $doc['apellido_paterno']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary small">Correo de Contacto</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($paciente['email']); ?>" <?php echo empty($paciente['email']) ? 'required' : 'readonly'; ?>>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary small">Teléfono Móvil</label>
                            <input type="text" name="telefono" class="form-control" value="<?php echo htmlspecialchars($paciente['telefono']); ?>" <?php echo empty($paciente['telefono']) ? 'required' : 'readonly'; ?> maxlength="10">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary small">Motivo de la consulta</label>
                        <textarea name="motivo" class="form-control" rows="3" placeholder="Escribe brevemente los síntomas..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">Agendar Cita</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../src/views/layout/footer.php'; ?>