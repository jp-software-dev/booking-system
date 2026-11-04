<?php

/**
 * ENDPOINT: REGISTRAR UNA TOMA
 *
 * El paciente marca una toma como tomada u omitida, o la regresa a pendiente
 * (para deshacer). Una toma no se puede marcar como tomada más de una hora
 * antes de su hora programada.
 *
 * Método:    POST (JSON)
 * Acceso:    paciente, con token CSRF; solo sus propias tomas
 * Entrada:   { id_toma, estado: Tomada|Omitida|Pendiente }
 * Respuesta: { status, message, estado }
 */

require_once __DIR__ . '/../config/config.php';

use App\Http\Api;
use App\Http\Sesion;
use App\Models\Tratamiento;

Api::iniciar('POST');
$idPaciente = Api::exigirSesion(Sesion::ROL_PACIENTE);
$entrada = Api::leerJson();
Api::exigirCsrf($entrada);

$idToma = filter_var($entrada['id_toma'] ?? null, FILTER_VALIDATE_INT);
$estado = (string) ($entrada['estado'] ?? '');

if ($idToma === false) {
    Api::error('Toma no encontrada', 404);
}

$error = (new Tratamiento())->registrarToma((int) $idToma, $idPaciente, $estado);
if ($error !== null) {
    Api::error($error, $error === 'Toma no encontrada' ? 404 : 400);
}

$mensajes = [
    Tratamiento::TOMA_TOMADA    => 'Toma registrada',
    Tratamiento::TOMA_OMITIDA   => 'Toma marcada como omitida',
    Tratamiento::TOMA_PENDIENTE => 'Registro deshecho',
];

Api::exito($mensajes[$estado], ['estado' => $estado]);
