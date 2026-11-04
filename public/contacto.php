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

// Días de la semana para la fila de compartimentos del horario.
$diasSemana = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

// === VISTA ===
$tituloPagina = 'Contacto';
require __DIR__ . '/../src/views/layout/header.php';
?>
<div class="container ma-pagina">
    <div class="ma-encabezado-pagina">
        <div>
            <h1 class="ma-titulo">Contacto</h1>
            <p class="ma-subtitulo">Escríbenos o llámanos; para una cita, lo más rápido es agendarla en línea.</p>
        </div>
        <a href="<?= e($rutaAgendar) ?>" class="btn btn-primary"><i class="bi bi-calendar-plus me-1" aria-hidden="true"></i>Agendar una cita</a>
    </div>

    <div class="row g-4">
        <!-- === DATOS DE CONTACTO === -->
        <div class="col-lg-5">
            <section class="ma-panel h-100" aria-labelledby="tituloDatos">
                <h2 class="ma-panel-titulo" id="tituloDatos">Datos de contacto</h2>
                <dl class="mb-0">
                    <div class="d-flex gap-3 py-3 border-bottom">
                        <i class="bi bi-geo-alt fs-4 text-primary" aria-hidden="true"></i>
                        <div><dt class="small text-secondary fw-bold">Ubicación</dt><dd class="fw-bold mb-0"><?= e(CONTACTO_UBICACION) ?></dd></div>
                    </div>
                    <div class="d-flex gap-3 py-3 border-bottom">
                        <i class="bi bi-telephone fs-4 text-primary" aria-hidden="true"></i>
                        <div>
                            <dt class="small text-secondary fw-bold">Teléfono</dt>
                            <dd class="fw-bold mb-0 ma-cifras">
                                <?php if ($telefonoEsNumero): ?>
                                    <a href="tel:<?= e($telefonoMarcable) ?>" class="text-decoration-none"><?= e(CONTACTO_TELEFONO) ?></a>
                                <?php else: ?>
                                    <?= e(CONTACTO_TELEFONO) ?>
                                <?php endif; ?>
                            </dd>
                        </div>
                    </div>
                    <div class="d-flex gap-3 pt-3">
                        <i class="bi bi-envelope fs-4 text-primary" aria-hidden="true"></i>
                        <div><dt class="small text-secondary fw-bold">Correo electrónico</dt><dd class="fw-bold mb-0"><a href="mailto:<?= e(CONTACTO_EMAIL) ?>" class="text-decoration-none"><?= e(CONTACTO_EMAIL) ?></a></dd></div>
                    </div>
                </dl>
            </section>
        </div>

        <!-- === HORARIO (el mismo de la agenda, desde App\Helpers\Horario) === -->
        <div class="col-lg-7">
            <section class="ma-panel h-100" aria-labelledby="tituloHorario">
                <h2 class="ma-panel-titulo" id="tituloHorario">Horario de atención</h2>
                <p class="mb-1"><span class="fw-bold"><?= e($diasHabiles) ?></span> · <span class="ma-cifras fw-bold"><?= e($horarioCitas) ?></span></p>
                <p class="small text-secondary mb-4">Citas cada <?= Horario::INTERVALO_MINUTOS ?> minutos.</p>

                <div class="d-grid gap-2" style="grid-template-columns: repeat(7, minmax(0, 1fr));" role="list" aria-label="Días de atención">
                    <?php foreach ($diasSemana as $numero => $dia): ?>
                        <?php $habil = in_array($numero, Horario::DIAS_HABILES, true); ?>
                        <div role="listitem" class="compartimento <?= $habil ? 'manana' : 'vacio' ?> align-items-center text-center" style="min-height: 84px;">
                            <span class="tapa"></span>
                            <span class="fw-bold <?= $habil ? '' : 'text-danger' ?>"><?= e($dia) ?></span>
                            <span class="small fw-semibold <?= $habil ? 'text-secondary' : 'text-danger' ?>"><?= $habil ? 'Citas' : 'Urgencias' ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php foreach ($diasSinCitas as $dia): ?>
                    <p class="small text-secondary mt-3 mb-0"><i class="bi bi-exclamation-circle me-1 text-danger" aria-hidden="true"></i><?= e($dia) ?>: solo urgencias, sin citas programadas.</p>
                <?php endforeach; ?>
            </section>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../src/views/layout/footer.php'; ?>
