<?php

/**
 * ENDPOINT: SUSCRIBIR ESTE NAVEGADOR A LAS NOTIFICACIONES PUSH
 *
 * Guarda la suscripción que creó el navegador (PushManager.subscribe) para
 * enviarle los avisos de cada toma.
 *
 * Método:    POST (JSON)
 * Acceso:    paciente, con token CSRF
 * Entrada:   { endpoint, keys: { p256dh, auth } }
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
$p256dh = (string) ($entrada['keys']['p256dh'] ?? '');
$auth = (string) ($entrada['keys']['auth'] ?? '');

// Solo URLs https de un servicio push y claves en base64url de longitud razonable.
$esBase64Url = static fn (string $valor, int $max): bool => $valor !== '' && strlen($valor) <= $max && preg_match('/^[A-Za-z0-9_-]+=*$/', $valor) === 1;
if (!str_starts_with($endpoint, 'https://') || strlen($endpoint) > 1000 || filter_var($endpoint, FILTER_VALIDATE_URL) === false
    || !$esBase64Url($p256dh, 255) || !$esBase64Url($auth, 100)) {
    Api::error('Suscripción inválida');
}

$agente = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
(new SuscripcionPush())->guardar($idPaciente, $endpoint, $p256dh, $auth, $agente !== '' ? $agente : null);

Api::exito('Avisos activados en este navegador');
