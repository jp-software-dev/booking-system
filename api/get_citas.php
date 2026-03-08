<?php
// ENDPOINT CALENDARIO: Archivo API que extrae y formatea las citas médicas para que la librería FullCalendar las pueda renderizar.

// DEPENDENCIAS: Carga el archivo de configuración para establecer la conexión con la base de datos.
require_once '../config/Database.php';

// FORMATO DE RESPUESTA: Define la cabecera HTTP para devolver los datos estrictamente en formato JSON.
header('Content-Type: application/json');

// CONEXIÓN DB: Llama a la instancia única de la base de datos utilizando el patrón de diseño Singleton.
$db = Database::getInstance();

try {
    // EXTRACCIÓN Y FORMATO: Construye la consulta SQL adaptando los nombres de las columnas (id, title, start) al estándar exacto que exige FullCalendar.
    $query = "SELECT c.id_cita as id,
                     CONCAT('Pac: ', p.nombre, ' / Dr. ', d.nombre) as title,
                     CONCAT(c.fecha_cita, 'T', c.hora_inicio) as start,
                     c.motivo_consulta as description,
                     c.estado_cita as status
              FROM citas c
              -- RELACIONES: Une las tablas de citas, pacientes y doctores (INNER JOIN) para mostrar los nombres reales en el evento.
              INNER JOIN pacientes p ON c.id_paciente = p.id_paciente
              INNER JOIN doctores d ON c.id_doctor = d.id_doctor";
              
    // EJECUCIÓN: Lanza la consulta directa a la base de datos para leer todo el registro de citas activas.
    $stmt = $db->query($query);
    
    // ESTRUCTURACIÓN: Convierte los resultados obtenidos de la base de datos en un arreglo asociativo de PHP.
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // RESPUESTA EXITOSA: Imprime el arreglo de eventos convertido en un objeto JSON listo para ser consumido por el frontend.
    echo json_encode($events);
} catch (Exception $e) {
    // MANEJO DE ERRORES: Captura excepciones, devuelve un código HTTP 500 (Error interno) y envía el detalle técnico del fallo en formato JSON.
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>