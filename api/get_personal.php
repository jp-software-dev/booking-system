<?php
session_start();
require_once '../config/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$db = Database::getInstance();

try {
    $doctores = $db->query("SELECT id_doctor as id, nombre, apellido_paterno, 'Doctor' as puesto, especialidad, id_consultorio FROM doctores")->fetchAll(PDO::FETCH_ASSOC);
    $enfermeros = $db->query("SELECT id_enfermero as id, nombre, apellido_paterno, 'Enfermero' as puesto, turno, id_consultorio FROM enfermeros")->fetchAll(PDO::FETCH_ASSOC);
    
    $personal = array_merge($doctores, $enfermeros);
    echo json_encode(['status' => 'success', 'data' => $personal]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener datos del personal: ' . $e->getMessage()]);
}
?>