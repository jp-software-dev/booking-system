<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/views/layout/header.php';
require_once __DIR__ . '/../config/Database.php';

$ruta_agendar = isset($_SESSION['user_id']) ? 'agenda.php' : 'login.php';
$proximas_citas = [];

if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'paciente') {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT c.fecha_cita, c.hora_inicio, d.nombre AS doctor, d.apellido_paterno, c.estado_cita FROM citas c JOIN doctores d ON c.id_doctor = d.id_doctor WHERE c.id_paciente = ? AND c.fecha_cita >= CURDATE() AND c.estado_cita IN ('Pendiente', 'Confirmada') ORDER BY c.fecha_cita ASC, c.hora_inicio ASC LIMIT 3");
    $stmt->execute([$_SESSION['user_id']]);
    $proximas_citas = $stmt->fetchAll();
}
?>
<section class="hero border-bottom" style="min-height: 100vh; display: flex; align-items: center; padding-top: 80px;">
    <div class="container hero-container">
        <div class="row align-items-center w-100">
            <div class="col-lg-6 hero-text pe-lg-5">
                <div class="hero-pre-title text-primary fw-bold mb-2 text-uppercase tracking-wider">Tu Salud en Buenas Manos</div>
                <h1 class="hero-title fw-bolder text-dark-blue mb-4 display-4">Gestión Inteligente de Citas Médicas</h1>
                <p class="hero-description text-secondary lead mb-5">Agenda, reprograma y mantén el control de tu historial médico desde la comodidad de tu hogar con acceso a especialistas.</p>
                <div class="d-flex gap-3">
                    <a href="<?php echo $ruta_agendar; ?>" class="btn btn-primary btn-lg px-5 shadow-sm"><i class="bi bi-calendar-check me-2"></i>Agendar Cita</a>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="registro.php" class="btn btn-outline-primary btn-lg px-5">Crear Cuenta</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6 mt-5 mt-lg-0 text-center">
                <video autoplay muted loop playsinline class="w-100 rounded-4 shadow-lg" style="object-fit: cover; max-height: 600px;"><source src="assets/videos/doctor.mp4" type="video/mp4"></video>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($proximas_citas)): ?>
<section class="py-5 bg-light border-bottom">
    <div class="container">
        <h3 class="fw-bold mb-4 text-dark-blue"><i class="bi bi-calendar-event text-primary me-2"></i>Tus Próximas Citas</h3>
        <div class="row g-4">
            <?php foreach ($proximas_citas as $cita): ?>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div><h5 class="fw-bold text-dark mb-1">Dr. <?php echo htmlspecialchars($cita['doctor'] . ' ' . $cita['apellido_paterno']); ?></h5><span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1"><?php echo $cita['estado_cita']; ?></span></div>
                                <div class="bg-light rounded-circle p-3 text-center d-flex flex-column justify-content-center" style="width: 60px; height: 60px;">
                                    <span class="d-block fw-bold fs-5 text-primary lh-1"><?php echo date('d', strtotime($cita['fecha_cita'])); ?></span>
                                    <span class="d-block text-muted small lh-1 mt-1"><?php echo date('M', strtotime($cita['fecha_cita'])); ?></span>
                                </div>
                            </div>
                            <div class="d-flex align-items-center text-secondary mb-2"><i class="bi bi-clock me-2"></i> <?php echo date('h:i A', strtotime($cita['hora_inicio'])); ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="py-5 bg-white">
    <div class="container py-4">
        <div class="text-center mb-5"><h2 class="fw-bold text-dark-blue">¿Por qué elegir MediAgenda?</h2><p class="text-secondary">Diseñado pensando en la comodidad del paciente y la eficiencia médica.</p></div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 p-4 text-center"><i class="bi bi-calendar2-week fs-1 text-primary mb-3"></i><h5 class="fw-bold text-dark-blue">Disponibilidad 24/7</h5><p class="text-secondary">Agenda tus citas médicas a cualquier hora del día, sin esperar en líneas telefónicas ni ajustarte a horarios de oficina.</p></div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 p-4 text-center"><i class="bi bi-shield-check fs-1 text-primary mb-3"></i><h5 class="fw-bold text-dark-blue">Seguridad y Confidencialidad</h5><p class="text-secondary">Tus datos médicos están protegidos con encriptación avanzada. Cumplimos con las normativas de privacidad.</p></div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 p-4 text-center"><i class="bi bi-bell-fill fs-1 text-primary mb-3"></i><h5 class="fw-bold text-dark-blue">Recordatorios Automáticos</h5><p class="text-secondary">Recibe notificaciones por correo electrónico para no olvidar tus citas programadas en nuestro sistema.</p></div>
            </div>
        </div>
    </div>
</section>

<section class="cta-section py-5 text-white" style="background: linear-gradient(135deg, #0a4275 0%, #0d6efd 100%);">
    <div class="container text-center py-4">
        <h2 class="fw-bold mb-3 text-white">¿Listo para transformar tu experiencia médica?</h2>
        <p class="mb-4 lead text-white-50">Únete a nuestra plataforma y toma el control de tu agenda hoy mismo.</p>
        <a href="registro.php" class="btn btn-light btn-lg rounded-pill px-5 py-3 fw-bold shadow">CREAR MI CUENTA AHORA</a>
    </div>
</section>

<div class="modal fade" id="modalContacto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="row g-0 w-100 m-0">
                <div class="col-md-6 p-5 bg-white position-relative">
                    <button type="button" class="btn-close position-absolute top-0 start-0 m-4" data-bs-dismiss="modal"></button>
                    <h3 class="fw-bolder text-dark-blue mb-4 mt-3">Detalles de Contacto</h3>
                    <div class="d-flex align-items-center mb-4"><i class="bi bi-geo-alt-fill fs-3 text-primary me-3"></i><div><h6 class="fw-bold mb-0">Ubicación</h6><small class="text-muted">Toluca, Estado de México</small></div></div>
                    <div class="d-flex align-items-center mb-4"><i class="bi bi-telephone-fill fs-3 text-primary me-3"></i><div><h6 class="fw-bold mb-0">Teléfono</h6><small class="text-muted">+52 729 963 5417</small></div></div>
                </div>
                <div class="col-md-6 bg-primary text-white p-5 position-relative overflow-hidden">
                    <h4 class="fw-bold mb-4 text-white"><i class="bi bi-clock-history me-2"></i> Horarios</h4>
                    <div class="d-flex justify-content-between mb-3 border-bottom border-light border-opacity-25 pb-3"><span>Lunes - Viernes</span><span class="fw-bold">07:00 AM - 12:00 PM</span></div>
                    <div class="d-flex justify-content-between mb-3 border-bottom border-light border-opacity-25 pb-3"><span>Sábados</span><span class="fw-bold">07:00 AM - 12:00 PM</span></div>
                    <div class="d-flex justify-content-between position-relative z-1"><span>Domingos</span><span class="fw-bold text-warning">Solo Urgencias</span></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../src/views/layout/footer.php'; ?>