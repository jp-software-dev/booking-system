<?php

/**
 * ENDPOINT: CAMBIAR ESTADO DE UNA CITA (ADMIN)
 *
 * Actualiza el estado de una cita y, si queda Confirmada o Cancelada, avisa al
 * paciente por correo (según su preferencia de notificaciones). Lógica en
 * AppointmentController::changeStatus().
 *
 * Método:    POST (JSON)
 * Acceso:    administrador, con token CSRF
 * Entrada:   { id_cita, estado }
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

Api::responderResultado(
    (new AppointmentController())->changeStatus((int) ($entrada['id_cita'] ?? 0), (string) ($entrada['estado'] ?? ''))
);
