<?php

/**
 * ENDPOINT: HORARIOS OCUPADOS PARA LA AGENDA
 *
 * Devuelve los horarios ya reservados de hoy en adelante para que la agenda
 * (calendar.js) los marque como no disponibles según el médico elegido.
 *
 * Método:    GET
 * Acceso:    cualquier usuario autenticado
 * Respuesta: [ { id_doctor, fecha, hora }, ... ]
 *
 * Minimización de datos: no incluye paciente ni motivo de consulta.
 */

require_once __DIR__ . '/../config/config.php';

use App\Http\Api;
use App\Models\Cita;

Api::iniciar('GET');
Api::exigirSesion();

Api::json((new Cita())->listarHorariosOcupados(date('Y-m-d')));
