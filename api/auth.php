<?php

/**
 * ENDPOINT: INICIO DE SESIÓN
 *
 * Autentica a administradores y médicos (por nombre de usuario, tabla `usuarios`)
 * y a pacientes (por correo, tablas `pacientes` + `pacientes_credenciales`).
 * Un médico solo entra si su cuenta está ligada a un médico activo.
 *
 * Método:    POST (JSON)
 * Acceso:    público, con token CSRF
 * Entrada:   { usuario, password, sitio_web (honeypot, debe venir vacío) }
 * Respuesta: { status, message, role, redirect }
 *
 * Seguridad aplicada:
 *   - Honeypot contra bots y límite de intentos por IP con bloqueo progresivo.
 *   - password_verify() se ejecuta siempre (con un hash ficticio si el usuario
 *     no existe) para que el tiempo de respuesta no revele qué cuentas existen.
 *   - Regeneración del id de sesión al autenticar (evita fijación de sesión).
 */

require_once __DIR__ . '/../config/config.php';

use App\Core\Database;
use App\Helpers\Bitacora;
use App\Helpers\RateLimiter;
use App\Helpers\ValidationHelper;
use App\Http\Api;
use App\Http\Sesion;
use App\Models\Paciente;

/** Hash bcrypt de una cadena aleatoria: se verifica cuando el usuario no existe. */
const HASH_FICTICIO = '$2y$10$N9qo8uLOickgx2ZMRZoMye.aA.dFcF/i5jP3l4vX2b8HYu34K9Q5u';

/** Mensaje único para cualquier fallo de credenciales (no revela cuál dato falló). */
const MENSAJE_CREDENCIALES = 'Credenciales incorrectas';

Api::iniciar('POST');
$entrada = Api::leerJson();
Api::exigirCsrf($entrada);

// === HONEYPOT ===
// Campo oculto para humanos: solo un bot que llena todos los campos lo completa.
if (!empty($entrada['sitio_web'])) {
    error_log('Honeypot activado en inicio de sesión desde la IP ' . RateLimiter::ipCliente());
    Api::error(MENSAJE_CREDENCIALES, 401);
}

// === LÍMITE DE INTENTOS ===
$claveLimite = RateLimiter::claveParaIp('login');
if (RateLimiter::tooManyAttempts($claveLimite, 5, 300)) {
    Api::error('Demasiados intentos. Por seguridad, espera unos minutos antes de volver a intentar.', 429);
}

// === VALIDACIÓN DE ENTRADA ===
$identificador = trim((string) ($entrada['usuario'] ?? $entrada['email'] ?? ''));
$password = (string) ($entrada['password'] ?? '');

if ($identificador === '' || $password === '') {
    RateLimiter::registerAttempt($claveLimite);
    Api::error('Escribe tu usuario o correo y tu contraseña');
}

// El identificador debe ser un correo válido o un nombre de usuario simple.
// (Antes una expresión regular rechazaba correos válidos con "+", como nombre+etiqueta@gmail.com.)
$esCorreo = ValidationHelper::validateEmail($identificador);
$esUsuario = preg_match('/^[A-Za-z0-9_.\-]{1,50}$/', $identificador) === 1;

if ((!$esCorreo && !$esUsuario) || strlen($password) > 200) {
    RateLimiter::registerAttempt($claveLimite);
    Api::error(MENSAJE_CREDENCIALES, 401);
}

// === BÚSQUEDA DEL USUARIO ===
$db = Database::getInstance();
$hashAVerificar = HASH_FICTICIO;
$usuario = null;          // Datos de sesión si la cuenta existe y su rol tiene acceso.
$rolSinAcceso = false;    // Cuenta de `usuarios` sin acceso (rol 'recepcion' o médico dado de baja).

// El nombre de usuario no distingue mayúsculas (LOWER funciona igual en MySQL y PostgreSQL).
$stmt = $db->prepare(
    "SELECT u.id_usuario, u.nombre, u.password_hash, u.rol, u.id_doctor, d.estado AS estado_doctor
     FROM usuarios u LEFT JOIN doctores d ON d.id_doctor = u.id_doctor
     WHERE LOWER(u.usuario) = LOWER(?)"
);
$stmt->execute([$identificador]);
$cuenta = $stmt->fetch();

if ($cuenta) {
    $hashAVerificar = (string) $cuenta['password_hash'];
    $base = ['id' => (int) $cuenta['id_usuario'], 'nombre' => (string) $cuenta['nombre'], 'tabla' => 'usuarios', 'id_doctor' => null];

    if ($cuenta['rol'] === Sesion::ROL_ADMIN) {
        $usuario = $base + ['rol' => Sesion::ROL_ADMIN];
    } elseif ($cuenta['rol'] === Sesion::ROL_DOCTOR && $cuenta['id_doctor'] !== null && $cuenta['estado_doctor'] === 'Activo') {
        $usuario = ['rol' => Sesion::ROL_DOCTOR, 'id_doctor' => (int) $cuenta['id_doctor']] + $base;
    } else {
        $rolSinAcceso = true;
    }
} elseif ($esCorreo) {
    $paciente = (new Paciente($db))->obtenerCredencialesPorEmail($identificador);
    if ($paciente !== null) {
        $hashAVerificar = (string) $paciente['password_hash'];
        $usuario = ['id' => (int) $paciente['id_paciente'], 'nombre' => (string) $paciente['nombre'], 'rol' => Sesion::ROL_PACIENTE, 'tabla' => 'pacientes', 'id_doctor' => null];
    }
}

// === VERIFICACIÓN DE CONTRASEÑA (SIEMPRE SE EJECUTA) ===
$passwordCorrecta = password_verify($password, $hashAVerificar);

if ($passwordCorrecta && $rolSinAcceso) {
    error_log("Inicio de sesión rechazado: el usuario '{$identificador}' tiene un rol sin acceso al sistema.");
    Api::error('Tu usuario no tiene un rol con acceso a este sistema. Contacta al administrador.', 403);
}

if (!$passwordCorrecta || $usuario === null) {
    RateLimiter::registerAttempt($claveLimite);
    Api::error(MENSAJE_CREDENCIALES, 401);
}

// === INICIO DE SESIÓN ===
// Si el algoritmo o costo de hash por defecto cambió, se actualiza el hash guardado.
if (password_needs_rehash($hashAVerificar, PASSWORD_DEFAULT)) {
    $nuevoHash = password_hash($password, PASSWORD_DEFAULT);
    if ($usuario['tabla'] === 'usuarios') {
        $db->prepare('UPDATE usuarios SET password_hash = ? WHERE id_usuario = ?')->execute([$nuevoHash, $usuario['id']]);
    } else {
        (new Paciente($db))->actualizarHash($usuario['id'], $nuevoHash);
    }
}

Sesion::autenticar($usuario['id'], $usuario['nombre'], $usuario['rol'], $usuario['id_doctor']);
RateLimiter::resetAttempts($claveLimite);
Bitacora::registrar('login_exitoso', 'sesion', $usuario['id']);

Api::exito('Bienvenido', [
    'role'     => $usuario['rol'],
    'redirect' => Sesion::paginaInicio(),
]);
