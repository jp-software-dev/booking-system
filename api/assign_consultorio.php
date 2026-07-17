<?php
// Endpoint transaccional que permite asignar un médico o liberar el consultorio dejándolo vacío
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../src/Helpers/CsrfHelper.php';

session_start();

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

// Obtain CSRF token from JSON body or request headers (if available)
$token = null;
if (is_array($data) && array_key_exists('csrf_token', $data)) {
    $token = $data['csrf_token'];
} else {
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        $token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? null;
    }
}

// Validate CsrfHelper existence and token
if (!class_exists('CsrfHelper') || !CsrfHelper::validateToken($token)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Token de seguridad inválido']);
    exit;
}

$id_consultorio = $data['id_consultorio'] ?? null;
$id_doctor = $data['id_doctor'] ?? '';

if (!$id_consultorio) {
    echo json_encode(['status' => 'error', 'message' => 'Consultorio no especificado']);
    exit;
}

try {
    $db = Database::getInstance();
    $db->beginTransaction();
    $stmt1 = $db->prepare("UPDATE doctores SET id_consultorio = NULL WHERE id_consultorio = ?");
    $stmt1->execute([$id_consultorio]);
    if (!empty($id_doctor)) {
        $stmt2 = $db->prepare("UPDATE doctores SET id_consultorio = ? WHERE id_doctor = ?");
        $stmt2->execute([$id_consultorio, $id_doctor]);
    }
    $db->commit();
    echo json_encode(['status' => 'success', 'message' => 'Operación realizada con éxito']);
} catch (Exception $e) {
    $db->rollBack();
    error_log("Error en assign_consultorio.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno al procesar la asignación']);
}