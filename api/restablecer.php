<?php
// ENDPOINT RESTABLECER CONTRASEÑA: Archivo API que valida el token de seguridad y actualiza la contraseña del paciente en la base de datos.

// DEPENDENCIAS Y FORMATO: Carga la conexión a la base de datos y establece que la respuesta al frontend será estrictamente en formato JSON.
require_once '../config/Database.php';
header('Content-Type: application/json');

// VALIDADOR DE MÉTODO: Bloquea cualquier acceso directo por URL (GET) asegurando que los datos viajen únicamente por POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

// EXTRACCIÓN DE DATOS: Captura y decodifica el cuerpo de la petición JSON enviada desde el formulario del frontend.
$input = json_decode(file_get_contents('php://input'), true);

// ASIGNACIÓN DE VARIABLES: Extrae el token de seguridad y la nueva contraseña, eliminando espacios accidentales en el token.
$token = trim($input['token'] ?? '');
$password = $input['password'] ?? '';

// VALIDADOR DE SEGURIDAD: Comprueba que los campos no estén vacíos y exige un mínimo de 8 caracteres para la nueva clave.
if (empty($token) || empty($password) || strlen($password) < 8) {
    echo json_encode(['status' => 'error', 'message' => 'Datos inválidos o contraseña demasiado corta']);
    exit;
}

// CONEXIÓN DB: Llama a la instancia única activa (Singleton) para ejecutar consultas en la base de datos.
$db = Database::getInstance();

// VERIFICACIÓN DE TOKEN: Consulta la base de datos asegurando que el token exista, siga vigente (límite de 1 hora) y no se haya usado.
$stmt = $db->prepare("SELECT email FROM password_resets WHERE token = ? AND expira > NOW() AND usado = 0");
$stmt->execute([$token]);
$reset = $stmt->fetch();

// PROTECCIÓN CONTRA REUSO: Si el token es inválido, viejo o ya se usó, bloquea inmediatamente el intento de cambio de contraseña.
if (!$reset) {
    echo json_encode(['status' => 'error', 'message' => 'El enlace es inválido o ha expirado.']);
    exit;
}

// ASIGNACIÓN DE CORREO: Resguarda el correo validado que está vinculado directamente a ese token seguro.
$email = $reset['email'];

// ENCRIPTACIÓN: Transforma la nueva contraseña en un hash criptográfico irreversible utilizando el algoritmo estándar de PHP.
$hash = password_hash($password, PASSWORD_DEFAULT);

// ACTUALIZACIÓN DE CREDENCIALES: Actualiza la contraseña enlazando la tabla de credenciales con la de pacientes a través del correo validado.
$update = $db->prepare("UPDATE pacientes_credenciales pc 
                         JOIN pacientes p ON pc.id_paciente = p.id_paciente 
                         SET pc.password_hash = ? 
                         WHERE p.email = ?");
$update->execute([$hash, $email]);

// INVALIDACIÓN DE TOKEN: Marca el token de seguridad actual como 'usado' (1) para garantizar que este enlace jamás pueda reutilizarse.
$usar = $db->prepare("UPDATE password_resets SET usado = 1 WHERE token = ?");
$usar->execute([$token]);

// RESPUESTA EXITOSA: Devuelve un mensaje de confirmación al frontend para que notifique al usuario y lo redirija al inicio de sesión.
echo json_encode(['status' => 'success', 'message' => 'Contraseña actualizada correctamente. Redirigiendo al login...']);
?>