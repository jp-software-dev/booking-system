<?php

/**
 * ENDPOINT: ELIMINAR CITA (ADMIN)
 *
 * Borrado físico de una cita. Lógica en AppointmentController::delete().
 *
 * Método:    POST (JSON)
 * Acceso:    administrador, con token CSRF
 * Entrada:   { id_cita }
 * Respuesta: { status, message }
 */

require_once __DIR__ . '/../config/config.php';

use App\Controllers\AppointmentController;
use App\Http\Api;
use App\Http\Sesion;

Api::iniciar('POST');
Api::exigirSesion(Sesion::ROL_ADMIN);
$entrada = Api::leerJson();
Api::exigirCsrf($entrada);

Api::responderResultado((new AppointmentController())->delete((int) ($entrada['id_cita'] ?? 0)));
