<?php

/**
 * ENDPOINT: REGISTRO DE PACIENTES
 *
 * Crea la cuenta de un paciente mayor de edad e inicia su sesión.
 *
 * Método:    POST (multipart/form-data, enviado con FormData desde registro.php)
 * Acceso:    público, con token CSRF
 * Entrada:   nombre, apellido_paterno, apellido_materno, email, telefono,
 *            fecha_nacimiento, genero, password, confirm_password,
 *            sitio_web (honeypot, debe venir vacío)
 * Respuesta: { status, message, redirect }
 */

require_once __DIR__ . '/../config/config.php';

use App\Core\Database;
use App\Helpers\Bitacora;
use App\Helpers\RateLimiter;
use App\Helpers\ValidationHelper;
use App\Http\Api;
use App\Http\Sesion;
use App\Models\Paciente;

/** Edad mínima para registrarse. */
const EDAD_MINIMA = 18;

Api::iniciar('POST');

// === HONEYPOT ===
if (!empty($_POST['sitio_web'])) {
    error_log('Honeypot activado en registro desde la IP ' . RateLimiter::ipCliente());
    Api::error('No fue posible procesar el registro.');
}

// === LÍMITE DE REGISTROS POR IP (3 por hora) ===
$claveLimite = RateLimiter::claveParaIp('registro');
if (RateLimiter::tooManyAttempts($claveLimite, 3, 3600)) {
    Api::error('Límite de registros alcanzado. Intenta más tarde.', 429);
}

Api::exigirCsrf($_POST);

// === LECTURA DE CAMPOS ===
$campo = static fn (string $nombre): string => trim((string) ($_POST[$nombre] ?? ''));

$nombre          = $campo('nombre');
$apellidoPaterno = $campo('apellido_paterno');
$apellidoMaterno = $campo('apellido_materno');
$email           = $campo('email');
$telefono        = $campo('telefono');
$fechaNacimiento = $campo('fecha_nacimiento');
$genero          = $campo('genero');
$password        = (string) ($_POST['password'] ?? '');
$confirmacion    = (string) ($_POST['confirm_password'] ?? '');

// === CAMPOS OBLIGATORIOS ===
$faltantes = array_keys(array_filter([
    'nombre'                => $nombre,
    'apellido paterno'      => $apellidoPaterno,
    'correo'                => $email,
    'teléfono'              => $telefono,
    'fecha de nacimiento'   => $fechaNacimiento,
    'género'                => $genero,
    'contraseña'            => $password,
    'confirmar contraseña'  => $confirmacion,
], static fn (string $valor): bool => $valor === ''));

if ($faltantes !== []) {
    Api::error('Faltan campos obligatorios: ' . implode(', ', $faltantes));
}

// === FORMATO Y LONGITUD ===
foreach ([$nombre, $apellidoPaterno, $apellidoMaterno] as $texto) {
    if (ValidationHelper::excedeLongitud($texto, Paciente::MAX_NOMBRE)) {
        Api::error('Nombre y apellidos pueden tener como máximo ' . Paciente::MAX_NOMBRE . ' caracteres');
    }
}

if (!ValidationHelper::validateEmail($email)) {
    Api::error('El correo electrónico no es válido');
}

if (!ValidationHelper::validatePhone($telefono)) {
    Api::error('El teléfono debe tener exactamente 10 dígitos');
}

if (!in_array($genero, Paciente::GENEROS, true)) {
    Api::error('Selecciona un género válido');
}

if ($password !== $confirmacion) {
    Api::error('Las contraseñas no coinciden');
}

if (strlen($password) < 8 || strlen($password) > 200) {
    Api::error('La contraseña debe tener entre 8 y 200 caracteres');
}

// === FECHA DE NACIMIENTO Y MAYORÍA DE EDAD ===
if (!ValidationHelper::validateDate($fechaNacimiento)) {
    Api::error('Fecha de nacimiento inválida');
}

$edad = (new DateTimeImmutable($fechaNacimiento))->diff(new DateTimeImmutable('today'));
if ($edad->invert === 1 || $edad->y > 120) {
    Api::error('Fecha de nacimiento inválida');
}
if ($edad->y < EDAD_MINIMA) {
    Api::error('Debes ser mayor de ' . EDAD_MINIMA . ' años para registrarte');
}

// === REGISTRO ===
$pacientes = new Paciente();

// El hash se calcula antes de revisar si el correo existe: password_hash() es la
// operación más lenta, y hacerla siempre iguala el tiempo de respuesta de ambos casos.
$hash = password_hash($password, PASSWORD_DEFAULT);

if ($pacientes->existeEmail($email)) {
    Api::error('El correo ya está registrado', 409);
}

try {
    $idPaciente = $pacientes->registrar([
        'nombre'           => $nombre,
        'apellido_paterno' => $apellidoPaterno,
        'apellido_materno' => $apellidoMaterno !== '' ? $apellidoMaterno : null,
        'fecha_nacimiento' => $fechaNacimiento,
        'genero'           => $genero,
        'telefono'         => $telefono,
        'email'            => $email,
    ], $hash);
} catch (PDOException $e) {
    // Dos registros simultáneos con el mismo correo: el índice único rechaza el segundo.
    if (Database::esLlaveDuplicada($e)) {
        Api::error('El correo ya está registrado', 409);
    }
    throw $e;
}

// Cuenta para el límite de registros por IP.
RateLimiter::registerAttempt($claveLimite);

Sesion::autenticar($idPaciente, $nombre, Sesion::ROL_PACIENTE);
Bitacora::registrar('registro_paciente', 'paciente', $idPaciente);

Api::exito('Registro exitoso', ['redirect' => 'agenda.php']);
