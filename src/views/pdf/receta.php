<?php
/**
 * PLANTILLA PDF: RECETA MÉDICA
 *
 * Membrete con folio, datos del médico y del paciente, diagnóstico, prescripción
 * (una fila por medicamento con su frecuencia, duración y periodo), indicaciones
 * generales y espacio para la firma. Solo usa CSS 2.1 (lo que entiende Dompdf).
 *
 * Variables recibidas desde PdfService::renderizarVista():
 * @var array<string, mixed> $receta       Receta completa (Receta::obtenerDetalle()).
 * @var string               $folio        Folio legible (RX-000000).
 * @var callable             $frecuencia   Texto de una frecuencia en horas (Tratamiento::describirFrecuencia).
 * @var string               $fechaReporte Fecha y hora de generación.
 */

use App\Demo\ModoDemo;

$paciente = trim($receta['paciente_nombre'] . ' ' . $receta['paciente_apellido'] . ' ' . ($receta['paciente_apellido_materno'] ?? ''));
$doctor = 'Dr(a). ' . $receta['doctor_nombre'] . ' ' . $receta['doctor_apellido'];
$especialidad = (string) ($receta['nombre_especialidad'] ?? 'Medicina general');
$emision = new DateTimeImmutable((string) $receta['fecha_emision']);

$nacimiento = !empty($receta['fecha_nacimiento']) ? new DateTimeImmutable((string) $receta['fecha_nacimiento']) : null;
$edad = $nacimiento !== null ? $nacimiento->diff($emision)->y . ' años' : '—';

