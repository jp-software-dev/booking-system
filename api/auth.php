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

header('Content-Type: application/json');

// VALIDADOR DE MÉTODO: Bloquea peticiones que no sean POST para evitar accesos directos.
if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_login_attempt'] = time();
}

if ($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['last_login_attempt']) < 300) {
    echo json_encode(['success' => false, 'message' => 'Demasiados intentos. Por seguridad, espera 5 minutos.']);
    exit;
}

// LECTOR DE DATOS: Captura y decodifica las credenciales enviadas desde el frontend.
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

$db = Database::getInstance();

try {
    $identificador = trim($input['usuario'] ?? $input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($identificador) || empty($password)) {
        // VALIDACIÓN DE CAMPOS: Rechaza la solicitud si faltan las credenciales clave.
        $_SESSION['login_attempts']++;
        $_SESSION['last_login_attempt'] = time();
        echo json_encode(['success' => false, 'message' => 'Los campos no pueden estar vacíos']);
        exit;
    }

    // FLUJO DE ADMINISTRADOR: Detecta si la petición contiene un "usuario" para el panel de control.
    $stmtAdmin = $db->prepare("SELECT id_usuario, usuario, nombre, password_hash, rol 
                               FROM usuarios WHERE LOWER(usuario) = LOWER(?)");
    $stmtAdmin->execute([$identificador]);
    $userAdmin = $stmtAdmin->fetch();

    // VERIFICACIÓN ADMIN: Evalúa si el usuario existe y si la contraseña coincide con el hash.
    if ($userAdmin && password_verify($password, $userAdmin['password_hash'])) {
        // SEGURIDAD DE SESIÓN: Regenera el ID de sesión para prevenir ataques de fijación.
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userAdmin['id_usuario'];
        $_SESSION['user_name'] = $userAdmin['nombre'];
        $_SESSION['role'] = $userAdmin['rol'];
        $_SESSION['login_attempts'] = 0;
        
        echo json_encode(['success' => true, 'role' => $userAdmin['rol'], 'redirect' => 'admin.php']);
        exit;
    }

    // FLUJO DE PACIENTE: Detecta si la petición contiene un "email" para el portal público.
    // CONSULTA PACIENTE: Busca el correo uniendo la tabla de datos personales con la de credenciales.
    $stmtPac = $db->prepare("SELECT p.id_paciente, p.nombre, pc.password_hash 
                             FROM pacientes p 
                             INNER JOIN pacientes_credenciales pc ON p.id_paciente = pc.id_paciente 
                             WHERE p.email = ?");
    $stmtPac->execute([$identificador]);
    $userPac = $stmtPac->fetch();

    // VERIFICACIÓN PACIENTE: Evalúa la contraseña y, si es correcta, crea la sesión correspondiente.
    if ($userPac && password_verify($password, $userPac['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userPac['id_paciente'];
        $_SESSION['user_name'] = $userPac['nombre'];
        $_SESSION['role'] = 'paciente';
        $_SESSION['login_attempts'] = 0;
        
        echo json_encode(['success' => true, 'role' => 'paciente', 'redirect' => 'agenda.php']);
        exit;
    }

    $_SESSION['login_attempts']++;
    $_SESSION['last_login_attempt'] = time();
    echo json_encode(['success' => false, 'message' => 'Credenciales incorrectas']);

} catch (Exception $e) {
    // MANEJO DE ERRORES: Registra el error en el log y envía un mensaje seguro al cliente.
    error_log("Error en auth.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>