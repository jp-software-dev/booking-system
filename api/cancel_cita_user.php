<?php

/**
 * ENDPOINT: CANCELAR CITA (PACIENTE)
 *
 * El paciente cancela una de sus citas pendientes o confirmadas que todavía no
 * ocurre. El horario queda libre para otros pacientes. Lógica en
 * AppointmentController::cancel().
 *
 * Método:    POST (JSON)
 * Acceso:    paciente autenticado (solo sus propias citas), con token CSRF
 * Entrada:   { id_cita }
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

Api::responderResultado((new AppointmentController())->cancel((int) ($entrada['id_cita'] ?? 0), $idPaciente));
