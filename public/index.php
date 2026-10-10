<?php

/**
 * VISTA: PÁGINA DE INICIO
 *
 * Presentación del servicio. Si entra un paciente con sesión, muestra además
 * sus próximas citas.
 *
 * Acceso: público.
 */

require_once __DIR__ . '/../config/config.php';

use App\Http\Sesion;
use App\Models\Cita;

// === DATOS ===
$rutaAgendar = Sesion::estaAutenticado() ? 'agenda.php' : 'login.php';

$proximasCitas = Sesion::tieneRol(Sesion::ROL_PACIENTE)
    ? (new Cita())->listarProximasPorPaciente((int) Sesion::usuarioId())
    : [];

// === VISTA ===
require __DIR__ . '/../src/views/layout/header.php';
?>
<section class="border-bottom d-flex align-items-center" style="min-height: 100vh; padding-top: 24px;">
    <div class="container">
        <div class="row align-items-center w-100">
            <div class="col-lg-6 pe-lg-5">
                <div class="text-primary fw-bold mb-2 text-uppercase">Tu Salud en Buenas Manos</div>
                <h1 class="fw-bolder text-dark-blue mb-4 display-4">Gestión Inteligente de Citas Médicas</h1>
                <p class="text-secondary lead mb-5">Agenda, reprograma y mantén el control de tu historial médico desde la comodidad de tu hogar con acceso a especialistas.</p>
                <div class="d-flex gap-3">
                    <a href="<?= e($rutaAgendar) ?>" class="btn btn-primary btn-lg px-5 shadow-sm"><i class="bi bi-calendar-check me-2"></i>Agendar Cita</a>
                    <?php if (!Sesion::estaAutenticado()): ?>
                        <a href="registro.php" class="btn btn-outline-primary btn-lg px-5">Crear Cuenta</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6 mt-5 mt-lg-0 text-center">
                <video autoplay muted loop playsinline class="w-100 rounded-4 shadow-lg" style="object-fit: cover; max-height: 600px;">
                    <source src="assets/videos/doctor.mp4" type="video/mp4">
                </video>
            </div>
        </div>
    </div>
</section>

<?php if ($proximasCitas !== []): ?>
    <!-- === PRÓXIMAS CITAS DEL PACIENTE === -->
    <section class="py-5 bg-light border-bottom">
        <div class="container">
            <h3 class="fw-bold mb-4 text-dark-blue"><i class="bi bi-calendar-event text-primary me-2"></i>Tus Próximas Citas</h3>
            <div class="row g-4">
                <?php foreach ($proximasCitas as $cita): ?>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm rounded-4 h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="fw-bold text-dark mb-1">Dr. <?= e($cita['doctor_nombre'] . ' ' . $cita['doctor_apellido']) ?></h5>
                                        <span class="badge rounded-pill px-3 py-1 <?= clase_estado_cita($cita['estado_cita']) ?>"><?= e($cita['estado_cita']) ?></span>
                                    </div>
                                    <div class="bg-light rounded-circle p-3 text-center d-flex flex-column justify-content-center" style="width: 60px; height: 60px;">
                                        <span class="d-block fw-bold fs-5 text-primary lh-1"><?= date('d', (int) strtotime($cita['fecha_cita'])) ?></span>
                                        <span class="d-block text-muted small lh-1 mt-1"><?= date('M', (int) strtotime($cita['fecha_cita'])) ?></span>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center text-secondary mb-2"><i class="bi bi-clock me-2"></i> <?= hora_corta($cita['hora_inicio']) ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- === BENEFICIOS === -->
<section class="py-5 bg-white">
    <div class="container py-4">
        <div class="text-center mb-5">
            <h2 class="fw-bold text-dark-blue">¿Por qué elegir <?= e(APP_NAME) ?>?</h2>
            <p class="text-secondary">Diseñado pensando en la comodidad del paciente y la eficiencia médica.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 p-4 text-center">
                    <i class="bi bi-calendar2-week fs-1 text-primary mb-3"></i>
                    <h5 class="fw-bold text-dark-blue">Disponibilidad 24/7</h5>
                    <p class="text-secondary">Agenda tus citas médicas a cualquier hora del día, sin esperar en líneas telefónicas ni ajustarte a horarios de oficina.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 p-4 text-center">
                    <i class="bi bi-shield-check fs-1 text-primary mb-3"></i>
                    <h5 class="fw-bold text-dark-blue">Seguridad y Confidencialidad</h5>
                    <p class="text-secondary">Tus datos médicos están protegidos con encriptación avanzada. Cumplimos con las normativas de privacidad.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 p-4 text-center">
                    <i class="bi bi-bell-fill fs-1 text-primary mb-3"></i>
                    <h5 class="fw-bold text-dark-blue">Notificaciones por Correo</h5>
                    <p class="text-secondary">Recibe un correo cuando agendas una cita y cuando nuestro equipo la confirma o la cancela.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- === LLAMADO A LA ACCIÓN === -->
<section class="py-5 text-white" style="background: linear-gradient(135deg, #0a4275 0%, #0d6efd 100%);">
    <div class="container text-center py-4">
        <h2 class="fw-bold mb-3 text-white">¿Listo para transformar tu experiencia médica?</h2>
        <p class="mb-4 lead text-white-50">Únete a nuestra plataforma y toma el control de tu agenda hoy mismo.</p>
        <a href="registro.php" class="btn btn-light btn-lg rounded-pill px-5 py-3 fw-bold shadow">CREAR MI CUENTA AHORA</a>
    </div>
</section>

<?php require __DIR__ . '/../src/views/layout/footer.php'; ?>
