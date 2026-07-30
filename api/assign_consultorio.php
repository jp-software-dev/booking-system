<?php
// Endpoint transaccional que permite asignar un médico o liberar el consultorio dejándolo vacío
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

if (!CsrfHelper::validateToken($token)) {
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

    if (!empty($id_doctor)) {
        // doctores.estado es ENUM('Activo','Inactivo'); PDO lo devuelve como string,
        // no como entero -- comparar con (int) siempre daba 0 y rechazaba a todos los doctores.
        $stmtCheck = $db->prepare("SELECT estado, id_especialidad FROM doctores WHERE id_doctor = ?");
        $stmtCheck->execute([$id_doctor]);
        $doctor = $stmtCheck->fetch();
        if (!$doctor || $doctor['estado'] !== 'Activo') {
            $db->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'El médico seleccionado no está activo']);
            exit;
        }

        // Si el consultorio ya tiene una especialidad predeterminada, solo se permite
        // asignar doctores de esa misma especialidad (mismo criterio que filtra el
        // selector en admin_consultorios.php).
        $stmtCons = $db->prepare("SELECT id_especialidad FROM consultorios WHERE id_consultorio = ?");
        $stmtCons->execute([$id_consultorio]);
        $consultorio = $stmtCons->fetch();
        if ($consultorio && !empty($consultorio['id_especialidad']) && $consultorio['id_especialidad'] != $doctor['id_especialidad']) {
            $db->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'La especialidad del médico no coincide con la del consultorio']);
            exit;
        }
    }

    $stmt1 = $db->prepare("UPDATE doctores SET id_consultorio = NULL WHERE id_consultorio = ?");
    $stmt1->execute([$id_consultorio]);
    if (!empty($id_doctor)) {
        $stmt2 = $db->prepare("UPDATE doctores SET id_consultorio = ? WHERE id_doctor = ?");
        $stmt2->execute([$id_consultorio, $id_doctor]);
        Bitacora::registrar('reasignar_consultorio', 'consultorio', (int)$id_consultorio, "Doctor #$id_doctor asignado");
    } else {
        Bitacora::registrar('reasignar_consultorio', 'consultorio', (int)$id_consultorio, 'Consultorio liberado');
    }
    $db->commit();
    echo json_encode(['status' => 'success', 'message' => 'Operación realizada con éxito']);
} catch (Exception $e) {
    $db->rollBack();
    error_log("Error en assign_consultorio.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno al procesar la asignación']);
}