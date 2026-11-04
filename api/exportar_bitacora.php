<?php

/**
 * ENDPOINT: EXPORTAR LA BITÁCORA (ADMIN)
 *
 * Descarga los registros que cumplen los filtros de admin_bitacora.php en CSV
 * (para pasarlos a otra base o a una hoja de cálculo) o en PDF. La bitácora es
 * de solo lectura: este endpoint solo consulta, y la propia exportación queda
 * registrada en la bitácora.
 *
 * Método:     GET (enlace de descarga)
 * Acceso:     administrador
 * Parámetros: formato=csv|pdf, accion, usuario, desde, hasta
 * Respuesta:  archivo CSV o PDF, o una página 413 si el PDF excede el límite de filas
 */

require_once __DIR__ . '/../config/config.php';

use App\Helpers\Bitacora;
use App\Http\Sesion;
use App\Models\Auditoria;
use App\Services\PdfService;

/** Filas máximas por formato (Dompdf consume mucha memoria por fila). */
const LIMITE_CSV = 50000;
const LIMITE_PDF = 1500;

if (!Sesion::esAdmin()) {
    http_response_code(403);
    exit('Acceso denegado.');
}

$formato = ($_GET['formato'] ?? '') === 'pdf' ? 'pdf' : 'csv';
$filtros = Auditoria::filtrosDesdeConsulta($_GET);
$auditoria = new Auditoria();
$total = $auditoria->contarConFiltro($filtros);
$limite = $formato === 'pdf' ? LIMITE_PDF : LIMITE_CSV;

if ($total > $limite) {
    http_response_code(413);
    header('Content-Type: text/html; charset=utf-8');
    printf(
        '<!DOCTYPE html><html lang="es"><meta charset="UTF-8"><title>Exportación demasiado grande</title>'
        . '<body style="font-family: system-ui, sans-serif; max-width: 640px; margin: 60px auto; color: #16233b; text-align: center;">'
        . '<h1 style="font-size: 20px;">La exportación es demasiado grande</h1>'
        . '<p>Hay <strong>%s</strong> registros con estos filtros y el límite en %s es de <strong>%s</strong>. Reduce el rango de fechas o filtra por acción o usuario.</p>'
        . '<a href="javascript:history.back()" style="color: #1f57c3; font-weight: bold;">Volver a la bitácora</a></body></html>',
        number_format($total),
        strtoupper($formato),
        number_format($limite)
    );
    exit;
}

$registros = $auditoria->listarParaExportar($filtros, $limite);
$descripcionFiltros = implode(' · ', array_filter([
    $filtros['accion'] !== '' ? 'Acción: ' . $filtros['accion'] : '',
    $filtros['usuario'] !== '' ? 'Usuario: ' . $filtros['usuario'] : '',
    $filtros['desde'] !== '' ? 'Desde: ' . $filtros['desde'] : '',
    $filtros['hasta'] !== '' ? 'Hasta: ' . $filtros['hasta'] : '',
])) ?: 'Todos los registros';

Bitacora::registrar('exportar_bitacora', 'bitacora', 0, sprintf('%s · %d registro(s) · %s', strtoupper($formato), count($registros), $descripcionFiltros));
$nombreArchivo = 'Bitacora_MediAgenda_' . date('Y-m-d_His');

// === PDF ===
if ($formato === 'pdf') {
    ini_set('memory_limit', '512M');
    $html = PdfService::renderizarVista('bitacora', [
        'registros'    => $registros,
        'filtros'      => $descripcionFiltros,
        'fechaReporte' => date('d/m/Y H:i'),
    ]);
    PdfService::descargar($html, $nombreArchivo . '.pdf', 'landscape');
    exit;
}

// === CSV ===
/**
 * Neutraliza celdas que una hoja de cálculo interpretaría como fórmula
 * (inyección CSV): se antepone un apóstrofo a =, +, -, @, tabulador y retorno.
 */
$celdaSegura = static function (mixed $valor): string {
    $texto = (string) ($valor ?? '');

    return $texto !== '' && str_contains("=+-@\t\r", $texto[0]) ? "'" . $texto : $texto;
};

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.csv"');
header('Cache-Control: no-store');

$salida = fopen('php://output', 'wb');
fwrite($salida, "\xEF\xBB\xBF"); // BOM: Excel abre el archivo como UTF-8 (acentos correctos).
fputcsv($salida, ['ID', 'Fecha', 'ID usuario', 'Usuario', 'Rol', 'Acción', 'Entidad', 'ID entidad', 'Detalle'], ',', '"', '');

foreach ($registros as $registro) {
    fputcsv($salida, array_map($celdaSegura, [
        $registro['id'],
        $registro['fecha'],
        $registro['id_usuario'],
        $registro['nombre_usuario'],
        $registro['rol'],
        $registro['accion'],
        $registro['entidad'],
        $registro['id_entidad'],
        $registro['detalle'],
    ]), ',', '"', '');
}

fclose($salida);
