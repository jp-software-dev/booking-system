<?php
session_start();
require_once '../config/Database.php';
require_once __DIR__ . '/../src/Helpers/CsrfHelper.php';

use Helpers\CsrfHelper;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || !isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
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

$tipo = $input['tipo'] ?? '';
$nombre = trim($input['nombre'] ?? '');
$apellido = trim($input['apellido_paterno'] ?? '');
$id_consultorio = !empty($input['id_consultorio']) ? (int)$input['id_consultorio'] : null;

if (empty($nombre) || empty($apellido) || empty($tipo)) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan datos obligatorios']);
    exit;
}

$db = Database::getInstance();

try {
    if ($tipo === 'doctor') {
        $id_especialidad = !empty($input['id_especialidad']) ? (int)$input['id_especialidad'] : null;
        $stmt = $db->prepare("INSERT INTO doctores (nombre, apellido_paterno, id_especialidad, id_consultorio, estado) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$nombre, $apellido, $id_especialidad, $id_consultorio]);
    } else if ($tipo === 'enfermero') {
        $turno = $input['turno'] ?? 'Matutino';
        $stmt = $db->prepare("INSERT INTO enfermeros (nombre, apellido_paterno, turno, id_consultorio) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nombre, $apellido, $turno, $id_consultorio]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Tipo de personal inválido']);
        exit;
    }

    echo json_encode(['status' => 'success', 'message' => 'Personal agregado con éxito']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error al guardar en base de datos']);
}