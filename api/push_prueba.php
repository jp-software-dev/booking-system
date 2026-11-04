<?php

/**
 * ENDPOINT: AVISO DE PRUEBA
 *
 * Envía un aviso a los navegadores del paciente para que vea cómo le llegarán
 * los recordatorios. Limitado a 3 por cada 10 minutos.
 *
 * Método:    POST (JSON)
 * Acceso:    paciente, con token CSRF
 * Respuesta: { status, message, enviados }
 */

require_once __DIR__ . '/../config/config.php';

use App\Helpers\RateLimiter;
use App\Http\Api;
use App\Http\Sesion;
use App\Services\NotificadorPush;

Api::iniciar('POST');
$idPaciente = Api::exigirSesion(Sesion::ROL_PACIENTE);
$entrada = Api::leerJson();
Api::exigirCsrf($entrada);

if (!NotificadorPush::configurado()) {
    Api::error('Las notificaciones no están configuradas en este servidor', 503);
}

$claveLimite = 'push_prueba_' . $idPaciente;
if (RateLimiter::tooManyAttempts($claveLimite, 3, 600)) {
    Api::error('Ya enviaste varios avisos de prueba. Espera unos minutos.', 429);
}
RateLimiter::registerAttempt($claveLimite);

$resultado = (new NotificadorPush())->enviarAPaciente($idPaciente, [
    'titulo'   => 'Así te avisaremos',
    'cuerpo'   => 'A la hora de cada toma recibirás un aviso como este.',
    'url'      => 'tratamientos.php',
    'etiqueta' => 'prueba-' . $idPaciente,
]);

if ($resultado['enviados'] === 0) {
    Api::error('No se pudo entregar el aviso. Vuelve a activar las notificaciones en este navegador.', 502);
}

Api::exito('Aviso de prueba enviado', ['enviados' => $resultado['enviados']]);
