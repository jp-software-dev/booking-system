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
    $query = "SELECT fecha_cita, hora_inicio FROM citas WHERE estado_cita IN ('Pendiente', 'Confirmada', 'En Curso')";
    $stmt = $db->query($query);
    $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];
    foreach ($citas as $c) {
        $events[] = [
            'start' => $c['fecha_cita'] . 'T' . substr($c['hora_inicio'], 0, 5)
        ];
    }

    echo json_encode($events);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}