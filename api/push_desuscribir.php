<?php

/**
 * ENDPOINT: DESACTIVAR LAS NOTIFICACIONES PUSH EN ESTE NAVEGADOR
 *
 * Método:    POST (JSON)
 * Acceso:    paciente, con token CSRF
 * Entrada:   { endpoint }
 * Respuesta: { status, message }
 */

require_once __DIR__ . '/../config/config.php';

use App\Http\Api;
use App\Http\Sesion;
use App\Models\SuscripcionPush;

Api::iniciar('POST');
$idPaciente = Api::exigirSesion(Sesion::ROL_PACIENTE);
$entrada = Api::leerJson();
Api::exigirCsrf($entrada);

$endpoint = (string) ($entrada['endpoint'] ?? '');
if ($endpoint === '') {
    Api::error('Suscripción inválida');
}

(new SuscripcionPush())->eliminar($idPaciente, $endpoint);

Api::exito('Avisos desactivados en este navegador');
