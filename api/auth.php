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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
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
    // FLUJO DE ADMINISTRADOR: Detecta si la petición contiene un "usuario" para el panel de control.
    if (isset($input['usuario']) && isset($input['password'])) {
        $usuario = trim($input['usuario']);
        $password = $input['password'];

        // CONSULTA ADMIN: Busca al usuario en la tabla de administradores, ignorando mayúsculas/minúsculas.
        $stmt = $db->prepare("SELECT id_usuario, usuario, nombre, password_hash, rol 
                               FROM usuarios WHERE LOWER(usuario) = LOWER(?)");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch();

        // VERIFICACIÓN ADMIN: Evalúa si el usuario existe y si la contraseña coincide con el hash.
        if ($user && password_verify($password, $user['password_hash'])) {
            // SEGURIDAD DE SESIÓN: Regenera el ID de sesión para prevenir ataques de fijación.
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id_usuario'];
            $_SESSION['user_name'] = $user['nombre'];
            $_SESSION['role'] = $user['rol'];
            
            echo json_encode(['success' => true, 'role' => $user['rol'], 'redirect' => 'admin.php']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Usuario o contraseña incorrectos']);
        }
    }
    
    // FLUJO DE PACIENTE: Detecta si la petición contiene un "email" para el portal público.
    elseif (isset($input['email']) && isset($input['password'])) {
        $email = trim($input['email']);
        $password = $input['password'];

        // CONSULTA PACIENTE: Busca el correo uniendo la tabla de datos personales con la de credenciales.
        $stmt = $db->prepare("SELECT p.id_paciente, p.nombre, pc.password_hash 
                               FROM pacientes p 
                               INNER JOIN pacientes_credenciales pc ON p.id_paciente = pc.id_paciente 
                               WHERE p.email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // VERIFICACIÓN PACIENTE: Evalúa la contraseña y, si es correcta, crea la sesión correspondiente.
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id_paciente'];
            $_SESSION['user_name'] = $user['nombre'];
            $_SESSION['role'] = 'paciente';
            
            echo json_encode(['success' => true, 'role' => 'paciente', 'redirect' => 'agenda.php']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Correo o contraseña incorrectos']);
        }
    } else {
        // VALIDACIÓN DE CAMPOS: Rechaza la solicitud si faltan las credenciales clave.
        echo json_encode(['success' => false, 'message' => 'Los campos no pueden estar vacíos']);
    }
} catch (Exception $e) {
    // MANEJO DE ERRORES: Registra el error en el log y envía un mensaje seguro al cliente.
    error_log("Error en auth.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>