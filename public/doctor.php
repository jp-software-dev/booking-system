<?php

/**
 * VISTA: PANEL DEL MÉDICO ("TU SEMANA DE CONSULTAS")
 *
 * - Arriba, la semana como pastillero: un compartimento por día y momento
 *   (mañana, tarde, noche) con las citas que caen en él.
 * - Abajo, las citas de cada día con el botón para emitir la receta o ver la
 *   que ya se emitió.
 *
 * Parámetros: semana (Y-m-d de cualquier día de la semana a mostrar; por defecto, la actual).
 * Acceso: doctor.
 */

require_once __DIR__ . '/../config/config.php';

use App\Http\Sesion;
use App\Models\Cita;
use App\Models\Doctor;
use App\Models\Tratamiento;

Sesion::exigirRol(Sesion::ROL_DOCTOR);

$idDoctor = Sesion::doctorId();
$doctor = $idDoctor !== null ? (new Doctor())->obtenerPorId($idDoctor) : null;
if ($doctor === null) {
    Sesion::redirigir('login.php');
}

// === SEMANA A MOSTRAR (LUNES A DOMINGO) ===
$referencia = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($_GET['semana'] ?? '')) ?: new DateTimeImmutable('today');
$lunes = $referencia->modify('monday this week');
$domingo = $lunes->modify('+6 days');
$hoy = date('Y-m-d');

$citas = (new Cita())->listarAgendaDoctor($idDoctor, $lunes->format('Y-m-d'), $domingo->format('Y-m-d'));

// Citas agrupadas por día y por momento del día (para el pastillero).
$porDia = [];
$compartimentos = [];
foreach ($citas as $cita) {
    $fecha = (string) $cita['fecha_cita'];
    $porDia[$fecha][] = $cita;
    if ($cita['estado_cita'] !== 'Cancelada') {
        $compartimentos[$fecha][Tratamiento::momentoDelDia($fecha . ' ' . $cita['hora_inicio'])][] = $cita;
    }
}

$activas = array_filter($citas, static fn (array $c): bool => $c['estado_cita'] !== 'Cancelada');
$deHoy = array_filter($activas, static fn (array $c): bool => $c['fecha_cita'] === $hoy);
$sinReceta = array_filter($activas, static fn (array $c): bool => $c['id_receta'] === null && in_array($c['estado_cita'], ['Completada', 'En Curso'], true));

$momentos = ['manana' => 'Mañana', 'tarde' => 'Tarde', 'noche' => 'Noche'];
$dias = [];
for ($i = 0; $i < 7; $i++) {
    $dias[] = $lunes->modify("+{$i} days")->format('Y-m-d');
}

$nombreDoctor = 'Dr(a). ' . $doctor['nombre'] . ' ' . $doctor['apellido_paterno'];

