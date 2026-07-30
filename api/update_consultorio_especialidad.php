<?php
// Endpoint que permite al admin asignar/cambiar la especialidad predeterminada de un consultorio
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../src/Helpers/CsrfHelper.php';
require_once __DIR__ . '/../src/Helpers/Bitacora.php';

use Helpers\CsrfHelper;
use Helpers\Bitacora;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$token = null;
if (is_array($data) && array_key_exists('csrf_token', $data)) {
    $token = $data['csrf_token'];
} else {
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        $token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? null;
    }
}

if (!CsrfHelper::validateToken($token)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Token de seguridad inválido']);
    exit;
}

$id_consultorio = $data['id_consultorio'] ?? null;
$id_especialidad = !empty($data['id_especialidad']) ? (int)$data['id_especialidad'] : null;

if (!$id_consultorio) {
    echo json_encode(['status' => 'error', 'message' => 'Consultorio no especificado']);
    exit;
}

try {
    $db = Database::getInstance();

    if ($id_especialidad !== null) {
        $stmtCheck = $db->prepare("SELECT id_especialidad FROM especialidades WHERE id_especialidad = ?");
        $stmtCheck->execute([$id_especialidad]);
        if (!$stmtCheck->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Especialidad no válida']);
            exit;
        }
    }

    $stmt = $db->prepare("UPDATE consultorios SET id_especialidad = ? WHERE id_consultorio = ?");
    $stmt->execute([$id_especialidad, $id_consultorio]);

    Bitacora::registrar('actualizar_especialidad_consultorio', 'consultorio', (int)$id_consultorio, $id_especialidad ? "Especialidad #$id_especialidad" : 'Especialidad removida');

    echo json_encode(['status' => 'success', 'message' => 'Especialidad actualizada con éxito']);
} catch (Exception $e) {
    error_log("Error en update_consultorio_especialidad.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno al actualizar la especialidad']);
}
