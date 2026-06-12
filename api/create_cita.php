<?php
session_start();
require_once '../config/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no iniciada']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$id_doctor = !empty($input['id_doctor']) ? (int)$input['id_doctor'] : 0;
$fecha_cita = $input['fecha_cita'] ?? '';
$hora_inicio = $input['hora_inicio'] ?? '';
$motivo = trim($input['motivo'] ?? $input['motivo_consulta'] ?? '');
$email = trim($input['email'] ?? '');

if (empty($id_doctor) || empty($fecha_cita) || empty($hora_inicio) || empty($motivo)) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan datos obligatorios para agendar la cita']);
    exit;
}

$db = Database::getInstance();

try {
    if ($_SESSION['role'] === 'admin') {
        if (empty($email)) {
            echo json_encode(['status' => 'error', 'message' => 'El correo del paciente es obligatorio para el administrador']);
            exit;
        }
        $stmtPac = $db->prepare("SELECT id_paciente FROM pacientes WHERE email = ?");
        $stmtPac->execute([$email]);
        $paciente = $stmtPac->fetch();
        if (!$paciente) {
            echo json_encode(['status' => 'error', 'message' => 'No se encontró ningún paciente registrado con ese correo electrónico']);
            exit;
        }
        $id_paciente = (int)$paciente['id_paciente'];
    } else {
        $id_paciente = (int)$_SESSION['user_id'];
    }

    $stmtDoc = $db->prepare("SELECT id_consultorio FROM doctores WHERE id_doctor = ?");
    $stmtDoc->execute([$id_doctor]);
    $doctorInfo = $stmtDoc->fetch();
    $id_consultorio = $doctorInfo ? $doctorInfo['id_consultorio'] : null;

    $stmtCheck = $db->prepare("SELECT COUNT(*) FROM citas WHERE id_doctor = ? AND fecha_cita = ? AND hora_inicio = ? AND estado_cita != 'Cancelada'");
    $stmtCheck->execute([$id_doctor, $fecha_cita, $hora_inicio]);
    if ($stmtCheck->fetchColumn() > 0) {
        echo json_encode(['status' => 'error', 'message' => 'El médico seleccionado ya tiene una cita programada en ese horario']);
        exit;
    }

    $stmtInsert = $db->prepare("INSERT INTO citas (id_paciente, id_doctor, id_consultorio, fecha_cita, hora_inicio, motivo_consulta, estado_cita) VALUES (?, ?, ?, ?, ?, ?, 'Pendiente')");
    $stmtInsert->execute([$id_paciente, $id_doctor, $id_consultorio, $fecha_cita, $hora_inicio, $motivo]);

    echo json_encode(['status' => 'success', 'message' => 'Cita agendada exitosamente']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error en el servidor al crear la cita: ' . $e->getMessage()]);
}