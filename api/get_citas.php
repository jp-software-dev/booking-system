<?php
session_start();
require_once '../config/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$db = Database::getInstance();

try {
    // MINIMIZACIÓN DE DATOS: solo se expone lo indispensable para marcar un horario como
    // ocupado en la agenda (id_doctor, fecha, hora). Nunca el paciente, CURP o motivo de
    // consulta de otro paciente.
    $query = "SELECT id_doctor, fecha_cita, hora_inicio FROM citas WHERE estado_cita IN ('Pendiente', 'Confirmada', 'En Curso')";
    $stmt = $db->query($query);
    $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];
    foreach ($citas as $c) {
        $events[] = [
            'id_doctor' => (int)$c['id_doctor'],
            'fecha_cita' => $c['fecha_cita'],
            'hora_inicio' => substr($c['hora_inicio'], 0, 5),
            'ocupado' => true,
            'start' => $c['fecha_cita'] . 'T' . substr($c['hora_inicio'], 0, 5)
        ];
    }

    echo json_encode($events);
} catch (Exception $e) {
    error_log("Error en get_citas.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor']);
}