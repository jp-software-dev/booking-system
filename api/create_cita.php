<?php

/**
 * ENDPOINT: CREAR CITA
 *
 * El paciente agenda para sí mismo desde la agenda; el administrador agenda a
 * nombre de un paciente indicando su correo. La lógica está en
 * AppointmentController::create().
 *
 * Método:    POST (JSON)
 * Acceso:    paciente o administrador, con token CSRF
 * Entrada:   { id_doctor, fecha_cita, hora_inicio, motivo, email (solo admin) }
 * Respuesta: { status, message, id_cita }
 */

require_once __DIR__ . '/../config/config.php';

use App\Controllers\AppointmentController;
use App\Http\Api;
use App\Http\Sesion;

Api::iniciar('POST');
$idUsuario = Api::exigirSesion(Sesion::ROL_PACIENTE, Sesion::ROL_ADMIN);
$entrada = Api::leerJson();
Api::exigirCsrf($entrada);

Api::responderResultado((new AppointmentController())->create($entrada, $idUsuario, (string) Sesion::rol()));
