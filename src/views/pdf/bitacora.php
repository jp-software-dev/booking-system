<?php
/**
 * PLANTILLA PDF: BITÁCORA DE AUDITORÍA
 *
 * Variables recibidas desde PdfService::renderizarVista():
 * @var list<array<string, mixed>> $registros    Registros de la bitácora (más recientes primero).
 * @var string                     $filtros      Descripción de los filtros aplicados.
 * @var string                     $fechaReporte Fecha y hora de generación.
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bitácora de auditoría</title>
    <?php require __DIR__ . '/estilos.php'; ?>
    <style>
        body { font-size: 10px; }
        td, th { padding: 5px 6px; vertical-align: top; }
        .fecha { white-space: nowrap; width: 105px; }
        .detalle { color: #4d5b76; }
    </style>
</head>
<body>
    <div class="encabezado">
        <h1>MediAgenda</h1>
        <p>Bitácora de auditoría (solo lectura) · <?= count($registros) ?> registro(s)</p>
        <p><?= e($filtros) ?> · Generado el <?= e($fechaReporte) ?></p>
    </div>

    <table>
        <thead>
            <tr><th>Fecha</th><th>Usuario</th><th>Rol</th><th>Acción</th><th>Entidad</th><th>Detalle</th></tr>
        </thead>
        <tbody>
            <?php foreach ($registros as $registro): ?>
                <tr>
                    <td class="fecha"><?= e(date('d/m/Y H:i:s', (int) strtotime((string) $registro['fecha']))) ?></td>
                    <td>#<?= (int) $registro['id_usuario'] ?><?= !empty($registro['nombre_usuario']) ? ' · ' . e((string) $registro['nombre_usuario']) : '' ?></td>
                    <td><?= e((string) $registro['rol']) ?></td>
                    <td><?= e((string) $registro['accion']) ?></td>
                    <td><?= e((string) $registro['entidad']) ?><?= $registro['id_entidad'] ? ' #' . (int) $registro['id_entidad'] : '' ?></td>
                    <td class="detalle"><?= e((string) ($registro['detalle'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($registros === []): ?>
                <tr><td colspan="6" class="vacio">No hay registros con estos filtros.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
