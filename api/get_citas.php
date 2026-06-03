<?php
/**
 * ENDPOINT DE RECUPERACIÓN DE CITAS PARA CALENDARIO
 *
 * Solo devuelve citas con fecha >= HOY para que los slots pasados
 * no aparezcan como "Ocupado" en el calendario.
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
    $query = "SELECT c.id_cita AS id,
                     CONCAT('Pac: ', p.nombre, ' / Dr. ', d.nombre) AS title,
                     CONCAT(c.fecha_cita, 'T', c.hora_inicio) AS start,
                     c.motivo_consulta AS description,
                     c.estado_cita AS status
              FROM citas c
              INNER JOIN pacientes p ON c.id_paciente = p.id_paciente
              INNER JOIN doctores d ON c.id_doctor = d.id_doctor
              WHERE c.estado_cita IN ('Pendiente', 'Confirmada')
                AND c.fecha_cita >= CURDATE()";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($events);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>