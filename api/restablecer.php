<?php
/**
 * ENDPOINT DE RESTABLECIMIENTO DE CONTRASEÑA
 *
 * Valida el token de seguridad, verifica su vigencia y que no haya sido usado,
 * y actualiza la contraseña del paciente en la base de datos.
 *
 * @requires config/Database.php
 * @requires method POST
 * @response application/json
 */

require_once '../config/Database.php';
header('Content-Type: application/json');

// VALIDADOR DE MÉTODO: Bloquea accesos directos que no sean por POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

// EXTRACCIÓN DE DATOS: Captura y decodifica el cuerpo de la petición JSON.
$input = json_decode(file_get_contents('php://input'), true);
$token = trim($input['token'] ?? '');
$password = $input['password'] ?? '';

// VALIDADOR DE SEGURIDAD: Comprueba que los campos no estén vacíos y que la contraseña cumpla la longitud mínima.
if (empty($token) || empty($password) || strlen($password) < 8) {
    echo json_encode(['status' => 'error', 'message' => 'Datos inválidos o contraseña demasiado corta']);
    exit;
}

$db = Database::getInstance();

// VERIFICACIÓN DE TOKEN: Consulta que el token exista, esté vigente y no se haya usado.
$stmt = $db->prepare("SELECT email FROM password_resets WHERE token = ? AND expira > NOW() AND usado = 0");
$stmt->execute([$token]);
$reset = $stmt->fetch();

// PROTECCIÓN CONTRA REUSO: Bloquea el intento si el token es inválido, viejo o ya se usó.
if (!$reset) {
    echo json_encode(['status' => 'error', 'message' => 'El enlace es inválido o ha expirado.']);
    exit;
}

$email = $reset['email'];

// ENCRIPTACIÓN: Transforma la nueva contraseña en un hash criptográfico irreversible.
$hash = password_hash($password, PASSWORD_DEFAULT);

// ACTUALIZACIÓN DE CREDENCIALES: Actualiza la contraseña en la tabla de credenciales.
$update = $db->prepare("UPDATE pacientes_credenciales pc 
                         JOIN pacientes p ON pc.id_paciente = p.id_paciente 
                         SET pc.password_hash = ? 
                         WHERE p.email = ?");
$update->execute([$hash, $email]);

// INVALIDACIÓN DE TOKEN: Marca el token como usado para que no pueda reutilizarse.
$usar = $db->prepare("UPDATE password_resets SET usado = 1 WHERE token = ?");
$usar->execute([$token]);

echo json_encode(['status' => 'success', 'message' => 'Contraseña actualizada correctamente. Redirigiendo al login...']);
?>