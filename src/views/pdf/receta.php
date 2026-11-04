<?php
/**
 * PLANTILLA PDF: RECETA MÉDICA
 *
 * Variables recibidas desde PdfService::renderizarVista():
 * @var array<string, mixed> $receta       Receta completa (Receta::obtenerDetalle()).
 * @var string               $folio        Folio legible (RX-000000).
 * @var callable             $frecuencia   Texto de una frecuencia en horas (Tratamiento::describirFrecuencia).
 * @var string               $fechaReporte Fecha y hora de generación.
 */

$paciente = trim($receta['paciente_nombre'] . ' ' . $receta['paciente_apellido'] . ' ' . ($receta['paciente_apellido_materno'] ?? ''));
$doctor = 'Dr(a). ' . $receta['doctor_nombre'] . ' ' . $receta['doctor_apellido'];
$edad = !empty($receta['fecha_nacimiento']) ? (new DateTimeImmutable((string) $receta['fecha_nacimiento']))->diff(new DateTimeImmutable())->y . ' años' : '—';
$emision = new DateTimeImmutable((string) $receta['fecha_emision']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Receta <?= e($folio) ?></title>
    <?php require __DIR__ . '/estilos.php'; ?>
    <style>
        .membrete td { border: 0; padding: 0; vertical-align: top; }
        .marca { font-size: 22px; font-weight: bold; color: #16233b; }
        .medico { font-size: 14px; font-weight: bold; margin-top: 6px; }
        .tenue { color: #4d5b76; }
        .folio { text-align: right; }
        .folio b { font-size: 16px; color: #1f57c3; }
        .franja { height: 6px; background-color: #2f6fe4; margin: 14px 0 18px 0; }
        .paciente td { border: 0; padding: 3px 0; }
        .caja { border: 1px solid #e1e7f0; background-color: #f7f9fc; padding: 10px 12px; margin-top: 8px; }
        .firma { margin-top: 70px; width: 260px; border-top: 1px solid #16233b; text-align: center; padding-top: 6px; }
        .pie { position: fixed; bottom: 0; left: 0; right: 0; font-size: 9px; color: #6b7894; text-align: center; }
    </style>
</head>
<body>
    <table class="membrete">
        <tr>
            <td>
                <div class="marca">MediAgenda</div>
                <div class="medico"><?= e($doctor) ?></div>
                <div class="tenue"><?= e($receta['nombre_especialidad'] ?? 'Medicina') ?><?= !empty($receta['consultorio_nombre']) ? ' · ' . e($receta['consultorio_nombre']) : '' ?></div>
            </td>
            <td class="folio">
                <div class="tenue">Folio</div>
                <b><?= e($folio) ?></b>
                <div class="tenue"><?= e($emision->format('d/m/Y H:i')) ?></div>
            </td>
        </tr>
    </table>

    <div class="franja"></div>

    <table class="paciente">
        <tr><td style="width: 110px;"><b>Paciente</b></td><td><?= e($paciente) ?></td></tr>
        <tr><td><b>Edad</b></td><td><?= e($edad) ?></td></tr>
    </table>

    <h2>Diagnóstico</h2>
    <div class="caja"><?= e((string) $receta['diagnostico']) ?></div>

    <h2>Medicamentos</h2>
    <table>
        <thead>
            <tr><th>Medicamento</th><th>Dosis</th><th>Frecuencia</th><th>Duración</th><th>Primera toma</th></tr>
        </thead>
        <tbody>
            <?php foreach ($receta['medicamentos'] as $medicamento): ?>
                <tr>
                    <td><b><?= e((string) $medicamento['medicamento']) ?></b><?= !empty($medicamento['notas']) ? '<br><span class="tenue">' . e((string) $medicamento['notas']) . '</span>' : '' ?></td>
                    <td><?= e((string) $medicamento['dosis']) ?></td>
                    <td><?= e($frecuencia((int) $medicamento['frecuencia_horas'])) ?></td>
                    <td><?= (int) $medicamento['duracion_dias'] ?> día(s)</td>
                    <td><?= e((new DateTimeImmutable((string) $medicamento['fecha_inicio']))->format('d/m/Y H:i')) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (!empty($receta['indicaciones'])): ?>
        <h2>Indicaciones</h2>
        <div class="caja"><?= nl2br(e((string) $receta['indicaciones'])) ?></div>
    <?php endif; ?>

    <div class="firma"><?= e($doctor) ?></div>

    <div class="pie">Generado el <?= e($fechaReporte) ?> · Los recordatorios de cada toma están programados en MediAgenda.</div>
</body>
</html>
