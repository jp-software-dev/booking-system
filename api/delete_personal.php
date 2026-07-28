<?php
session_start();
require_once '../config/Database.php';
require_once __DIR__ . '/../src/Helpers/CsrfHelper.php';
require_once __DIR__ . '/../src/Helpers/Bitacora.php';

use Helpers\CsrfHelper;
use Helpers\Bitacora;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? ($input['csrf_token'] ?? null);
if (!CsrfHelper::validateToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Token de seguridad inválido']);
    exit;
}

$id = (int)($input['id'] ?? 0);
$tipo = $input['tipo'] ?? ''; // 'Doctor' o 'Enfermero'

if ($id <= 0 || empty($tipo)) {
    echo json_encode(['status' => 'error', 'message' => 'Datos inválidos']);
    exit;
}

$db = Database::getInstance();

try {
    if (strtolower($tipo) === 'doctor') {
        // Borrado lógico para el doctor (para no romper citas del historial) y lo quitamos del consultorio actual
        $stmt = $db->prepare("UPDATE doctores SET estado = 0, id_consultorio = NULL WHERE id_doctor = ?");
        $stmt->execute([$id]);
    } else {
        // Borrado físico para el enfermero
        $stmt = $db->prepare("DELETE FROM enfermeros WHERE id_enfermero = ?");
        $stmt->execute([$id]);
    }

    Bitacora::registrar('baja_personal', strtolower($tipo), $id);

    echo json_encode(['status' => 'success', 'message' => 'Baja procesada exitosamente']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'No se puede procesar la baja']);
}