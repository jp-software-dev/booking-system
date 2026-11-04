<?php

/**
 * VISTA: HISTORIAL DE RECETAS EMITIDAS (DOCTOR)
 *
 * Lista las recetas que emitió el médico, con filtros por paciente y por fecha.
 * Cada receta se abre en un modal con sus medicamentos y el apego del paciente
 * (tomas registradas), y se puede descargar en PDF.
 *
 * Parámetros: paciente, desde, hasta (Y-m-d).
 * Acceso: doctor.
 */

require_once __DIR__ . '/../config/config.php';

use App\Http\Sesion;
use App\Models\Receta;

Sesion::exigirRol(Sesion::ROL_DOCTOR);

$idDoctor = Sesion::doctorId();
if ($idDoctor === null) {
    Sesion::redirigir('login.php');
}

// === FILTROS ===
$esFecha = static fn (string $valor): bool => DateTimeImmutable::createFromFormat('!Y-m-d', $valor) !== false;
$filtros = [
    'paciente' => mb_substr(trim((string) ($_GET['paciente'] ?? '')), 0, 60),
    'desde'    => $esFecha((string) ($_GET['desde'] ?? '')) ? (string) $_GET['desde'] : '',
    'hasta'    => $esFecha((string) ($_GET['hasta'] ?? '')) ? (string) $_GET['hasta'] : '',
];
$hayFiltros = implode('', $filtros) !== '';

$recetas = (new Receta())->listarPorDoctor($idDoctor, $filtros);
$totalMedicamentos = array_sum(array_map(static fn (array $r): int => (int) $r['medicamentos'], $recetas));

// === VISTA ===
$tituloPagina = 'Recetas emitidas';
$scriptsExtra = ['js/receta.js'];
require __DIR__ . '/../src/views/layout/header.php';
?>
<div class="container ma-pagina">
    <div class="ma-encabezado-pagina">
        <div>
            <h1 class="ma-titulo">Recetas emitidas</h1>
            <p class="ma-subtitulo"><?= count($recetas) ?> receta(s) · <?= $totalMedicamentos ?> medicamento(s) recetado(s)<?= $hayFiltros ? ' con estos filtros' : '' ?></p>
        </div>
    </div>

    <form method="GET" class="ma-panel mb-4 row g-2 align-items-end" role="search">
        <div class="col-md-5">
            <label for="filtroPaciente" class="form-label">Paciente</label>
            <input type="search" class="form-control" id="filtroPaciente" name="paciente" value="<?= e($filtros['paciente']) ?>" placeholder="Nombre o apellido">
        </div>
        <div class="col-6 col-md-2">
            <label for="filtroDesde" class="form-label">Desde</label>
            <input type="date" class="form-control" id="filtroDesde" name="desde" value="<?= e($filtros['desde']) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label for="filtroHasta" class="form-label">Hasta</label>
            <input type="date" class="form-control" id="filtroHasta" name="hasta" value="<?= e($filtros['hasta']) ?>">
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">Buscar</button>
            <?php if ($hayFiltros): ?>
                <a href="doctor_recetas.php" class="btn btn-light">Limpiar</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($recetas === []): ?>
        <div class="ma-panel ma-vacio">
            <i class="bi bi-file-earmark-medical" aria-hidden="true"></i>
            <strong><?= $hayFiltros ? 'Ninguna receta coincide' : 'Todavía no emites recetas' ?></strong>
            <?= $hayFiltros ? 'Prueba con otro nombre o rango de fechas.' : 'Emítelas desde tus citas en "Mis citas".' ?>
        </div>
    <?php else: ?>
        <div class="card overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr><th>Folio</th><th>Fecha</th><th>Paciente</th><th>Diagnóstico</th><th class="text-center">Medicamentos</th><th class="text-end">Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recetas as $receta): ?>
                            <tr>
                                <td data-label="Folio" class="fw-bold ma-cifras"><?= e(Receta::folio((int) $receta['id_receta'])) ?></td>
                                <td data-label="Fecha" class="ma-cifras text-nowrap"><?= e(fecha_corta((string) $receta['fecha_emision'])) ?></td>
                                <td data-label="Paciente" class="fw-semibold"><?= e($receta['paciente_nombre'] . ' ' . $receta['paciente_apellido']) ?></td>
                                <td data-label="Diagnóstico" class="text-wrap" style="max-width: 32ch;"><?= e((string) $receta['diagnostico']) ?></td>
                                <td data-label="Medicamentos" class="text-center ma-cifras"><?= (int) $receta['medicamentos'] ?></td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-light" data-ver-receta="<?= (int) $receta['id_receta'] ?>">Ver</button>
                                    <a class="btn btn-sm btn-outline-primary" href="../api/generar_receta_pdf.php?id_receta=<?= (int) $receta['id_receta'] ?>" aria-label="Descargar PDF de la receta <?= e(Receta::folio((int) $receta['id_receta'])) ?>"><i class="bi bi-download" aria-hidden="true"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
require __DIR__ . '/../src/views/partials/modal_detalle_receta.php';
require __DIR__ . '/../src/views/layout/footer.php';
?>