$fecha = static fn (string $valor): string => (new DateTimeImmutable($valor))->format('d/m/Y');
$fechaHora = static fn (string $valor): string => (new DateTimeImmutable($valor))->format('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Receta <?= e($folio) ?></title>
    <?php require __DIR__ . '/estilos.php'; ?>
    <style>
        @page { margin: 34px 40px 58px 40px; }
        body { font-size: 11px; line-height: 1.35; }

        table.sin-borde td, table.sin-borde th { border: 0; padding: 0; background-color: transparent; }
        .tenue { color: #4d5b76; }
        .minimo { font-size: 9px; color: #6b7894; }

        .marca { font-size: 24px; font-weight: bold; color: #16233b; }
        .tipo { font-size: 12px; color: #1f57c3; font-weight: bold; margin-top: 2px; }
        .folio-caja { text-align: right; width: 210px; vertical-align: top; }
        .folio-caja b { font-size: 18px; color: #1f57c3; }
        .franja { height: 4px; background-color: #2f6fe4; margin: 16px 0 14px 0; }

        table.partes { margin-top: 0; }
        table.partes td { border: 1px solid #e1e7f0; background-color: #f7f9fc; padding: 9px 12px; vertical-align: top; width: 50%; }
        .rotulo { font-size: 9px; color: #6b7894; font-weight: bold; }
        .nombre { font-size: 13px; font-weight: bold; color: #16233b; margin: 2px 0 3px 0; }

        h2 { font-size: 12px; color: #1f57c3; margin: 18px 0 6px 0; padding-bottom: 3px; border-bottom: 1px solid #cfd8e6; }
        .texto { padding: 2px 2px 0 2px; }

        table.rx { margin-top: 4px; }
        table.rx th { background-color: #16233b; color: #ffffff; border: 1px solid #16233b; padding: 6px 8px; font-size: 10px; }
        table.rx td { border: 1px solid #e1e7f0; padding: 7px 8px; vertical-align: top; }
        table.rx tr.par td { background-color: #f7f9fc; }
        .num { width: 22px; text-align: center; font-weight: bold; color: #1f57c3; }
        .medicamento { font-size: 12px; font-weight: bold; color: #16233b; }
        .nota { color: #4d5b76; font-style: italic; margin-top: 3px; }

        .indicaciones { border: 1px solid #e1e7f0; padding: 9px 12px; background-color: #f7f9fc; }

        table.firma-tabla { margin-top: 56px; }
        .firma { width: 250px; border-top: 1px solid #16233b; text-align: center; padding-top: 5px; }
        .sello { width: 120px; border: 1px dashed #cfd8e6; height: 54px; text-align: center; color: #9aa6bd; font-size: 9px; padding-top: 20px; }

        .pie { position: fixed; bottom: -34px; left: 0; right: 0; font-size: 8px; color: #6b7894; text-align: center; border-top: 1px solid #e1e7f0; padding-top: 6px; }
    </style>
</head>
<body>
    <table class="sin-borde">
        <tr>
            <td style="vertical-align: top;">
                <div class="marca">MediAgenda</div>
                <div class="tipo">Receta médica</div>
            </td>
            <td class="folio-caja">
                <div class="rotulo">Folio</div>
                <b><?= e($folio) ?></b>
                <div class="tenue">Emitida el <?= e($fecha((string) $receta['fecha_emision'])) ?>, <?= e($emision->format('H:i')) ?></div>
            </td>
        </tr>
    </table>

    <div class="franja"></div>

    <table class="partes">
        <tr>
            <td>
                <div class="rotulo">Médico</div>
                <div class="nombre"><?= e($doctor) ?></div>
                <div class="tenue"><?= e($especialidad) ?></div>
                <?php if (!empty($receta['consultorio_nombre'])): ?>
                    <div class="tenue"><?= e((string) $receta['consultorio_nombre']) ?></div>
                <?php endif; ?>
            </td>
            <td>
                <div class="rotulo">Paciente</div>
                <div class="nombre"><?= e($paciente) ?></div>
                <div class="tenue">Edad: <?= e($edad) ?><?= $nacimiento !== null ? ' · Nacimiento: ' . e($nacimiento->format('d/m/Y')) : '' ?></div>
            </td>
        </tr>
    </table>

    <h2>Diagnóstico</h2>
    <div class="texto"><?= e((string) $receta['diagnostico']) ?></div>

    <h2>Prescripción</h2>
    <table class="rx">
        <thead>
            <tr>
                <th style="width: 22px;">#</th>
                <th>Medicamento y dosis</th>
                <th style="width: 82px;">Frecuencia</th>
                <th style="width: 58px;">Duración</th>
                <th style="width: 44px;">Tomas</th>
                <th style="width: 112px;">Periodo</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($receta['medicamentos'] as $indice => $medicamento): ?>
                <?php
                $horas = max(1, (int) $medicamento['frecuencia_horas']);
                $dias = (int) $medicamento['duracion_dias'];
                $tomas = (int) ($medicamento['total_tomas'] ?? 0);
                if ($tomas === 0) {
                    $tomas = (int) ceil($dias * 24 / $horas);
                }
                ?>
                <tr class="<?= $indice % 2 === 0 ? '' : 'par' ?>">
                    <td class="num"><?= $indice + 1 ?></td>
                    <td>
                        <div class="medicamento"><?= e((string) $medicamento['medicamento']) ?></div>
                        <div><?= e((string) $medicamento['dosis']) ?></div>
                        <?php if (!empty($medicamento['notas'])): ?>
                            <div class="nota"><?= e((string) $medicamento['notas']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= e(ucfirst($frecuencia($horas))) ?></td>
                    <td><?= $dias ?> día(s)</td>
                    <td><?= $tomas ?></td>
                    <td>
                        <?= e($fechaHora((string) $medicamento['fecha_inicio'])) ?><br>
                        <span class="tenue">al <?= e($fecha((string) $medicamento['fecha_fin'])) ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (!empty($receta['indicaciones'])): ?>
        <h2>Indicaciones generales</h2>
        <div class="indicaciones"><?= nl2br(e((string) $receta['indicaciones'])) ?></div>
    <?php endif; ?>

    <table class="sin-borde firma-tabla">
        <tr>
            <td style="vertical-align: bottom;">
                <div class="firma">
                    <b><?= e($doctor) ?></b><br>
                    <span class="tenue"><?= e($especialidad) ?></span><br>
                    <span class="minimo">Firma del médico</span>
                </div>
            </td>
            <td style="vertical-align: bottom; text-align: right;">
                <div class="sello" style="float: right;">Sello</div>
            </td>
        </tr>
    </table>

    <div class="pie">
        <?php if (ModoDemo::activo()): ?>
            Documento de demostración: los datos son ficticios y no tiene validez médica.<br>
        <?php endif; ?>
        <?= e($folio) ?> · Generada el <?= e($fechaReporte) ?> · Los recordatorios de cada toma quedan programados en MediAgenda.
    </div>
</body>
</html>
