<?php
/**
 * ENDPOINT DE LISTA DE DOCTORES
 *
 * Extrae la lista de médicos activos para llenar dinámicamente los selectores
 * (dropdowns) en los formularios del frontend.
 *
 * @requires config/Database.php
 * @response application/json
 */

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
    // CONSULTA SQL: Selecciona ID y nombre de los doctores activos (estado = 1), ordenados alfabéticamente.
    $query = "SELECT id_doctor, nombre, apellido_paterno
              FROM doctores
              WHERE estado = 1 AND id_consultorio IS NOT NULL
              ORDER BY nombre ASC";

    $stmt = $db->query($query);

    // EXTRACCIÓN DE DATOS: Recupera todos los registros como un arreglo asociativo.
    $doctores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($doctores);
} catch (Exception $e) {
    error_log("Error en get_doctores.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
?>