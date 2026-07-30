<?php
/**
 * ENDPOINT DE SOLICITUD DE CAMBIO DE CONTRASEÑA (PACIENTE, AUTENTICADO)
 *
 * A diferencia de update_perfil.php, este endpoint NO aplica el cambio de contraseña
 * de inmediato: valida la contraseña actual y la nueva, guarda el hash de la nueva
 * contraseña junto con un token de un solo uso en cambios_password_pendientes, y envía
 * un correo de confirmación. El cambio real solo se aplica cuando el usuario confirma
 * el enlace en confirmar_cambio_password.php.
 *
 * @requires session_start
 * @requires config/Database.php
 * @requires method POST
 * @requires paciente role
 * @response application/json
 */

session_start();

require_once '../config/Database.php';
require_once __DIR__ . '/../src/Helpers/CsrfHelper.php';
require_once __DIR__ . '/../src/Helpers/Bitacora.php';
require_once __DIR__ . '/../src/Helpers/Mailer.php';

use Helpers\CsrfHelper;
use Helpers\Bitacora;
use Helpers\Mailer;

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'paciente') {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['status' => 'error', 'message' => 'Datos inválidos']);
    exit;
}

$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? ($input['csrf_token'] ?? null);
if (!CsrfHelper::validateToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Token de seguridad inválido']);
    exit;
}

$id_paciente        = $_SESSION['user_id'];
$password_actual    = $input['password_actual'] ?? '';
$password_nueva     = $input['password_nueva'] ?? '';
$password_confirmar = $input['password_confirmar'] ?? '';

if (empty($password_actual) || empty($password_nueva) || empty($password_confirmar)) {
    echo json_encode(['status' => 'error', 'message' => 'Todos los campos de contraseña son obligatorios']);
    exit;
}

if (strlen($password_actual) > 200 || strlen($password_nueva) > 200) {
    echo json_encode(['status' => 'error', 'message' => 'Uno o más campos exceden la longitud permitida']);
    exit;
}

if ($password_nueva !== $password_confirmar || strlen($password_nueva) < 8) {
    echo json_encode(['status' => 'error', 'message' => 'Las nuevas contraseñas no coinciden o son demasiado cortas']);
    exit;
}

$db = Database::getInstance();

try {
    $stmtCred = $db->prepare("SELECT password_hash FROM pacientes_credenciales WHERE id_paciente = ?");
    $stmtCred->execute([$id_paciente]);
    $cred = $stmtCred->fetch();

    if (!$cred || !password_verify($password_actual, $cred['password_hash'])) {
        echo json_encode(['status' => 'error', 'message' => 'La contraseña actual es incorrecta']);
        exit;
    }

    $stmtPac = $db->prepare("SELECT email FROM pacientes WHERE id_paciente = ?");
    $stmtPac->execute([$id_paciente]);
    $paciente = $stmtPac->fetch();

    if (!$paciente || empty($paciente['email'])) {
        echo json_encode(['status' => 'error', 'message' => 'No fue posible procesar la solicitud']);
        exit;
    }

    $nuevoHash = password_hash($password_nueva, PASSWORD_DEFAULT);
    $token     = bin2hex(random_bytes(32));
    $expira    = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // El cambio de contraseña anterior aún no confirmado (si existe) queda huérfano de
    // forma inofensiva: expira solo y confirmar_cambio_password.php exige usado = 0 y
    // vigencia, así que solo el token más reciente que el usuario confirme surtirá efecto.
    $insert = $db->prepare("INSERT INTO cambios_password_pendientes (id_paciente, nuevo_hash, token, expira) VALUES (?, ?, ?, ?)");
    $insert->execute([$id_paciente, $nuevoHash, $token, $expira]);

    $enlace  = BASE_URL . "public/confirmar_cambio_password.php?token=" . $token;
    $asunto  = "Confirma tu cambio de contraseña - MediAgenda";
    $mensaje = "Hola,\n\nSolicitaste cambiar tu contraseña en MediAgenda. Si fuiste tú, confirma aquí:\n\n$enlace\n\nEste enlace expirará en 1 hora.\n\nSi no reconoces esta solicitud, ignora este correo: tu contraseña actual seguirá funcionando y no se aplicará ningún cambio.";

    $resultadoEnvio = Mailer::enviarCorreo($paciente['email'], $asunto, $mensaje);
    if ($resultadoEnvio !== true) {
        error_log("Error al enviar el correo de confirmación de cambio de contraseña para id_paciente: " . $id_paciente);
    }

    Bitacora::registrar('solicitar_cambio_contrasena', 'paciente', (int)$id_paciente);

    echo json_encode(['status' => 'success', 'message' => 'Revisa tu correo para confirmar el cambio de contraseña']);

} catch (PDOException $e) {
    error_log("PDO Error en solicitar_cambio_password.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error de base de datos']);
} catch (Exception $e) {
    error_log("Error en solicitar_cambio_password.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor']);
}
