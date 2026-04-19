<?php
/**
 * PÁGINA PRINCIPAL (LANDING PAGE)
 *
 * Vista pública de inicio del sistema. Muestra información sobre los servicios,
 * las próximas citas del paciente (si está autenticado) y un llamado a la acción
 * para registrarse o iniciar sesión.
 *
 * @requires session_start
 * @requires src/views/layout/header.php
 * @requires config/Database.php
 */

session_start();

require_once '../src/views/layout/header.php';
require_once '../config/Database.php';

$ruta_agendar = isset($_SESSION['user_id']) ? 'agenda.php' : 'login_paciente.php';

$proximas_citas = [];

if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'paciente') {
    $db = Database::getInstance();
    
    $stmt = $db->prepare("SELECT c.fecha_cita, c.hora_inicio, d.nombre AS doctor, d.apellido_paterno, c.estado_cita
                           FROM citas c
                           JOIN doctores d ON c.id_doctor = d.id_doctor
                           WHERE c.id_paciente = ? AND c.fecha_cita >= CURDATE() AND c.estado_cita IN ('Pendiente', 'Confirmada')
                           ORDER BY c.fecha_cita ASC, c.hora_inicio ASC
                           LIMIT 3");
                           
    $stmt->execute([$_SESSION['user_id']]);
    $proximas_citas = $stmt->fetchAll();
}
?>

<section class="hero">
    <div class="container hero-container">
        <div class="hero-text">
            <h6 class="hero-pre-title">MediAgenda Elite</h6>
            <h1 class="hero-title">Gestión médica de <span class="text-primary">alta precisión</span></h1>
            <p class="hero-description">Tu agenda, historial y consultas organizadas en un solo lugar. Diseñado para simplificar tu vida con tecnología segura y eficiente.</p>
            <div class="hero-buttons">
                <a href="<?php echo $ruta_agendar; ?>" class="btn btn-primary shadow-sm">Agendar cita ahora</a>
                <a href="#servicios" class="btn btn-outline-primary">Conoce más</a>
            </div>
        </div>
        <div class="hero-image">
            <img src="assets/img/hero-doctor.jpg" alt="Médico con paciente" class="img-fluid rounded-4 shadow-lg">
        </div>
    </div>
</section>

<?php if (!empty($proximas_citas)): ?>
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="fw-bold text-dark-blue mb-4">Tus próximas citas activas</h2>
        <div class="row g-4">
            <?php foreach ($proximas_citas as $cita): ?>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 15px;">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-calendar-check fs-2 text-primary me-3"></i>
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></h6>
                                <p class="text-secondary small mb-0"><?php echo date('h:i A', strtotime($cita['hora_inicio'])); ?></p>
                            </div>
                        </div>
                        <p class="text-secondary mb-3"><i class="bi bi-person-badge text-muted me-2"></i>Dr. <?php echo htmlspecialchars($cita['doctor'] . ' ' . $cita['apellido_paterno']); ?></p>
                        <span class="badge rounded-pill px-3 py-2 <?php echo $cita['estado_cita'] === 'Pendiente' ? 'bg-warning text-dark' : 'bg-success'; ?>">
                            <?php echo $cita['estado_cita']; ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section id="servicios" class="py-5 bg-white">
    <div class="container py-5">
        <div class="text-center mb-5">
            <h2 class="fw-bold text-dark-blue display-6">¿Por qué elegirnos?</h2>
            <p class="text-secondary">Tecnología médica de vanguardia al servicio de tu bienestar</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 p-4 text-center">
                    <i class="bi bi-calendar-check-fill fs-1 text-primary mb-3"></i>
                    <h5 class="fw-bold text-dark-blue">Gestión Inteligente de Citas</h5>
                    <p class="text-secondary">Optimiza tu tiempo con nuestra agenda dinámica en tiempo real. Visualiza disponibilidad, agenda en segundos y recibe confirmaciones automáticas.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 p-4 text-center">
                    <i class="bi bi-shield-lock-fill fs-1 text-primary mb-3"></i>
                    <h5 class="fw-bold text-dark-blue">Seguridad y Confidencialidad</h5>
                    <p class="text-secondary">Tus datos médicos están protegidos con encriptación de nivel bancario. Cumplimos con las normativas de privacidad para garantizar tu tranquilidad.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 p-4 text-center">
                    <i class="bi bi-bell-fill fs-1 text-primary mb-3"></i>
                    <h5 class="fw-bold text-dark-blue">Recordatorios Automáticos</h5>
                    <p class="text-secondary">Recibe notificaciones por correo electrónico para no olvidar tus citas. Reducimos las ausencias y mejoramos tu experiencia de atención.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="cta-section py-5 text-white" style="background: linear-gradient(135deg, #0a4275 0%, #0d6efd 100%);">
    <div class="container text-center py-4">
        <h2 class="fw-bold mb-3 text-white">¿Listo para transformar tu experiencia médica?</h2>
        <p class="mb-4 lead text-white-50">Únete a nuestra plataforma y toma el control de tu agenda hoy mismo.</p>
        <a href="registro.php" class="btn btn-light btn-lg rounded-pill px-5 py-3 fw-bold text-primary" style="transition: none; box-shadow: none;">Crear cuenta gratuita</a>
    </div>
</section>

<?php require_once '../src/views/layout/footer.php'; ?>