<?php

/**
 * ENDPOINT: SOLICITUD DE CAMBIO DE CONTRASEÑA (PACIENTE)
 *
 * El cambio no se aplica de inmediato: se valida la contraseña actual, se guarda
 * el hash de la nueva junto con un token de un solo uso y se envía un correo de
 * confirmación. El cambio se aplica cuando el paciente abre el enlace
 * (public/confirmar_cambio_password.php).
 *
 * Método:    POST (JSON)
 * Acceso:    paciente autenticado, con token CSRF
 * Entrada:   { password_actual, password_nueva, password_confirmar }
 * Respuesta: { status, message }
 */

require_once __DIR__ . '/../config/config.php';

use App\Demo\ModoDemo;
use App\Helpers\Bitacora;
use App\Helpers\RateLimiter;
use App\Http\Api;
use App\Http\Sesion;
use App\Models\Paciente;
use App\Models\TokenPassword;
use App\Services\EmailService;

Api::iniciar('POST');
$idPaciente = Api::exigirSesion(Sesion::ROL_PACIENTE);
$entrada = Api::leerJson();
Api::exigirCsrf($entrada);

// En modo demo la cuenta de paciente de prueba es compartida: no se puede modificar.
if (ModoDemo::esPacienteDemo($idPaciente)) {
    Api::error(ModoDemo::MENSAJE_BLOQUEO, 403);
}

$actual = (string) ($entrada['password_actual'] ?? '');
$nueva = (string) ($entrada['password_nueva'] ?? '');
$confirmacion = (string) ($entrada['password_confirmar'] ?? '');

// === VALIDACIÓN ===
if ($actual === '' || $nueva === '' || $confirmacion === '') {
    Api::error('Todos los campos de contraseña son obligatorios');
}

if ($nueva !== $confirmacion) {
    Api::error('Las contraseñas nuevas no coinciden');
}

if (strlen($nueva) < 8 || strlen($nueva) > 200 || strlen($actual) > 200) {
    Api::error('La contraseña debe tener entre 8 y 200 caracteres');
}

// Límite de intentos para que no se pueda adivinar la contraseña actual con la sesión abierta.
$claveLimite = 'cambio_password_' . $idPaciente;
if (RateLimiter::tooManyAttempts($claveLimite, 5, 900)) {
    Api::error('Demasiados intentos. Espera unos minutos.', 429);
}

$pacientes = new Paciente();
$hashActual = $pacientes->obtenerHash($idPaciente);

if ($hashActual === null || !password_verify($actual, $hashActual)) {
    RateLimiter::registerAttempt($claveLimite);
    Api::error('La contraseña actual es incorrecta');
}
RateLimiter::resetAttempts($claveLimite);

$paciente = $pacientes->obtenerPorId($idPaciente);
if ($paciente === null) {
    Api::error('No fue posible procesar la solicitud', 404);
}

// === CAMBIO PENDIENTE + CORREO DE CONFIRMACIÓN ===
$token = (new TokenPassword())->crearCambioPendiente($idPaciente, password_hash($nueva, PASSWORD_DEFAULT));
$enlace = BASE_URL . 'public/confirmar_cambio_password.php?token=' . $token;

if (!EmailService::enviarConfirmacionCambioPassword((string) $paciente['email'], $enlace)) {
    error_log("No se pudo enviar el correo de confirmación de cambio de contraseña (paciente #{$idPaciente}).");
}

Bitacora::registrar('solicitar_cambio_contrasena', 'paciente', $idPaciente);

Api::exito('Revisa tu correo para confirmar el cambio de contraseña');
