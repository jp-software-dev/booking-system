<?php
/**
 * PLANTILLA PDF: DATOS PERSONALES DEL PACIENTE (DERECHO ARCO)
 *
 * Variables recibidas desde PdfService::renderizarVista():
 * @var array<string, mixed>       $paciente     Perfil del paciente.
 * @var list<array<string, mixed>> $citas        Historial completo de citas.
 * @var string                     $fechaReporte Fecha y hora de generación.
 */

$nombreCompleto = trim($paciente['nombre'] . ' ' . $paciente['apellido_paterno'] . ' ' . ($paciente['apellido_materno'] ?? ''));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis datos personales</title>
    <?php require __DIR__ . '/estilos.php'; ?>
</head>
<body>
    <div class="encabezado">
        <h1>MediAgenda</h1>
        <p>Exportación de datos personales (derecho de acceso ARCO)</p>
        <p>Generado el: <?= e($fechaReporte) ?></p>
    </div>

    <h2>Datos personales</h2>
    <table class="tabla-datos">
        <tr><td>Nombre completo</td><td><?= e($nombreCompleto) ?></td></tr>
        <tr><td>Fecha de nacimiento</td><td><?= e($paciente['fecha_nacimiento'] ?? 'N/A') ?></td></tr>
        <tr><td>Género</td><td><?= e($paciente['genero'] ?? 'N/A') ?></td></tr>
        <tr><td>Teléfono</td><td><?= e($paciente['telefono'] ?? 'N/A') ?></td></tr>
        <tr><td>Correo electrónico</td><td><?= e($paciente['email']) ?></td></tr>
        <tr><td>Recibe notificaciones por correo</td><td><?= $paciente['recibir_notificaciones'] ? 'Sí' : 'No' ?></td></tr>
        <tr><td>Cuenta creada</td><td><?= e($paciente['created_at'] ?? 'N/A') ?></td></tr>
    </table>

    <h2>Historial de citas</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha y hora</th>
                <th>Médico</th>
                <th>Consultorio</th>
                <th>Motivo</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($citas as $cita): ?>
                <tr>
                    <td>#<?= (int) $cita['id_cita'] ?></td>
                    <td><?= fecha_corta($cita['fecha_cita']) ?> <?= hora_corta($cita['hora_inicio']) ?></td>
                    <td>Dr. <?= e($cita['doctor_nombre'] . ' ' . $cita['doctor_apellido']) ?></td>
                    <td><?= e($cita['consultorio_nombre'] ?? 'N/A') ?></td>
                    <td><?= e($cita['motivo_consulta']) ?></td>
                    <td><?= e($cita['estado_cita']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($citas === []): ?>
                <tr><td colspan="6" class="vacio">Sin citas registradas.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