// === VISTA ===
$tituloPagina = 'Mi semana de consultas';
$scriptsExtra = ['js/receta.js'];
require __DIR__ . '/../src/views/layout/header.php';
?>
<div class="container ma-pagina">
    <div class="ma-encabezado-pagina">
        <div>
            <h1 class="ma-titulo">Tu semana de consultas</h1>
            <p class="ma-subtitulo"><?= e($nombreDoctor) ?> · <?= count($activas) ?> citas esta semana · <?= count($deHoy) ?> hoy</p>
        </div>
        <nav class="d-flex align-items-center gap-2" aria-label="Cambiar de semana">
            <a class="btn btn-light" href="?semana=<?= e($lunes->modify('-7 days')->format('Y-m-d')) ?>" aria-label="Semana anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
            <span class="fw-bold px-2 ma-cifras"><?= e(date('j', $lunes->getTimestamp())) ?>–<?= e(date('j', $domingo->getTimestamp()) . ' ' . mes_corto($domingo->format('Y-m-d'))) ?></span>
            <a class="btn btn-light" href="?semana=<?= e($lunes->modify('+7 days')->format('Y-m-d')) ?>" aria-label="Semana siguiente"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
            <a class="btn btn-outline-primary" href="doctor.php">Hoy</a>
        </nav>
    </div>

    <?php if ($sinReceta !== []): ?>
        <div class="alert alert-warning d-flex align-items-center gap-2 ma-sin-entrada">
            <i class="bi bi-file-earmark-medical" aria-hidden="true"></i>
            <span><?= count($sinReceta) ?> consulta(s) completada(s) esta semana todavía sin receta.</span>
        </div>
    <?php endif; ?>

    <!-- === LA SEMANA COMO PASTILLERO === -->
    <section class="ma-panel mb-4" aria-label="Citas de la semana por momento del día">
        <div class="overflow-auto">
            <div class="pastillero" style="min-width: 760px;">
                <div></div>
                <?php foreach ($dias as $dia): ?>
                    <a href="#dia-<?= e($dia) ?>" class="pastillero-dia text-decoration-none<?= $dia === $hoy ? ' hoy' : '' ?><?= date('N', strtotime($dia)) === '7' ? ' domingo' : '' ?>">
                        <?= e(dia_corto($dia)) ?><b><?= e(date('j', strtotime($dia))) ?></b>
                    </a>
                <?php endforeach; ?>

                <?php foreach ($momentos as $clave => $etiqueta): ?>
                    <div class="pastillero-momento <?= e($clave) ?>"><?= e($etiqueta) ?></div>
                    <?php foreach ($dias as $dia): ?>
                        <?php $enCompartimento = $compartimentos[$dia][$clave] ?? []; ?>
                        <a href="#dia-<?= e($dia) ?>" class="compartimento text-decoration-none <?= e($clave) ?><?= $enCompartimento === [] ? ' vacio' : '' ?><?= $dia === $hoy && $enCompartimento !== [] ? ' hoy' : '' ?>"
                           aria-label="<?= e(fecha_larga($dia) . ', ' . strtolower($etiqueta) . ': ' . count($enCompartimento) . ' citas') ?>">
                            <span class="tapa"></span>
                            <?php if ($enCompartimento !== []): ?>
                                <strong class="ma-cifras fs-5 lh-1"><?= count($enCompartimento) ?></strong>
                                <?php foreach (array_slice($enCompartimento, 0, 2) as $cita): ?>
                                    <span class="dosis"><span><?= e($cita['paciente_nombre'] . ' ' . mb_substr((string) $cita['paciente_apellido'], 0, 1) . '.') ?></span><time><?= e(substr((string) $cita['hora_inicio'], 0, 5)) ?></time></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- === CITAS POR DÍA === -->
    <?php if ($citas === []): ?>
        <div class="ma-panel ma-vacio">
            <i class="bi bi-calendar2-check" aria-hidden="true"></i>
            <strong>Sin citas esta semana</strong>
            Revisa la semana siguiente o la anterior con las flechas.
        </div>
    <?php endif; ?>

    <?php foreach ($porDia as $dia => $citasDelDia): ?>
        <section class="ma-panel" id="dia-<?= e($dia) ?>" style="scroll-margin-top: 96px;">
            <h2 class="ma-panel-titulo">
                <span><?= e(ucfirst(fecha_larga($dia))) ?><?= $dia === $hoy ? ' · hoy' : '' ?></span>
                <span class="ma-chip"><?= count($citasDelDia) ?> cita(s)</span>
            </h2>
            <ul class="list-unstyled mb-0">
                <?php foreach ($citasDelDia as $cita): ?>
                    <?php $momento = Tratamiento::momentoDelDia($dia . ' ' . $cita['hora_inicio']); ?>
                    <li class="toma cita-fila <?= e($momento) ?>">
                        <span class="toma-punto" aria-hidden="true"></span>
                        <span class="toma-hora"><?= e(substr((string) $cita['hora_inicio'], 0, 5)) ?></span>
                        <div class="min-w-0 cita-cuerpo">
                            <div class="fw-bold">
                                <?= e($cita['paciente_nombre'] . ' ' . $cita['paciente_apellido']) ?>
                                <?php if (edad($cita['fecha_nacimiento'] ?? null) !== null): ?>
                                    <span class="cita-edad text-secondary fw-semibold"><?= edad($cita['fecha_nacimiento']) ?> años</span>
                                <?php endif; ?>
                            </div>
                            <div class="toma-detalle cita-motivo"><?= e((string) $cita['motivo_consulta']) ?></div>
                        </div>
                        <div class="cita-acciones d-flex align-items-center gap-2 flex-wrap justify-content-end">
                            <span class="badge <?= e(clase_estado_cita((string) $cita['estado_cita'])) ?>"><?= e((string) $cita['estado_cita']) ?></span>
                            <?php if ($cita['id_receta'] !== null): ?>
                                <button type="button" class="btn btn-sm btn-light" data-ver-receta="<?= (int) $cita['id_receta'] ?>"><i class="bi bi-file-earmark-medical me-1" aria-hidden="true"></i>Ver receta</button>
                            <?php elseif ($cita['estado_cita'] !== 'Cancelada'): ?>
                                <button type="button" class="btn btn-sm btn-primary" data-recetar="<?= (int) $cita['id_cita'] ?>"
                                        data-paciente="<?= e($cita['paciente_nombre'] . ' ' . $cita['paciente_apellido']) ?>"
                                        data-motivo="<?= e((string) $cita['motivo_consulta']) ?>">
                                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Recetar
                                </button>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
</div>

<?php
require __DIR__ . '/../src/views/partials/modal_emitir_receta.php';
require __DIR__ . '/../src/views/partials/modal_detalle_receta.php';
require __DIR__ . '/../src/views/layout/footer.php';
?>
