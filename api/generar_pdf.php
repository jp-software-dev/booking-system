<?php

/**
 * ENDPOINT: REPORTE PDF DE CITAS (ADMIN)
 *
 * Genera un PDF con las citas que cumplen los filtros activos del panel de
 * citas (admin.php) o del historial (admin_historial.php).
 *
 * Método:     GET (enlace de descarga, sin efectos secundarios)
 * Acceso:     administrador
 * Parámetros: id_cita, paciente, doctor, estado (ver FiltroCitas) y
 *             origen=historial para limitar a citas completadas o canceladas.
 * Respuesta:  archivo PDF, o una página HTML 413 si el reporte excede el límite de filas.
 */

require_once __DIR__ . '/../config/config.php';

use App\Helpers\FiltroCitas;
use App\Http\Sesion;
use App\Models\Cita;
use App\Models\Doctor;
use App\Services\PdfService;

/**
 * Máximo de filas por reporte. Dompdf consume mucha memoria por fila; con
 * miles de filas se queda sin memoria sin importar qué tan simple sea el HTML.
 */
const LIMITE_FILAS_PDF = 1000;

if (!Sesion::esAdmin()) {
    http_response_code(403);
    exit('Acceso denegado.');
}

// Margen de memoria adicional para Dompdf (complementa el límite de filas, no lo sustituye).
ini_set('memory_limit', '512M');

$filtro = FiltroCitas::desdeConsulta($_GET, ($_GET['origen'] ?? '') === 'historial');
$citas = new Cita();

// === LÍMITE DE FILAS ===
// Se cuenta antes de traer los datos para no pagar la consulta completa si se va a rechazar.
$totalFilas = $citas->contarConFiltro($filtro);

if ($totalFilas > LIMITE_FILAS_PDF) {
    http_response_code(413);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Reporte demasiado grande</title>
        <style>
            body { font-family: Helvetica, Arial, sans-serif; max-width: 640px; margin: 60px auto; color: #333; text-align: center; }
            h1 { color: #dc3545; font-size: 20px; }
            p { color: #555; line-height: 1.5; }
            a { display: inline-block; margin-top: 16px; color: #1a73e8; font-weight: bold; text-decoration: none; }
        </style>
    </head>
    <body>
        <h1>El reporte es demasiado grande para exportar</h1>
        <p>Hay <strong><?= number_format($totalFilas) ?></strong> citas que coinciden con estos filtros y el límite por exportación es de <strong><?= number_format(LIMITE_FILAS_PDF) ?></strong>.</p>
        <p>Aplica un filtro (paciente, médico, estado o ID de cita) para reducir el resultado antes de exportar.</p>
        <a href="javascript:history.back()">&laquo; Volver y ajustar filtros</a>
    </body>
    </html>
    <?php
    exit;
}

// === DATOS Y GENERACIÓN ===
$nombreDoctor = null;
if ($filtro->doctor !== '') {
    $doctor = (new Doctor())->obtenerPorId((int) $filtro->doctor);
    $nombreDoctor = $doctor !== null ? $doctor['nombre'] . ' ' . $doctor['apellido_paterno'] : null;
}

$html = PdfService::renderizarVista('historial_citas', [
    'citas'        => $citas->listarConFiltro($filtro),
    'subtitulo'    => $filtro->descripcion($nombreDoctor),
    'fechaReporte' => date('d/m/Y H:i'),
]);

PdfService::descargar($html, 'Historial_Medico_MediAgenda.pdf', 'landscape');
