<?php

/**
 * ENDPOINT: RECETA EN PDF
 *
 * Genera la receta para imprimirla o guardarla.
 *
 * Método:     GET (enlace de descarga, sin efectos secundarios)
 * Acceso:     administrador, el médico que la emitió o el paciente que la recibió
 * Parámetros: id_receta
 * Respuesta:  archivo PDF
 */

require_once __DIR__ . '/../config/config.php';

use App\Http\Sesion;
use App\Models\Receta;
use App\Models\Tratamiento;
use App\Services\PdfService;

if (!Sesion::estaAutenticado()) {
    http_response_code(401);
    exit('Inicia sesión para descargar la receta.');
}

$idReceta = filter_var($_GET['id_receta'] ?? null, FILTER_VALIDATE_INT);
$receta = $idReceta !== false ? (new Receta())->obtenerDetalle((int) $idReceta) : null;

if ($receta === null || !Receta::puedeVer($receta)) {
    http_response_code(404);
    exit('Receta no encontrada.');
}

$folio = Receta::folio((int) $receta['id_receta']);

$html = PdfService::renderizarVista('receta', [
    'receta'      => $receta,
    'folio'       => $folio,
    'frecuencia'  => Tratamiento::describirFrecuencia(...),
    'fechaReporte' => date('d/m/Y H:i'),
]);

PdfService::descargar($html, "Receta_{$folio}.pdf");
