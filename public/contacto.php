<?php

/**
 * VISTA: CONTACTO
 *
 * Datos de contacto y horario de atención de la clínica.
 *
 * - Teléfono, correo y ubicación vienen de config.php (variables CONTACTO_* del
 *   .env), para no publicar datos reales en el repositorio.
 * - El horario se arma con App\Helpers\Horario, el mismo que usa la agenda,
 *   así que siempre coincide con los horarios que se pueden reservar.
 *
 * Acceso: público.
 */

require_once __DIR__ . '/../config/config.php';

use App\Helpers\Horario;
use App\Http\Sesion;

// === DATOS ===
$rutaAgendar = Sesion::estaAutenticado() ? 'agenda.php' : 'login.php';

// El teléfono solo se vuelve enlace "tel:" si contiene un número de verdad
// (con el texto de ejemplo "Tu número" se muestra como texto plano).
$telefonoMarcable = preg_replace('/[^0-9+]/', '', CONTACTO_TELEFONO);
$telefonoEsNumero = strlen((string) $telefonoMarcable) >= 8;

$diasHabiles = Horario::descripcionDiasHabiles();
$horarioCitas = Horario::horaLegible(Horario::PRIMERA_CITA) . ' – ' . Horario::horaLegible(Horario::ULTIMA_CITA);
$diasSinCitas = Horario::diasSinCitas();

// === VISTA ===
require __DIR__ . '/../src/views/layout/header.php';
?>
<div class="container py-5" style="min-height: 85vh;">
    <div class="text-center mb-5 pt-4">
        <h1 class="fw-bolder text-dark-blue display-5">Contáctanos</h1>
        <p class="text-secondary lead mx-auto" style="max-width: 600px;">
            Estamos aquí para resolver tus dudas y brindarte la mejor atención médica en el momento que lo necesites.
        </p>
    </div>

    <div class="row g-4 justify-content-center align-items-stretch">
        <!-- === DATOS DE CONTACTO === -->
        <div class="col-lg-5 col-md-6">
            <div class="card border-0 shadow-sm h-100 rounded-4 p-4">
                <h4 class="fw-bold text-dark-blue mb-4">Detalles de Contacto</h4>

                <div class="d-flex align-items-center mb-4">
                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle text-primary me-4"><i class="bi bi-geo-alt-fill fs-3"></i></div>
                    <div>
                        <h6 class="fw-bold text-dark mb-1">Ubicación</h6>
                        <p class="text-secondary mb-0 small"><?= e(CONTACTO_UBICACION) ?></p>
                    </div>
                </div>

                <div class="d-flex align-items-center mb-4">
                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle text-primary me-4"><i class="bi bi-telephone-fill fs-3"></i></div>
                    <div>
                        <h6 class="fw-bold text-dark mb-1">Teléfono</h6>
                        <p class="text-secondary mb-0 small">
                            <?php if ($telefonoEsNumero): ?>
                                <a href="tel:<?= e($telefonoMarcable) ?>" class="text-secondary text-decoration-none"><?= e(CONTACTO_TELEFONO) ?></a>
                            <?php else: ?>
                                <?= e(CONTACTO_TELEFONO) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle text-primary me-4"><i class="bi bi-envelope-fill fs-3"></i></div>
                    <div>
                        <h6 class="fw-bold text-dark mb-1">Correo Electrónico</h6>
                        <p class="text-secondary mb-0 small">
                            <a href="mailto:<?= e(CONTACTO_EMAIL) ?>" class="text-secondary text-decoration-none"><?= e(CONTACTO_EMAIL) ?></a>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- === HORARIO DE ATENCIÓN (mismo horario que la agenda) === -->
        <div class="col-lg-5 col-md-6">
            <div class="card border-0 shadow-sm h-100 rounded-4 bg-primary text-white p-4 position-relative overflow-hidden">
                <!-- Ícono decorativo de fondo. ("opacity-10" no existe en Bootstrap; por eso la opacidad va en línea.) -->
                <i class="bi bi-hospital position-absolute pe-none" style="bottom: -20px; right: -20px; font-size: 12rem; opacity: 0.1;" aria-hidden="true"></i>
                <h4 class="fw-bold mb-1 text-white"><i class="bi bi-clock-history me-2"></i> Horario de Atención</h4>
                <p class="small text-white-50 mb-4">Citas cada <?= Horario::INTERVALO_MINUTOS ?> minutos</p>

                <div class="d-flex justify-content-between mb-3 border-bottom border-light border-opacity-25 pb-3">
                    <span class="fs-6 fw-medium"><?= e($diasHabiles) ?></span>
                    <span class="fw-bold fs-6"><?= e($horarioCitas) ?></span>
                </div>
                <?php foreach ($diasSinCitas as $dia): ?>
                    <div class="d-flex justify-content-between mb-5">
                        <span class="fs-6 fw-medium"><?= e($dia) ?></span>
                        <span class="fw-bold fs-6 text-warning"><i class="bi bi-exclamation-circle-fill me-1"></i> Solo Urgencias</span>
                    </div>
                <?php endforeach; ?>

                <div class="mt-auto pt-4 position-relative">
                    <a href="<?= e($rutaAgendar) ?>" class="btn btn-light w-100 fw-bold py-3 fs-6 rounded-pill text-primary shadow-sm">Agendar Cita Ahora</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../src/views/layout/footer.php'; ?>
