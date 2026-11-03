<?php

/**
 * ENDPOINT: DETALLE DE UNA RECETA
 *
 * Devuelve la receta con sus medicamentos y el avance de cada uno (tomas
 * registradas y apego).
 *
 * Método:     GET
 * Acceso:     administrador, el médico que la emitió o el paciente que la recibió
 *             (cualquier otro recibe 404, para no revelar qué recetas existen)
 * Parámetros: id_receta
 * Respuesta:  { status, receta }
 */

require_once __DIR__ . '/../config/config.php';

use App\Http\Api;
use App\Models\Receta;

Api::iniciar('GET');
Api::exigirSesion();

$idReceta = filter_var($_GET['id_receta'] ?? null, FILTER_VALIDATE_INT);
$receta = $idReceta !== false ? (new Receta())->obtenerDetalle((int) $idReceta) : null;

if ($receta === null || !Receta::puedeVer($receta)) {
    Api::error('Receta no encontrada', 404);
}

$receta['folio'] = Receta::folio((int) $receta['id_receta']);

Api::exito('ok', ['receta' => $receta]);
