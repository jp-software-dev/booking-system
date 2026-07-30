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
$tipo = $input['tipo'] ?? '';
$nombre = trim($input['nombre'] ?? '');
$apellido = trim($input['apellido_paterno'] ?? '');
$id_consultorio = !empty($input['id_consultorio']) ? (int)$input['id_consultorio'] : null;

if ($id <= 0 || empty($nombre) || empty($apellido) || empty($tipo)) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan datos obligatorios']);
    exit;
}

if ($tipo !== 'doctor' && $tipo !== 'enfermero') {
    echo json_encode(['status' => 'error', 'message' => 'Tipo de personal inválido']);
    exit;
}

$db = Database::getInstance();

try {
    // Se confirma existencia con SELECT antes del UPDATE: si se guarda sin cambiar
    // ningún valor, rowCount() del UPDATE sería 0 y se reportaría un falso "no encontrado".
    if ($tipo === 'doctor') {
        $stmtCheck = $db->prepare("SELECT id_doctor FROM doctores WHERE id_doctor = ? AND estado = 1");
        $stmtCheck->execute([$id]);
    } else {
        $stmtCheck = $db->prepare("SELECT id_enfermero FROM enfermeros WHERE id_enfermero = ?");
        $stmtCheck->execute([$id]);
    }
    if (!$stmtCheck->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'No se encontró al miembro del personal']);
        exit;
    }

    if ($tipo === 'doctor') {
        // doctores.id_especialidad es NOT NULL: todo doctor debe tener una especialidad
        // (incluso "Medicina General" cuenta como una), a diferencia de id_consultorio
        // que sí admite quedar sin asignar.
        if (empty($input['id_especialidad'])) {
            echo json_encode(['status' => 'error', 'message' => 'La especialidad es obligatoria para un doctor']);
            exit;
        }
        $id_especialidad = (int)$input['id_especialidad'];
        $stmt = $db->prepare("UPDATE doctores SET nombre = ?, apellido_paterno = ?, id_especialidad = ?, id_consultorio = ? WHERE id_doctor = ?");
        $stmt->execute([$nombre, $apellido, $id_especialidad, $id_consultorio, $id]);
    } else {
        $turno = $input['turno'] ?? 'Matutino';
        $stmt = $db->prepare("UPDATE enfermeros SET nombre = ?, apellido_paterno = ?, turno = ?, id_consultorio = ? WHERE id_enfermero = ?");
        $stmt->execute([$nombre, $apellido, $turno, $id_consultorio, $id]);
    }

    Bitacora::registrar('modificar_personal', $tipo, $id, "$nombre $apellido");

    echo json_encode(['status' => 'success', 'message' => 'Datos actualizados con éxito']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error al guardar en base de datos']);
}
