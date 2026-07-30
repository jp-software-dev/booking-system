<?php
/**
 * ENDPOINT DE REGISTRO DE PACIENTES
 * Reemplaza el campo "edad" por "fecha_nacimiento" y calcula la mayoría de edad
 * directamente desde la fecha, eliminando la validación errónea de edad numérica.
 */

session_start();
require_once '../config/Database.php';
require_once '../src/Helpers/RateLimiter.php';
require_once '../src/Helpers/CsrfHelper.php';

use Helpers\RateLimiter;
use Helpers\CsrfHelper;

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

// === HONEYPOT ===
// Campo trampa oculto para humanos vía CSS. Solo un bot que autocompleta todos los
// campos del formulario lo llenaría. Se rechaza sin tocar el rate limiter real
// (no es un usuario legítimo equivocándose) y sin dar pistas del motivo.
if (!empty($_POST['sitio_web'])) {
    echo json_encode(['status' => 'error', 'message' => 'No fue posible procesar el registro.']);
    exit;
}

// === RATE LIMITING ===
$rateLimitKey = 'register_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
try {
    if (RateLimiter::tooManyAttempts($rateLimitKey, 3, 3600)) {
        echo json_encode(['status' => 'error', 'message' => 'Límite de registros alcanzado. Por favor, intenta más tarde.']);
        exit;
    }
} catch (Exception $e) {
    error_log("Error en RateLimiter: " . $e->getMessage());
    // No bloqueamos el registro por un fallo de infraestructura del rate limiter
}

// === CSRF VALIDATION ===
if (!CsrfHelper::validateToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Token de seguridad inválido. Recarga la página.']);
    exit;
}

// === SANITIZACIÓN ===
$nombre            = trim($_POST['nombre'] ?? '');
$apellido_paterno  = trim($_POST['apellido_paterno'] ?? '');
$apellido_materno  = trim($_POST['apellido_materno'] ?? '');
$email             = trim($_POST['email'] ?? '');
$fecha_nacimiento  = trim($_POST['fecha_nacimiento'] ?? ''); // reemplaza "edad"
$genero            = trim($_POST['genero'] ?? '');
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
if (empty($password))         $campos_vacios[] = 'contraseña';
if (empty($confirm_password)) $campos_vacios[] = 'confirmar contraseña';
if (empty($telefono))         $campos_vacios[] = 'teléfono';

if (!empty($campos_vacios)) {
    echo json_encode(['status' => 'error', 'message' => 'Todos los campos obligatorios deben estar llenos. Faltan: ' . implode(', ', $campos_vacios)]);
    exit;
}

// === VALIDACIÓN ESTRICTA DE LONGITUD ===
// Defensa en profundidad contra payloads anormalmente largos (DoS/abuso). No reemplaza
// las sentencias preparadas, que ya evitan inyección SQL.
if (strlen($nombre) > 100 || strlen($apellido_paterno) > 100 || strlen($apellido_materno) > 100
    || strlen($email) > 254 || strlen($password) > 200 || strlen($confirm_password) > 200) {
    echo json_encode(['status' => 'error', 'message' => 'Uno o más campos exceden la longitud permitida']);
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
    // --- MITIGACIÓN DE ATAQUE DE TEMPORIZACIÓN ---
    // Se ejecuta siempre el mismo conjunto de operaciones (verificación + intento de
    // escritura) sin importar si el correo ya existe, y la respuesta se decide y envía
    // solo al final. Así el tiempo de respuesta no delata, por temporización, qué
    // correos ya están registrados.

    // 1. VERIFICAR DUPLICADO (siempre se ejecuta, sin retornar de inmediato)
    $checkEmail = $db->prepare("SELECT id_paciente FROM pacientes WHERE email = ?");
    $checkEmail->execute([$email]);
    $emailDuplicado = (bool)$checkEmail->fetch();

    // 2. CALCULAR HASH (siempre): password_hash() con bcrypt es la operación que domina
    // el tiempo de respuesta, así que calcularla incondicionalmente es lo que más aporta
    // a igualar los tiempos entre ambos casos.
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // 3. INTENTO DE ESCRITURA (siempre se ejecuta una operación de costo equivalente)
    if (!$emailDuplicado) {
        $db->beginTransaction();

        // Insertar paciente con fecha_nacimiento directa (no calculada desde edad)
        $stmt = $db->prepare("INSERT INTO pacientes
            (nombre, apellido_paterno, apellido_materno, fecha_nacimiento, genero, telefono, email)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nombre, $apellido_paterno, $apellido_materno, $fecha_nacimiento, $genero, $telefono, $email]);
        $id_paciente = $db->lastInsertId();

        // Insertar credenciales
        $stmt2 = $db->prepare("INSERT INTO pacientes_credenciales (id_paciente, password_hash, cuenta_verificada) VALUES (?, ?, 1)");
        $stmt2->execute([$id_paciente, $hash]);

        $db->commit();
    } else {
        // Operación señuelo de costo equivalente (misma tabla que tocaría el INSERT
        // real) para que el camino "duplicado" no sea más rápido que el camino de éxito.
        $dummy = $db->prepare("SELECT id_paciente FROM pacientes_credenciales WHERE id_paciente = ?");
        $dummy->execute([-1]);
        $dummy->fetch();
    }

    // 4. RESPUESTA (solo aquí, al final, se decide y se envía)
    if ($emailDuplicado) {
        echo json_encode(['status' => 'error', 'message' => 'El correo ya está registrado']);
        exit;
    }

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