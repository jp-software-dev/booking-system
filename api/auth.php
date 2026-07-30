<?php
/**
 * ENDPOINT DE AUTENTICACIÓN CENTRALIZADA
 *
 * API que gestiona el inicio de sesión tanto para administradores como para pacientes.
 * Detecta el tipo de credenciales (usuario/email) y ejecuta el flujo de verificación
 * correspondiente.
 *
 * @requires session_start
 * @requires config/Database.php
 * @requires method POST
 * @response application/json
 */

session_start();
require_once '../config/Database.php';
require_once __DIR__ . '/../src/Helpers/RateLimiter.php';
require_once __DIR__ . '/../src/Helpers/Bitacora.php';

use Helpers\RateLimiter;
use Helpers\Bitacora;

header('Content-Type: application/json');

// VALIDADOR DE MÉTODO: Bloquea peticiones que no sean POST para evitar accesos directos.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// LECTOR DE DATOS: Captura y decodifica las credenciales enviadas desde el frontend.
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

// HONEYPOT: campo trampa oculto para humanos vía CSS. Solo un bot que autocompleta
// todos los campos del formulario lo llenaría. Se rechaza sin tocar el rate limiter
// real (no es un usuario legítimo equivocándose) y sin dar pistas del motivo.
if (!empty($input['sitio_web'])) {
    // REGISTRO EN BITÁCORA: Se detectó un bot llenando el campo honeypot.
    // Se registra el evento para monitoreo de actividad maliciosa.
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    Bitacora::registrar('honeypot_triggered', 'security', null, "Bot detectado en login desde IP: $ip_address");

    echo json_encode(['success' => false, 'message' => 'Credenciales incorrectas']);
    exit;
}

$rateLimitKey = 'login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
try {
    if (RateLimiter::tooManyAttempts($rateLimitKey, 5, 300)) {
        echo json_encode(['success' => false, 'message' => 'Demasiados intentos. Por seguridad, espera unos minutos antes de volver a intentar.']);
        exit;
    }
} catch (Exception $e) {
    error_log("Error en RateLimiter: " . $e->getMessage());
    // No bloqueamos el login por un fallo de infraestructura del rate limiter
}

$db = Database::getInstance();

try {
    $identificador = trim($input['usuario'] ?? $input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($identificador) || empty($password)) {
        // VALIDACIÓN DE CAMPOS: Rechaza la solicitud si faltan las credenciales clave.
        RateLimiter::registerAttempt($rateLimitKey);
        echo json_encode(['success' => false, 'message' => 'Los campos no pueden estar vacíos']);
        exit;
    }

    // VALIDACIÓN ESTRICTA DE LONGITUD: defensa en profundidad contra payloads anormalmente
    // largos (no reemplaza las sentencias preparadas, que ya evitan inyección SQL).
    if (strlen($identificador) > 254 || strlen($password) > 200) {
        RateLimiter::registerAttempt($rateLimitKey);
        echo json_encode(['success' => false, 'message' => 'Credenciales incorrectas']);
        exit;
    }

    // --- MITIGACIÓN DE ATAQUES DE TEMPORIZACIÓN ---
    // 1. BUSCAR USUARIO Y OBTENER HASH
    // Se intenta localizar al usuario (admin o paciente) y se obtiene su hash real.
    // Si no se encuentra, se usa un hash falso para asegurar que la función password_verify() siempre se ejecute.
    $user = null;
    $hash_a_verificar = '$2y$10$N9qo8uLOickgx2ZMRZoMye.aA.dFcF/i5jP3l4vX2b8HYu34K9Q5u'; // Hash pre-generado de una cadena aleatoria.

    $stmtAdmin = $db->prepare("SELECT id_usuario, usuario, nombre, password_hash, rol 
                               FROM usuarios WHERE LOWER(usuario) = LOWER(?)");
    $stmtAdmin->execute([$identificador]);
    $user = $stmtAdmin->fetch();

    if (!$user) {
        $stmtPac = $db->prepare("SELECT p.id_paciente, p.nombre, pc.password_hash
                                 FROM pacientes p
                                 INNER JOIN pacientes_credenciales pc ON p.id_paciente = pc.id_paciente
                                 WHERE p.email = ? AND p.cuenta_eliminada = 0");
        $stmtPac->execute([$identificador]);
        $user = $stmtPac->fetch();
        if ($user) {
            $user['role'] = 'paciente'; // Se añade el rol para unificar la estructura del objeto.
        }
    } elseif ($user) {
        $user['role'] = $user['rol']; // Unifica la clave 'rol' (columna de la BD) con 'role' (usada en el resto del sistema).
    }

    if ($user) {
        $hash_a_verificar = $user['password_hash'];
    }

    // 2. VERIFICAR CONTRASEÑA (SIEMPRE)
    // Esta función se ejecuta incondicionalmente para que el tiempo de respuesta sea similar
    // tanto si el usuario existe como si no, frustrando los ataques de temporización.
    if (password_verify($password, $hash_a_verificar) && $user) {
        // 3. AUTENTICACIÓN EXITOSA
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id_usuario'] ?? $user['id_paciente'];
        $_SESSION['user_name'] = $user['nombre'];
        $_SESSION['role'] = $user['role'];
        RateLimiter::resetAttempts($rateLimitKey);
        Bitacora::registrar('login_exitoso', 'sesion', (int)$_SESSION['user_id']);

        $redirect = $user['role'] === 'admin' ? 'admin.php' : 'agenda.php';
        echo json_encode(['success' => true, 'role' => $user['role'], 'redirect' => $redirect]);
    } else {
        // 4. AUTENTICACIÓN FALLIDA
        RateLimiter::registerAttempt($rateLimitKey);
        echo json_encode(['success' => false, 'message' => 'Credenciales incorrectas']);
    }

} catch (Exception $e) {
    // MANEJO DE ERRORES: Registra el error en el log y envía un mensaje seguro al cliente.
    error_log("Error en auth.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>