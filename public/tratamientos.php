<?php

/**
 * VISTA: MI SEMANA (PACIENTE)
 *
 * Página de inicio del paciente, organizada como su pastillero semanal:
 *   - La semana: un compartimento por día y momento (mañana, tarde, noche) con
 *     sus tomas. Las tomadas tienen la tapa levantada.
 *   - Columna lateral: próxima cita y tomas de hoy, para marcarlas.
 *   - Tratamientos en curso (recetados o propios) con su avance, y recetas recibidas.
 *
 * Parámetros: semana (Y-m-d de cualquier día de la semana a mostrar; por defecto, la actual).
 * Acceso: paciente.
 */

require_once __DIR__ . '/../config/config.php';

use App\Http\Sesion;
use App\Models\Cita;
use App\Models\Receta;
use App\Models\Tratamiento;
use App\Services\NotificadorPush;

Sesion::exigirRol(Sesion::ROL_PACIENTE);
$idPaciente = (int) Sesion::usuarioId();

// === SEMANA A MOSTRAR (LUNES A DOMINGO) ===
$referencia = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($_GET['semana'] ?? '')) ?: new DateTimeImmutable('today');
$lunes = $referencia->modify('monday this week');
$domingo = $lunes->modify('+6 days');
$hoy = date('Y-m-d');
$ahora = date('Y-m-d H:i:s');
$esSemanaActual = $hoy >= $lunes->format('Y-m-d') && $hoy <= $domingo->format('Y-m-d');

// === DATOS ===
$modelo = new Tratamiento();
$tomasSemana = $modelo->tomasEntre($idPaciente, $lunes->format('Y-m-d') . ' 00:00:00', $domingo->format('Y-m-d') . ' 23:59:59');
$tomasHoy = $modelo->tomasEntre($idPaciente, $hoy . ' 00:00:00', $hoy . ' 23:59:59');
$tratamientos = $modelo->listarPorPaciente($idPaciente);
$recetas = (new Receta())->listarPorPaciente($idPaciente);
$proximaCita = (new Cita())->listarProximasPorPaciente($idPaciente, 1)[0] ?? null;

// Tomas agrupadas por día y momento (compartimentos del pastillero).
$compartimentos = [];
foreach ($tomasSemana as $toma) {
    $compartimentos[substr((string) $toma['programada_en'], 0, 10)][$toma['momento']][] = $toma;
}

// Siguiente toma pendiente de un tratamiento activo.
$proximaToma = null;
foreach ($modelo->tomasEntre($idPaciente, $ahora, (new DateTimeImmutable('+7 days'))->format('Y-m-d H:i:s')) as $toma) {
    if ($toma['estado'] === Tratamiento::TOMA_PENDIENTE && $toma['estado_tratamiento'] === Tratamiento::ACTIVO) {
        $proximaToma = $toma;
        break;
    }
}

$enCurso = array_filter($tratamientos, static fn (array $t): bool => (string) $t['fecha_fin'] > $ahora);
$terminados = array_filter($tratamientos, static fn (array $t): bool => (string) $t['fecha_fin'] <= $ahora);
$maxDias = max([1, ...array_map(static fn (array $t): int => $t['duracion_dias'], $tratamientos)]);

$momentos = ['manana' => 'Mañana', 'tarde' => 'Tarde', 'noche' => 'Noche'];
$dias = [];
for ($i = 0; $i < 7; $i++) {
    $dias[] = $lunes->modify("+{$i} days")->format('Y-m-d');
}

/** Hora HH:MM de una fecha de la base. */
$hora = static fn (string $fechaHora): string => substr($fechaHora, 11, 5);

/** Clase de estado de un compartimento según sus tomas. */
$estadoCompartimento = static function (array $tomas): string {
    $estados = array_column($tomas, 'estado');
    if ($tomas !== [] && count(array_filter($estados, static fn (string $e): bool => $e === Tratamiento::TOMA_TOMADA)) === count($tomas)) {
        return ' tomada';
    }
    if ($tomas !== [] && !in_array(Tratamiento::TOMA_PENDIENTE, $estados, true) && in_array(Tratamiento::TOMA_OMITIDA, $estados, true)) {
        return ' omitida';
    }

    return '';
};

$subtitulo = count($enCurso) . ' tratamiento(s) en curso';
if ($proximaToma !== null) {
    $diaProxima = substr((string) $proximaToma['programada_en'], 0, 10);
    $subtitulo .= ' · próxima toma ' . ($diaProxima === $hoy ? 'hoy' : dia_corto($diaProxima)) . ' a las ' . $hora((string) $proximaToma['programada_en']) . ' (' . $proximaToma['medicamento'] . ')';
}

