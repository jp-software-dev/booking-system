<?php
session_start();
require_once '../config/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$db = Database::getInstance();

try {
    $stmt = $db->query("SELECT * FROM consultorios");
    $consultorios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($consultorios);
} catch (Exception $e) {
    error_log("Error en get_consultorios.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
?>