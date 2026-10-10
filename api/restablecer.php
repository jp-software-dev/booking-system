<?php

/**
 * ENDPOINT: RESTABLECIMIENTO DE CONTRASEÑA
 *
 * Valida el token del enlace de recuperación y guarda la contraseña nueva.
 *
 * Método:    POST (JSON)
 * Acceso:    público (el token del correo es la autorización), con token CSRF
 * Entrada:   { token, password }
 * Respuesta: { status, message }
 */

require_once __DIR__ . '/../config/config.php';

use App\Core\Database;
use App\Helpers\Bitacora;
use App\Http\Api;
use App\Http\Sesion;
use App\Models\Paciente;
use App\Models\TokenPassword;

Api::iniciar('POST');
$entrada = Api::leerJson();
Api::exigirCsrf($entrada);

$token = trim((string) ($entrada['token'] ?? ''));
$password = (string) ($entrada['password'] ?? '');

if ($token === '' || strlen($password) < 8 || strlen($password) > 200) {
    Api::error('La contraseña debe tener entre 8 y 200 caracteres');
}

$db = Database::getInstance();
$tokens = new TokenPassword($db);
$pacientes = new Paciente($db);

$db->beginTransaction();

try {
    // FOR UPDATE: si el mismo enlace se usa dos veces a la vez, la segunda petición espera y lo encuentra ya usado.
    $email = $tokens->emailDeRestablecimiento($token, true);
    $idPaciente = $email !== null ? $pacientes->buscarIdActivoPorEmail($email) : null;

    if ($email === null || $idPaciente === null) {
        $db->rollBack();
        Api::error('El enlace es inválido o ya venció. Solicita uno nuevo.');
    }

    $pacientes->actualizarHash($idPaciente, password_hash($password, PASSWORD_DEFAULT));
    $tokens->invalidarRestablecimientos($email);

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $e;
}

Bitacora::registrarComo($idPaciente, Sesion::ROL_PACIENTE, 'restablecer_contrasena', 'paciente', $idPaciente);

Api::exito('Contraseña actualizada. Ya puedes iniciar sesión.');
