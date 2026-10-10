<?php

/**
 * ENDPOINT: SOLICITUD DE RECUPERACIÓN DE CONTRASEÑA
 *
 * Si el correo pertenece a una cuenta activa, envía un enlace de un solo uso
 * (vigente 1 hora) para elegir una contraseña nueva.
 *
 * Método:    POST (JSON)
 * Acceso:    público, con token CSRF
 * Entrada:   { email }
 * Respuesta: { status, message }
 *
 * La respuesta es la misma exista o no el correo, para no revelar qué
 * cuentas están registradas. El límite por IP evita usar el endpoint para
 * enviar correos masivos.
 */

require_once __DIR__ . '/../config/config.php';

use App\Helpers\RateLimiter;
use App\Helpers\ValidationHelper;
use App\Http\Api;
use App\Models\Paciente;
use App\Models\TokenPassword;
use App\Services\EmailService;

Api::iniciar('POST');
$entrada = Api::leerJson();
Api::exigirCsrf($entrada);

// === LÍMITE DE SOLICITUDES POR IP ===
$claveLimite = RateLimiter::claveParaIp('recuperar');
if (RateLimiter::tooManyAttempts($claveLimite, 5, 3600)) {
    Api::error('Hiciste demasiadas solicitudes. Intenta de nuevo más tarde.', 429);
}
RateLimiter::registerAttempt($claveLimite);

// === VALIDACIÓN ===
$email = trim((string) ($entrada['email'] ?? ''));
if (!ValidationHelper::validateEmail($email)) {
    Api::error('Correo electrónico inválido');
}

// === ENVÍO DEL ENLACE (solo si la cuenta existe y está activa) ===
if ((new Paciente())->buscarIdActivoPorEmail($email) !== null) {
    $token = (new TokenPassword())->crearRestablecimiento($email);
    $enlace = BASE_URL . 'public/restablecer.php?token=' . $token;

    if (!EmailService::enviarEnlaceRecuperacion($email, $enlace)) {
        error_log('No se pudo enviar el correo de recuperación de contraseña.');
    }
}

Api::exito('Si el correo está registrado, te enviamos un enlace para restablecer tu contraseña.');
