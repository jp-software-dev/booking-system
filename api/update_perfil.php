<?php

/**
 * ENDPOINT: ACTUALIZACIÓN DEL PERFIL (PACIENTE)
 *
 * Permite al paciente editar sus datos personales y su preferencia de
 * notificaciones. El id del paciente se toma siempre de la sesión.
 *
 * Método:    POST (JSON)
 * Acceso:    paciente autenticado, con token CSRF
 * Entrada:   Datos personales: { nombre, apellido_paterno, apellido_materno, telefono, email, password_actual }
 *            y/o preferencia:   { recibir_notificaciones }
 * Respuesta: { status, message }
 *
 * Cambiar el correo exige la contraseña actual: el correo sirve para recuperar
 * la cuenta, así que alguien con la sesión abierta no debe poder cambiarlo solo.
 * La contraseña no se cambia aquí (ver solicitar_cambio_password.php).
 */

require_once __DIR__ . '/../config/config.php';

use App\Core\Database;
use App\Demo\ModoDemo;
use App\Helpers\Bitacora;
use App\Helpers\ValidationHelper;
use App\Http\Api;
use App\Http\Sesion;
use App\Models\Paciente;

Api::iniciar('POST');
$idPaciente = Api::exigirSesion(Sesion::ROL_PACIENTE);
$entrada = Api::leerJson();
Api::exigirCsrf($entrada);

$actualizaPerfil = array_key_exists('nombre', $entrada);
$actualizaNotificaciones = array_key_exists('recibir_notificaciones', $entrada);

if (!$actualizaPerfil && !$actualizaNotificaciones) {
    Api::error('No hay cambios que aplicar');
}

$pacientes = new Paciente();
$actual = $pacientes->obtenerPorId($idPaciente);
if ($actual === null) {
    Api::error('Cuenta no encontrada', 404);
}

// En modo demo los datos de la cuenta de paciente de prueba no se pueden cambiar
// (la preferencia de notificaciones sí, porque no afecta a otros visitantes).
if ($actualizaPerfil && ModoDemo::esPacienteDemo($idPaciente)) {
    Api::error(ModoDemo::MENSAJE_BLOQUEO, 403);
}

// === DATOS PERSONALES ===
if ($actualizaPerfil) {
    $campo = static fn (string $nombre): string => trim((string) ($entrada[$nombre] ?? ''));

    $nombre          = $campo('nombre');
    $apellidoPaterno = $campo('apellido_paterno');
    $apellidoMaterno = $campo('apellido_materno');
    $telefono        = $campo('telefono');
    $email           = $campo('email');

    if ($nombre === '' || $apellidoPaterno === '' || $telefono === '' || $email === '') {
        Api::error('Nombre, apellido paterno, teléfono y correo son obligatorios');
    }

    foreach ([$nombre, $apellidoPaterno, $apellidoMaterno] as $texto) {
        if (ValidationHelper::excedeLongitud($texto, Paciente::MAX_NOMBRE)) {
            Api::error('Nombre y apellidos pueden tener como máximo ' . Paciente::MAX_NOMBRE . ' caracteres');
        }
    }

    if (!ValidationHelper::validatePhone($telefono)) {
        Api::error('El teléfono debe tener exactamente 10 dígitos');
    }

    if (!ValidationHelper::validateEmail($email)) {
        Api::error('El correo electrónico no es válido');
    }

    $cambiaEmail = strcasecmp($email, (string) $actual['email']) !== 0;

    if ($cambiaEmail) {
        $hash = $pacientes->obtenerHash($idPaciente);
        $password = (string) ($entrada['password_actual'] ?? '');

        if ($password === '' || $hash === null || !password_verify($password, $hash)) {
            Api::error('Para cambiar tu correo escribe tu contraseña actual correctamente');
        }

        if ($pacientes->existeEmail($email, $idPaciente)) {
            Api::error('Ese correo ya está en uso por otra cuenta', 409);
        }
    }

    try {
        $pacientes->actualizarPerfil($idPaciente, $nombre, $apellidoPaterno, $apellidoMaterno !== '' ? $apellidoMaterno : null, $telefono, $email);
    } catch (PDOException $e) {
        if (Database::esLlaveDuplicada($e)) {
            Api::error('Ese correo ya está en uso por otra cuenta', 409);
        }
        throw $e;
    }

    Sesion::actualizarNombre($nombre);
}

// === PREFERENCIA DE NOTIFICACIONES ===
if ($actualizaNotificaciones) {
    $pacientes->actualizarNotificaciones($idPaciente, !empty($entrada['recibir_notificaciones']));
}

$detalle = implode(', ', array_filter([
    $actualizaPerfil ? 'datos personales' : null,
    $actualizaNotificaciones ? 'preferencias de notificación' : null,
]));
Bitacora::registrar('actualizar_perfil_paciente', 'paciente', $idPaciente, "Campos actualizados: {$detalle}");

Api::exito('Datos actualizados correctamente');
