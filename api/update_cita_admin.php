<?php

/**
 * ENDPOINT: REPROGRAMAR CITA (ADMIN)
 *
 * El administrador cambia la fecha y hora de una cita pendiente o confirmada.
 * El estado de la cita se conserva. Lógica en AppointmentController::reschedule().
 *
 * Método:    POST (JSON)
 * Acceso:    administrador, con token CSRF
 * Entrada:   { id_cita, fecha_cita, hora_inicio }
 * Respuesta: { status, message }
 */

require_once __DIR__ . '/../config/config.php';

use App\Controllers\AppointmentController;
use App\Http\Api;
use App\Http\Sesion;

Api::iniciar('POST');
$idUsuario = Api::exigirSesion(Sesion::ROL_ADMIN);
$entrada = Api::leerJson();
Api::exigirCsrf($entrada);

Api::responderResultado(
    (new AppointmentController())->reschedule((int) ($entrada['id_cita'] ?? 0), $entrada, $idUsuario, Sesion::ROL_ADMIN)
);
