<?php

/**
 * ENDPOINT: REPROGRAMAR CITA (PACIENTE)
 *
 * El paciente cambia la fecha, hora y motivo de una de sus citas pendientes o
 * confirmadas. La cita vuelve a Pendiente para que el administrador la
 * confirme otra vez. Lógica en AppointmentController::reschedule().
 *
 * Método:    POST (JSON)
 * Acceso:    paciente autenticado (solo sus propias citas), con token CSRF
 * Entrada:   { id_cita, fecha_cita, hora_inicio, motivo }
 * Respuesta: { status, message }
 */

require_once __DIR__ . '/../config/config.php';

use App\Controllers\AppointmentController;
use App\Http\Api;
use App\Http\Sesion;

Api::iniciar('POST');
$idPaciente = Api::exigirSesion(Sesion::ROL_PACIENTE);
$entrada = Api::leerJson();
Api::exigirCsrf($entrada);

Api::responderResultado(
    (new AppointmentController())->reschedule((int) ($entrada['id_cita'] ?? 0), $entrada, $idPaciente, Sesion::ROL_PACIENTE)
);
