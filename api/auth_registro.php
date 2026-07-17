<?php
/**
 * ENDPOINT DE REGISTRO DE PACIENTES
 * Reemplaza el campo "edad" por "fecha_nacimiento" y calcula la mayoría de edad
 * directamente desde la fecha, eliminando la validación errónea de edad numérica.
 */

session_start();
require_once '../config/Database.php';
require_once '../src/Helpers/ValidationHelper.php';
require_once '../src/Helpers/RateLimiter.php';
require_once '../src/Helpers/CsrfHelper.php';

use Helpers\ValidationHelper;
use Helpers\RateLimiter;
use Helpers\CsrfHelper;

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

// === RATE LIMITING ===
$rateLimitKey = 'register_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (RateLimiter::tooManyAttempts($rateLimitKey, 3, 3600)) {
    echo json_encode(['status' => 'error', 'message' => 'Límite de registros alcanzado. Por favor, intenta más tarde.']);
    exit;
}

// === CSRF VALIDATION ===
if (!CsrfHelper::validateToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Token de seguridad inválido. Recarga la página.']);
}

// === SANITIZACIÓN ===
$nombre            = trim($_POST['nombre'] ?? '');
$apellido_paterno  = trim($_POST['apellido_paterno'] ?? '');
$apellido_materno  = trim($_POST['apellido_materno'] ?? '');
$email             = trim($_POST['email'] ?? '');
$fecha_nacimiento  = trim($_POST['fecha_nacimiento'] ?? ''); // reemplaza "edad"
$genero            = trim($_POST['genero'] ?? '');
$curp              = strtoupper(trim($_POST['curp'] ?? ''));
$password          = $_POST['password'] ?? '';
$confirm_password  = $_POST['confirm_password'] ?? '';
$telefono          = trim($_POST['telefono'] ?? '');

// === VALIDACIÓN DE CAMPOS OBLIGATORIOS ===
$campos_vacios = [];
if (empty($nombre))           $campos_vacios[] = 'nombre';
if (empty($apellido_paterno)) $campos_vacios[] = 'apellido paterno';
if (empty($email))            $campos_vacios[] = 'email';
if (empty($fecha_nacimiento)) $campos_vacios[] = 'fecha de nacimiento';
if (empty($genero))           $campos_vacios[] = 'género';
if (empty($curp))             $campos_vacios[] = 'CURP';
if (empty($password))         $campos_vacios[] = 'contraseña';
if (empty($confirm_password)) $campos_vacios[] = 'confirmar contraseña';
if (empty($telefono))         $campos_vacios[] = 'teléfono';

if (!empty($campos_vacios)) {
    echo json_encode(['status' => 'error', 'message' => 'Todos los campos obligatorios deben estar llenos. Faltan: ' . implode(', ', $campos_vacios)]);
    exit;
}

// === VALIDACIÓN DE FECHA DE NACIMIENTO Y MAYORÍA DE EDAD ===
$fechaNac = DateTime::createFromFormat('Y-m-d', $fecha_nacimiento);
if (!$fechaNac || $fechaNac->format('Y-m-d') !== $fecha_nacimiento) {
    echo json_encode(['status' => 'error', 'message' => 'Fecha de nacimiento inválida']);
    exit;
}

$hoy    = new DateTime();
$edad   = $hoy->diff($fechaNac)->y;

if ($edad < 18) {
    echo json_encode(['status' => 'error', 'message' => 'Debes ser mayor de 18 años para registrarte']);
    exit;
}

// === VALIDACIÓN DE CURP ===
if (!ValidationHelper::validateCURP($curp)) {
    echo json_encode(['status' => 'error', 'message' => 'Formato de CURP inválido o dígito verificador incorrecto.']);
    exit;
}

// === VALIDACIÓN DE CONTRASEÑAS ===
if ($password !== $confirm_password || strlen($password) < 8) {
    echo json_encode(['status' => 'error', 'message' => 'Contraseñas no coinciden o son cortas']);
    exit;
}

// === VALIDACIÓN DE TELÉFONO ===
if (!preg_match('/^[0-9]{10}$/', $telefono)) {
    echo json_encode(['status' => 'error', 'message' => 'El teléfono debe contener exactamente 10 dígitos numéricos']);
    exit;
}

// === VALIDACIÓN DE EMAIL ===
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'El correo electrónico no es válido']);
    exit;
}

// === BASE DE DATOS ===
try {
    $db = Database::getInstance();
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión a la base de datos']);
    exit;
}

try {
    // Verificar CURP duplicada
    $checkCurp = $db->prepare("SELECT id_paciente FROM pacientes WHERE curp = ?");
    $checkCurp->execute([$curp]);
    if ($checkCurp->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Esta CURP ya está registrada']);
        exit;
    }

    // Verificar email duplicado
    $checkEmail = $db->prepare("SELECT id_paciente FROM pacientes WHERE email = ?");
    $checkEmail->execute([$email]);
    if ($checkEmail->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'El correo ya está registrado']);
        exit;
    }

    $db->beginTransaction();

    // Insertar paciente con fecha_nacimiento directa (no calculada desde edad)
    $stmt = $db->prepare("INSERT INTO pacientes 
        (curp, nombre, apellido_paterno, apellido_materno, fecha_nacimiento, genero, telefono, email) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$curp, $nombre, $apellido_paterno, $apellido_materno, $fecha_nacimiento, $genero, $telefono, $email]);
    $id_paciente = $db->lastInsertId();

    // Insertar credenciales
    $hash  = password_hash($password, PASSWORD_DEFAULT);
    $stmt2 = $db->prepare("INSERT INTO pacientes_credenciales (id_paciente, password_hash, cuenta_verificada) VALUES (?, ?, 1)");
    $stmt2->execute([$id_paciente, $hash]);

    $db->commit();

    // Iniciar sesión automáticamente
    $_SESSION['user_id']   = $id_paciente;
    $_SESSION['user_name'] = $nombre;
    $_SESSION['role']      = 'paciente';
    RateLimiter::registerAttempt($rateLimitKey); // Registra el intento exitoso para el rate limiting

    echo json_encode(['status' => 'success', 'message' => 'Registro exitoso', 'redirect' => 'agenda.php']);

} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log("PDO Error en auth_registro: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error de base de datos']);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log("Error en auth_registro: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor']);
}
?>