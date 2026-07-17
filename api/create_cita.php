<?php
session_start();
require_once '../config/Database.php';
require_once '../src/Controllers/AppointmentController.php';
require_once '../src/Helpers/CsrfHelper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no iniciada']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// CSRF
$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? ($input['csrf_token'] ?? null);
if (!\Helpers\CsrfHelper::validateToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Token de seguridad inválido']);
    exit;
}

// Resolución de id_paciente cuando el admin agenda a nombre de un paciente
if ($_SESSION['role'] === 'admin' && !empty($input['email'])) {
    $db = Database::getInstance();
    $stmtPac = $db->prepare("SELECT id_paciente FROM pacientes WHERE email = ?");
    $stmtPac->execute([trim($input['email'])]);
    $paciente = $stmtPac->fetch();
    if (!$paciente) {
        echo json_encode(['status' => 'error', 'message' => 'No se encontró ningún paciente registrado con ese correo electrónico']);
        exit;
    }
    $input['id_paciente'] = (int)$paciente['id_paciente'];
}

$controller = new AppointmentController();
$resultado = $controller->create($input, (int)$_SESSION['user_id'], $_SESSION['role']);

if ($resultado['status'] === 'error') {
    http_response_code(400); // Bad Request para errores de validación
}

echo json_encode($resultado);