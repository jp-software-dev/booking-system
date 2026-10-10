<?php
/**
 * PLANTILLA PDF: REPORTE DE CITAS DEL PANEL ADMINISTRATIVO
 *
 * Variables recibidas desde PdfService::renderizarVista():
 * @var list<array<string, mixed>> $citas        Citas que cumplen el filtro.
 * @var string                     $subtitulo    Descripción de los filtros aplicados.
 * @var string                     $fechaReporte Fecha y hora de generación.
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de citas</title>
    <?php require __DIR__ . '/estilos.php'; ?>
</head>
<body>
    <div class="encabezado">
        <h1>MediAgenda</h1>
        <p><?= e($subtitulo) ?></p>
        <p>Generado el: <?= e($fechaReporte) ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Paciente</th>
                <th>Médico</th>
                <th>Fecha y hora</th>
                <th>Motivo</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($citas as $cita): ?>
                <tr>
                    <td>#<?= (int) $cita['id_cita'] ?></td>
                    <td><?= e($cita['paciente_nombre'] . ' ' . $cita['paciente_apellido']) ?></td>
                    <td>Dr. <?= e($cita['doctor_nombre'] . ' ' . $cita['doctor_apellido']) ?></td>
                    <td><?= fecha_corta($cita['fecha_cita']) ?> <?= hora_corta($cita['hora_inicio']) ?></td>
                    <td><?= e($cita['motivo_consulta']) ?></td>
                    <td class="estado-<?= e(str_replace(' ', '-', strtolower($cita['estado_cita']))) ?>"><?= e($cita['estado_cita']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($citas === []): ?>
                <tr><td colspan="6" class="vacio">No hay citas que coincidan con los filtros.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
