<?php
// ENDPOINT DE AUTENTICACIÓN: Archivo API que gestiona de forma centralizada el inicio de sesión para administradores y pacientes.
session_start();
require_once '../config/Database.php';

// FORMATO DE RESPUESTA: Configura la cabecera para que el frontend reciba y procese correctamente los mensajes de éxito o error en JSON.
header('Content-Type: application/json');

// VALIDADOR DE MÉTODO: Bloquea cualquier petición que no provenga de un formulario (POST) para evitar accesos directos por URL.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// LECTOR DE DATOS: Captura, lee y decodifica las credenciales encriptadas enviadas desde el script de login del frontend.
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

// CONEXIÓN DB: Obtiene la instancia activa de la conexión a la base de datos.
$db = Database::getInstance();

try {
    // FLUJO DE ADMINISTRADOR: Detecta si la petición contiene un "usuario" (login del panel de control).
    if (isset($input['usuario']) && isset($input['password'])) {
        $usuario = trim($input['usuario']);
        $password = $input['password'];

        // CONSULTA ADMIN: Busca al usuario en la tabla validando en minúsculas (LOWER) para evitar errores si lo escriben con mayúsculas.
        $stmt = $db->prepare("SELECT id_usuario, usuario, nombre, password_hash, rol 
                               FROM usuarios WHERE LOWER(usuario) = LOWER(?)");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch();

        // VERIFICACIÓN ADMIN: Evalúa si el usuario existe y si la contraseña escrita coincide con el hash seguro guardado en la BD.
        if ($user && password_verify($password, $user['password_hash'])) {
            // SEGURIDAD DE SESIÓN: Regenera el ID de sesión para prevenir ataques de secuestro (session fixation).
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id_usuario'];
            $_SESSION['user_name'] = $user['nombre'];
            $_SESSION['role'] = $user['rol'];
            
            // RESPUESTA EXITOSA: Retorna el rol y la ruta a la que JavaScript debe redirigir al administrador.
            echo json_encode(['success' => true, 'role' => $user['rol'], 'redirect' => 'admin.php']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Usuario o contraseña incorrectos']);
        }
    }
    
    // FLUJO DE PACIENTE: Detecta si la petición contiene un "email" en lugar de un usuario (login de la vista pública).
    elseif (isset($input['email']) && isset($input['password'])) {
        $email = trim($input['email']);
        $password = $input['password'];

        // CONSULTA PACIENTE: Busca el correo uniendo la tabla de datos personales con la tabla de contraseñas de pacientes.
        $stmt = $db->prepare("SELECT p.id_paciente, p.nombre, pc.password_hash 
                               FROM pacientes p 
                               INNER JOIN pacientes_credenciales pc ON p.id_paciente = pc.id_paciente 
                               WHERE p.email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // VERIFICACIÓN PACIENTE: Evalúa la contraseña del paciente y, si es correcta, le crea su sesión correspondiente.
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id_paciente'];
            $_SESSION['user_name'] = $user['nombre'];
            $_SESSION['role'] = 'paciente';
            
            // RESPUESTA EXITOSA: Retorna éxito y la ruta para redirigir al paciente hacia su agenda médica.
            echo json_encode(['success' => true, 'role' => 'paciente', 'redirect' => 'agenda.php']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Correo o contraseña incorrectos']);
        }
    } else {
        // VALIDACIÓN DE CAMPOS: Si falta alguno de los datos clave (usuario/email o contraseña), rechaza el proceso.
        echo json_encode(['success' => false, 'message' => 'Los campos no pueden estar vacíos']); // Ajustado al Caso de Prueba 1
    }
} catch (Exception $e) {
    // MANEJO DE ERRORES: Si falla la base de datos, registra el error oculto en los logs y envía un mensaje seguro al cliente.
    error_log("Error en auth.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>