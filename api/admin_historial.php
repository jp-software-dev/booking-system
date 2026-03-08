<?php
// ENDPOINT HISTORIAL: Archivo API que devuelve el registro completo de todas las citas en formato JSON para el panel administrativo.
session_start();

// DEPENDENCIAS: Carga la clase centralizada para conectarse a la base de datos.
require_once '../config/Database.php';

// FORMATO DE RESPUESTA: Le indica al navegador o aplicación que la salida de este archivo será estructurada en formato JSON.
header('Content-Type: application/json');

// VALIDADOR DE SEGURIDAD: Bloquea inmediatamente el acceso si el usuario no ha iniciado sesión o si no tiene privilegios de 'admin'.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401); // Devuelve un código HTTP de "No Autorizado".
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// INSTANCIA DB: Obtiene la conexión activa a la base de datos utilizando el patrón Singleton.
$db = Database::getInstance();

try {
    // CONSULTA RELACIONAL: Extrae los detalles de las citas vinculando la información de las tablas 'pacientes' y 'doctores' mediante JOIN.
    $query = "SELECT c.id_cita, p.nombre AS paciente, d.nombre AS doctor, d.apellido_paterno AS doctor_ap,
                     c.fecha_cita, c.hora_inicio, c.motivo_consulta, c.estado_cita, c.created_at
              FROM citas c
              JOIN pacientes p ON c.id_paciente = p.id_paciente
              JOIN doctores d ON c.id_doctor = d.id_doctor
              ORDER BY c.fecha_cita DESC, c.hora_inicio DESC";
    
    // EJECUCIÓN DE CONSULTA: Lanza la petición SQL preparada directamente hacia el gestor de base de datos.
    $stmt = $db->query($query);
    
    // EXTRACCIÓN DE DATOS: Recupera todos los registros obtenidos y los formatea como un arreglo asociativo de PHP.
    $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // RESPUESTA EXITOSA: Convierte el arreglo de citas a un objeto JSON y lo envía al frontend para que pinte la tabla.
    echo json_encode(['status' => 'success', 'data' => $citas]);

} catch (Exception $e) {
    // CAPTURA DE ERRORES: Si algo falla en la consulta, devuelve un código HTTP 500 (Error del servidor) y el mensaje técnico.
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>