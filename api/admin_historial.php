<?php
/**
 * ENDPOINT DE HISTORIAL ADMINISTRATIVO
 *
 * Proporciona al panel de control del administrador un listado completo y formateado
 * de todas las citas médicas del sistema, incluyendo datos relacionales de pacientes
 * y doctores.
 *
 * @requires session_start
 * @requires config/Database.php
 * @response application/json
 */

session_start();
require_once '../config/Database.php';
header('Content-Type: application/json');

// VALIDACIÓN DE ACCESO: Verifica que exista una sesión activa y que el usuario tenga privilegios de administrador.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$db = Database::getInstance();

// EXTRACCIÓN DE DATOS RELACIONALES: Obtiene las citas cruzando la información de pacientes y doctores mediante un JOIN, ordenadas por fecha descendente.
try {
    $query = "SELECT c.id_cita, p.nombre AS paciente, d.nombre AS doctor, d.apellido_paterno AS doctor_ap,
                     c.fecha_cita, c.hora_inicio, c.motivo_consulta, c.estado_cita, c.created_at
              FROM citas c
              JOIN pacientes p ON c.id_paciente = p.id_paciente
              JOIN doctores d ON c.id_doctor = d.id_doctor
              ORDER BY c.fecha_cita DESC, c.hora_inicio DESC";
    
    $stmt = $db->query($query);
    $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => 'success', 'data' => $citas]);

// MANEJO DE EXCEPCIONES: Captura fallos en la base de datos, registra el error interno y devuelve un mensaje genérico al cliente.
} catch (Exception $e) {
    error_log("Error en admin_historial.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor al procesar la solicitud.']);
}
?>