// === VISTA ===
$tituloPagina = 'Mi semana';
$clavePushPublica = NotificadorPush::clavePublica();
$scriptsExtra = ['js/receta.js', 'js/tratamientos.js'];
require __DIR__ . '/../src/views/layout/header.php';
?>
<div class="container ma-pagina">
    <div class="ma-encabezado-pagina">
        <div>
            <h1 class="ma-titulo">Tu pastillero de la semana</h1>
            <p class="ma-subtitulo"><?= e($subtitulo) ?></p>
        </div>
        <nav class="d-flex align-items-center gap-2" aria-label="Cambiar de semana">
            <a class="btn btn-light" href="?semana=<?= e($lunes->modify('-7 days')->format('Y-m-d')) ?>" aria-label="Semana anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
            <span class="fw-bold px-2 ma-cifras"><?= e(date('j', $lunes->getTimestamp())) ?>–<?= e(date('j', $domingo->getTimestamp()) . ' ' . mes_corto($domingo->format('Y-m-d'))) ?></span>
            <a class="btn btn-light" href="?semana=<?= e($lunes->modify('+7 days')->format('Y-m-d')) ?>" aria-label="Semana siguiente"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
            <?php if (!$esSemanaActual): ?>
                <a class="btn btn-outline-primary" href="tratamientos.php">Esta semana</a>
            <?php endif; ?>
        </nav>
    </div>

    <!-- Aviso para activar notificaciones (lo muestra tratamientos.js si el navegador las admite). -->
    <div id="avisoNotificaciones" class="ma-panel d-none mb-4 align-items-center gap-3 flex-wrap">
        <i class="bi bi-bell fs-3 text-primary" aria-hidden="true"></i>
        <div class="flex-grow-1">
            <div class="fw-bold">Recibe un aviso a la hora de cada toma</div>
            <div class="small text-secondary" id="avisoNotificacionesTexto">Aunque tengas MediAgenda cerrada, en este navegador o en tu celular.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap" id="avisoNotificacionesAcciones"></div>
    </div>

    <div class="row g-4">
        <!-- === EL PASTILLERO === -->
        <section class="col-xl-9 order-2 order-xl-1" aria-label="Tomas de la semana por momento del día">
            <div class="ma-panel">
                <div class="overflow-auto">
                    <div class="pastillero" style="min-width: 640px;">
                        <div></div>
                        <?php foreach ($dias as $dia): ?>
                            <div class="pastillero-dia<?= $dia === $hoy ? ' hoy' : '' ?><?= date('N', strtotime($dia)) === '7' ? ' domingo' : '' ?>">
                                <?= e(dia_corto($dia)) ?><b><?= e(date('j', strtotime($dia))) ?></b>
                            </div>
                        <?php endforeach; ?>

                        <?php foreach ($momentos as $clave => $etiqueta): ?>
                            <div class="pastillero-momento <?= e($clave) ?>"><?= e($etiqueta) ?></div>
                            <?php foreach ($dias as $dia): ?>
                                <?php $tomas = $compartimentos[$dia][$clave] ?? []; ?>
                                <div class="compartimento <?= e($clave) ?><?= $tomas === [] ? ' vacio' : '' ?><?= $dia === $hoy && $tomas !== [] ? ' hoy' : '' ?><?= $estadoCompartimento($tomas) ?>"
                                     data-compartimento="<?= e($dia . '|' . $clave) ?>"
                                     aria-label="<?= e(ucfirst(fecha_larga($dia)) . ', ' . strtolower($etiqueta) . ': ' . count($tomas) . ' toma(s)') ?>">
                                    <span class="tapa"></span>
                                    <?php foreach (array_slice($tomas, 0, 3) as $toma): ?>
                                        <span class="dosis" data-toma-dosis="<?= (int) $toma['id_toma'] ?>" data-estado="<?= e((string) $toma['estado']) ?>">
                                            <span title="<?= e((string) $toma['medicamento']) ?>"><?= e((string) $toma['medicamento']) ?></span><time><?= e($hora((string) $toma['programada_en'])) ?></time>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if (count($tomas) > 3): ?>
                                        <span class="dosis text-secondary"><span>+<?= count($tomas) - 3 ?> más</span></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if ($tomasSemana === []): ?>
                    <p class="text-secondary small mt-3 mb-0">Esta semana no hay tomas programadas. Cuando tu médico te recete o agregues un recordatorio, aparecerán aquí.</p>
                <?php endif; ?>
            </div>

            <!-- === TRATAMIENTOS === -->
            <section class="ma-panel mt-4" aria-labelledby="tituloTratamientos">
                <h2 class="ma-panel-titulo" id="tituloTratamientos">Tratamientos <span class="ma-chip"><?= count($enCurso) ?> en curso</span></h2>
                <?php if ($tratamientos === []): ?>
                    <div class="ma-vacio">
                        <i class="bi bi-capsule" aria-hidden="true"></i>
                        <strong>Sin tratamientos</strong>
                        Aquí verás los medicamentos que te recete tu médico y los recordatorios que agregues.
                    </div>
                <?php endif; ?>
                <div class="d-flex flex-column gap-4">
                    <?php foreach ([...$enCurso, ...$terminados] as $indice => $tratamiento): ?>
                        <?php
                        $terminado = (string) $tratamiento['fecha_fin'] <= $ahora;
                        $momento = Tratamiento::momentoDelDia((string) $tratamiento['fecha_inicio']);
                        $porcentajeDias = $tratamiento['duracion_dias'] / $maxDias * 100;
                        $avance = $tratamiento['total_tomas'] > 0 ? ($tratamiento['tomadas'] + $tratamiento['omitidas']) / $tratamiento['total_tomas'] * 100 : 0;
                        ?>
                        <article class="<?= $terminado ? 'opacity-75' : '' ?>" data-tratamiento="<?= (int) $tratamiento['id_tratamiento'] ?>">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                                <div>
                                    <h3 class="fs-6 fw-bold mb-1"><?= e($tratamiento['medicamento'] . ' · ' . $tratamiento['dosis']) ?></h3>
                                    <div class="small text-secondary">
                                        <?= e(ucfirst(Tratamiento::describirFrecuencia($tratamiento['frecuencia_horas']))) ?> · <?= $tratamiento['duracion_dias'] ?> día(s)
                                        · <?= $tratamiento['origen'] === Tratamiento::ORIGEN_DOCTOR
                                            ? 'recetado por Dr(a). ' . e($tratamiento['doctor_nombre'] . ' ' . $tratamiento['doctor_apellido'])
                                            : 'recordatorio propio' ?>
                                    </div>
                                    <?php if (!empty($tratamiento['notas'])): ?>
                                        <div class="small fst-italic text-secondary"><?= e((string) $tratamiento['notas']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($terminado): ?>
                                        <span class="ma-chip">Terminado</span>
                                    <?php elseif ($tratamiento['estado'] === Tratamiento::PAUSADO): ?>
                                        <span class="ma-chip aviso">Pausado</span>
                                    <?php endif; ?>
                                    <?php if ($tratamiento['apego'] !== null): ?>
                                        <span class="ma-chip <?= $tratamiento['apego'] >= 80 ? 'exito' : 'aviso' ?>"><?= $tratamiento['apego'] ?> % de apego</span>
                                    <?php endif; ?>
                                    <?php if (!$terminado): ?>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Opciones de <?= e((string) $tratamiento['medicamento']) ?>"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <?php if ($tratamiento['estado'] === Tratamiento::PAUSADO): ?>
                                                    <li><button class="dropdown-item" type="button" data-accion-tratamiento="reanudar" data-id="<?= (int) $tratamiento['id_tratamiento'] ?>"><i class="bi bi-play me-2" aria-hidden="true"></i>Reanudar avisos</button></li>
                                                <?php else: ?>
                                                    <li><button class="dropdown-item" type="button" data-accion-tratamiento="pausar" data-id="<?= (int) $tratamiento['id_tratamiento'] ?>"><i class="bi bi-pause me-2" aria-hidden="true"></i>Pausar avisos</button></li>
                                                <?php endif; ?>
                                                <?php if ($tratamiento['origen'] === Tratamiento::ORIGEN_PACIENTE): ?>
                                                    <li><button class="dropdown-item text-danger" type="button" data-accion-tratamiento="eliminar" data-id="<?= (int) $tratamiento['id_tratamiento'] ?>" data-nombre="<?= e((string) $tratamiento['medicamento']) ?>"><i class="bi bi-trash me-2" aria-hidden="true"></i>Eliminar</button></li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <!-- La barra mide exactamente los días del tratamiento; el relleno, las tomas ya registradas. -->
                            <div class="tratamiento-barra <?= e($momento) ?>" style="width: <?= round($porcentajeDias, 2) ?>%;" role="img"
                                 aria-label="<?= e($tratamiento['tomadas'] . ' de ' . $tratamiento['total_tomas'] . ' tomas tomadas') ?>">
                                <i style="width: <?= round($avance, 2) ?>%;"></i>
                            </div>
                            <div class="d-flex justify-content-between small text-secondary mt-1 ma-cifras" style="width: <?= round($porcentajeDias, 2) ?>%; min-width: 220px;">
                                <span><?= e(date('j', strtotime((string) $tratamiento['fecha_inicio'])) . ' ' . mes_corto((string) $tratamiento['fecha_inicio'])) ?></span>
                                <span><?= $tratamiento['tomadas'] ?> de <?= $tratamiento['total_tomas'] ?> tomas</span>
                                <span><?= e(date('j', strtotime((string) $tratamiento['fecha_fin'])) . ' ' . mes_corto((string) $tratamiento['fecha_fin'])) ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </section>

        <!-- === COLUMNA LATERAL: PRÓXIMA CITA Y HOY === -->
        <aside class="col-xl-3 order-1 order-xl-2">
            <div class="ma-panel">
                <h2 class="ma-panel-titulo">Próxima cita</h2>
                <?php if ($proximaCita !== null): ?>
                    <div class="d-flex gap-3 align-items-center">
                        <span class="ma-fecha"><b><?= e(date('j', strtotime((string) $proximaCita['fecha_cita']))) ?></b><small><?= e(dia_corto((string) $proximaCita['fecha_cita'])) ?></small></span>
                        <div>
                            <div class="fw-bold"><?= e(substr((string) $proximaCita['hora_inicio'], 0, 5)) ?> · Dr(a). <?= e($proximaCita['doctor_nombre'] . ' ' . $proximaCita['doctor_apellido']) ?></div>
                            <div class="small text-secondary"><?= e(ucfirst(fecha_larga((string) $proximaCita['fecha_cita']))) ?> · <?= e((string) $proximaCita['estado_cita']) ?></div>
                        </div>
                    </div>
                    <a href="historial.php" class="btn btn-light w-100 mt-3">Ver mis citas</a>
                <?php else: ?>
                    <p class="text-secondary mb-3">No tienes citas próximas.</p>
                    <a href="agenda.php" class="btn btn-primary w-100">Agendar una cita</a>
                <?php endif; ?>
            </div>

            <div class="ma-panel">
                <h2 class="ma-panel-titulo">Hoy <span class="ma-chip ma-cifras" id="contadorHoy"><?= count(array_filter($tomasHoy, static fn (array $t): bool => $t['estado'] === Tratamiento::TOMA_TOMADA)) ?> de <?= count($tomasHoy) ?></span></h2>
                <?php if ($tomasHoy === []): ?>
                    <p class="text-secondary mb-3">Hoy no tienes tomas programadas.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-3" id="listaHoy">
                        <?php foreach ($tomasHoy as $toma): ?>
                            <?php
                            $yaTomada = $toma['estado'] === Tratamiento::TOMA_TOMADA;
                            $atrasada = $toma['estado'] === Tratamiento::TOMA_PENDIENTE && (string) $toma['programada_en'] < $ahora;
                            ?>
                            <li class="toma <?= e($toma['momento']) ?><?= $yaTomada ? ' tomada' : '' ?>" data-toma="<?= (int) $toma['id_toma'] ?>"
                                data-compartimento="<?= e($hoy . '|' . $toma['momento']) ?>">
                                <span class="toma-punto" aria-hidden="true"></span>
                                <div class="min-w-0">
                                    <div><span class="toma-hora"><?= e($hora((string) $toma['programada_en'])) ?></span> <span class="toma-medicamento fw-bold"><?= e((string) $toma['medicamento']) ?></span></div>
                                    <div class="toma-detalle text-truncate">
                                        <?= e((string) $toma['dosis']) ?>
                                        <?php if ($toma['estado'] === Tratamiento::TOMA_OMITIDA): ?> · <span class="text-danger">omitida</span><?php elseif ($atrasada): ?> · <span class="text-warning-emphasis">sin registrar</span><?php endif; ?>
                                        <?php if ($toma['estado_tratamiento'] === Tratamiento::PAUSADO): ?> · pausado<?php endif; ?>
                                    </div>
                                </div>
                                <button type="button" class="toma-boton" data-marcar-toma="<?= (int) $toma['id_toma'] ?>"
                                        aria-pressed="<?= $yaTomada ? 'true' : 'false' ?>"
                                        aria-label="<?= e(($yaTomada ? 'Deshacer: ' : 'Marcar como tomada: ') . $toma['medicamento'] . ' de las ' . $hora((string) $toma['programada_en'])) ?>">
                                    <i class="bi bi-check-lg" aria-hidden="true"></i>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#modalRecordatorio">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Agregar recordatorio
                </button>
            </div>
        </aside>
    </div>

    <!-- === RECETAS RECIBIDAS === -->
    <section class="ma-panel mt-4" aria-labelledby="tituloRecetas">
        <h2 class="ma-panel-titulo" id="tituloRecetas">Mis recetas</h2>
        <?php if ($recetas === []): ?>
            <p class="text-secondary mb-0">Cuando tu médico te recete, la receta aparecerá aquí con su PDF.</p>
        <?php else: ?>
            <ul class="list-unstyled mb-0">
                <?php foreach ($recetas as $receta): ?>
                    <li class="d-flex flex-wrap align-items-center gap-3 py-3 border-bottom">
                        <span class="ma-fecha"><b><?= e(date('j', strtotime((string) $receta['fecha_emision']))) ?></b><small><?= e(mes_corto((string) $receta['fecha_emision'])) ?></small></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-bold"><?= e((string) $receta['diagnostico']) ?></div>
                            <div class="small text-secondary">Dr(a). <?= e($receta['doctor_nombre'] . ' ' . $receta['doctor_apellido']) ?><?= !empty($receta['nombre_especialidad']) ? ' · ' . e((string) $receta['nombre_especialidad']) : '' ?> · <?= (int) $receta['medicamentos'] ?> medicamento(s) · <?= e(Receta::folio((int) $receta['id_receta'])) ?></div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-light" data-ver-receta="<?= (int) $receta['id_receta'] ?>">Ver</button>
                            <a class="btn btn-sm btn-outline-primary" href="../api/generar_receta_pdf.php?id_receta=<?= (int) $receta['id_receta'] ?>"><i class="bi bi-download me-1" aria-hidden="true"></i>PDF</a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<!-- === MODAL: AGREGAR RECORDATORIO === -->
<div class="modal fade" id="modalRecordatorio" tabindex="-1" aria-labelledby="modalRecordatorioTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formRecordatorio" novalidate>
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="modalRecordatorioTitulo">Agregar recordatorio</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div id="recordatorioAlerta"></div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="rec_medicamento" class="form-label">Medicamento</label>
                            <input type="text" class="form-control" id="rec_medicamento" name="medicamento" maxlength="120" required placeholder="Ej. Paracetamol">
                        </div>
                        <div class="col-12">
                            <label for="rec_dosis" class="form-label">Dosis</label>
                            <input type="text" class="form-control" id="rec_dosis" name="dosis" maxlength="120" required placeholder="Ej. 1 tableta de 500 mg">
                        </div>
                        <div class="col-6">
                            <label for="rec_frecuencia" class="form-label">Frecuencia</label>
                            <select class="form-select" id="rec_frecuencia" name="frecuencia_horas">
                                <?php foreach (Tratamiento::FRECUENCIAS as $horas): ?>
                                    <option value="<?= $horas ?>"<?= $horas === 8 ? ' selected' : '' ?>><?= e(ucfirst(Tratamiento::describirFrecuencia($horas))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="rec_duracion" class="form-label">Durante (días)</label>
                            <input type="number" class="form-control" id="rec_duracion" name="duracion_dias" min="1" max="<?= Tratamiento::DURACION_MAXIMA_DIAS ?>" value="7" required>
                        </div>
                        <div class="col-12">
                            <label for="rec_inicio" class="form-label">Primera toma</label>
                            <input type="datetime-local" class="form-control" id="rec_inicio" name="fecha_inicio" required>
                        </div>
                        <div class="col-12">
                            <label for="rec_notas" class="form-label">Nota <span class="fw-semibold text-muted">(opcional)</span></label>
                            <input type="text" class="form-control" id="rec_notas" name="notas" maxlength="255" placeholder="Ej. después de comer">
                        </div>
                    </div>
                    <p class="small fw-semibold text-secondary mt-3 mb-0" id="recordatorioVistaPrevia" aria-live="polite"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarRecordatorio">Agregar al pastillero</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require __DIR__ . '/../src/views/partials/modal_detalle_receta.php';
require __DIR__ . '/../src/views/layout/footer.php';
?>
