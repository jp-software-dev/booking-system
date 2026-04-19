<?php
/**
 * ENDPOINT DE RECUPERACIÓN DE CITAS PARA CALENDARIO
 *
 * Proporciona los datos de las citas en un formato específico para ser consumido
 * por un componente de calendario (FullCalendar). Incluye citas con estado
 * 'Pendiente' o 'Confirmada'.
 *
 * @requires session_start
 * @requires config/Database.php
 * @response application/json
 */

session_start();
require_once '../config/Database.php';

header('Content-Type: application/json');

$db = Database::getInstance();

try {
    $query = "SELECT c.id_cita as id,
                     CONCAT('Pac: ', p.nombre, ' / Dr. ', d.nombre) as title,
                     CONCAT(c.fecha_cita, 'T', c.hora_inicio) as start,
                     c.motivo_consulta as description,
                     c.estado_cita as status
              FROM citas c
              INNER JOIN pacientes p ON c.id_paciente = p.id_paciente
              INNER JOIN doctores d ON c.id_doctor = d.id_doctor
              WHERE c.estado_cita IN ('Pendiente', 'Confirmada')";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($events);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>