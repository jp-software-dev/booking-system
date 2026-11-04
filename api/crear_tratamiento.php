<?php

/**
 * ENDPOINT: RECORDATORIO DE MEDICAMENTO DEL PACIENTE
 *
 * El paciente agrega por su cuenta un medicamento con su frecuencia y duración
 * (por ejemplo, una indicación que recibió fuera de la plataforma). Se calculan
 * y guardan todas sus tomas, igual que en una receta.
 *
 * Método:    POST (JSON)
 * Acceso:    paciente, con token CSRF
 * Entrada:   { medicamento, dosis, frecuencia_horas, duracion_dias, fecha_inicio (AAAA-MM-DDTHH:MM), notas }
 * Respuesta: { status, message, id_tratamiento, total_tomas }
 */

require_once __DIR__ . '/../config/config.php';

use App\Helpers\Bitacora;
use App\Helpers\ValidadorTratamiento;
use App\Http\Api;
use App\Http\Sesion;
use App\Models\Tratamiento;

/** Recordatorios propios activos permitidos por paciente (evita abusos en la demo pública). */
const MAX_RECORDATORIOS_ACTIVOS = 15;

Api::iniciar('POST');
$idPaciente = Api::exigirSesion(Sesion::ROL_PACIENTE);
$entrada = Api::leerJson();
Api::exigirCsrf($entrada);

[$datos, $error] = ValidadorTratamiento::validar($entrada);
if ($error !== null) {
    Api::error($error);
}

$tratamientos = new Tratamiento();
$propiosActivos = array_filter(
    $tratamientos->listarPorPaciente($idPaciente),
    static fn (array $t): bool => $t['origen'] === Tratamiento::ORIGEN_PACIENTE && $t['estado'] === Tratamiento::ACTIVO
);
if (count($propiosActivos) >= MAX_RECORDATORIOS_ACTIVOS) {
    Api::error('Tienes demasiados recordatorios activos. Elimina alguno que ya no uses.', 409);
}

$idTratamiento = $tratamientos->crear([
    'id_paciente' => $idPaciente,
    'id_receta'   => null,
    'origen'      => Tratamiento::ORIGEN_PACIENTE,
] + $datos);

$totalTomas = count(Tratamiento::calcularTomas($datos['fecha_inicio'], $datos['frecuencia_horas'], $datos['duracion_dias']));
Bitacora::registrar('crear_recordatorio', 'tratamiento', $idTratamiento, $datos['medicamento']);

Api::exito("Recordatorio creado: {$totalTomas} tomas de {$datos['medicamento']} en tu pastillero", [
    'id_tratamiento' => $idTratamiento,
    'total_tomas'    => $totalTomas,
]);
