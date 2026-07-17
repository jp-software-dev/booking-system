<?php
/**
 * ENDPOINT DE TOTALES AVANZADOS PARA EL PANEL ADMINISTRATIVO
 *
 * API que calcula y devuelve un desglose detallado de métricas por estado de cita
 * y por especialidad médica. Proporciona datos más granulares para reportes
 * avanzados y gráficas en el panel de control del administrador.
 *
 * @requires session_start
 * @requires config/Database.php
 * @requires admin role
 * @response application/json
 */

session_start();

require_once '../config/Database.php';

header('Content-Type: application/json');

// VALIDADOR DE SEGURIDAD: Bloquea el acceso si no hay una sesión activa de administrador.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$db = Database::getInstance();

try {
    // CONSULTA ÚNICA Y OPTIMIZADA: Calcula todas las métricas en una sola llamada a la base de datos.
    // Utiliza agregación condicional para métricas escalares y subconsultas con agregación JSON para datos agrupados.
    $stmt = $db->query("
        SELECT
            -- 1. Métricas escalares
            COUNT(*) AS total_general,
            SUM(CASE WHEN estado_cita = 'Cancelada' THEN 1 ELSE 0 END) AS total_canceladas,
            SUM(CASE WHEN fecha_cita = CURDATE() THEN 1 ELSE 0 END) AS citas_hoy,
            SUM(CASE WHEN YEARWEEK(fecha_cita, 1) = YEARWEEK(CURDATE(), 1) THEN 1 ELSE 0 END) AS citas_semana,
            SUM(CASE WHEN YEAR(fecha_cita) = YEAR(CURDATE()) AND MONTH(fecha_cita) = MONTH(CURDATE()) THEN 1 ELSE 0 END) AS citas_mes,
    
            -- 2. Métricas agrupadas, convertidas a JSON
            (SELECT JSON_ARRAYAGG(JSON_OBJECT('estado_cita', estado_cita, 'cantidad', COUNT(*)))
             FROM citas GROUP BY estado_cita) AS por_estado,
    
            (SELECT JSON_ARRAYAGG(JSON_OBJECT('nombre_especialidad', e.nombre_especialidad, 'cantidad', COUNT(c.id_cita)))
             FROM citas c
             JOIN doctores d ON c.id_doctor = d.id_doctor
             JOIN especialidades e ON d.id_especialidad = e.id_especialidad
             GROUP BY e.id_especialidad ORDER BY COUNT(c.id_cita) DESC) AS por_especialidad,
    
            (SELECT JSON_ARRAYAGG(JSON_OBJECT('nombre', d.nombre, 'apellido_paterno', d.apellido_paterno, 'cantidad', COUNT(c.id_cita)))
             FROM citas c
             JOIN doctores d ON c.id_doctor = d.id_doctor
             GROUP BY d.id_doctor ORDER BY COUNT(c.id_cita) DESC LIMIT 10) AS por_doctor
        FROM citas
    ");
    $metrics = $stmt->fetch(PDO::FETCH_ASSOC);

    $total = (int)($metrics['total_general'] ?? 0);
    $canceladas = (int)($metrics['total_canceladas'] ?? 0);
    $tasaCancelacion = $total > 0 ? round(($canceladas / $total) * 100, 2) : 0;

    // RESPUESTA EXITOSA: Empaqueta todas las métricas en un solo objeto JSON.
    echo json_encode([
        'status' => 'success',
        'data' => [
            'por_estado' => json_decode($metrics['por_estado'] ?? '[]', true),
            'por_especialidad' => json_decode($metrics['por_especialidad'] ?? '[]', true),
            'por_doctor' => json_decode($metrics['por_doctor'] ?? '[]', true),
            'citas_hoy' => (int)($metrics['citas_hoy'] ?? 0),
            'citas_semana' => (int)($metrics['citas_semana'] ?? 0),
            'citas_mes' => (int)($metrics['citas_mes'] ?? 0),
            'tasa_cancelacion' => $tasaCancelacion,
            'total_general' => (int)$total
        ]
    ]);

} catch (Exception $e) {
    // MANEJO DE ERRORES: Registra el fallo y envía un código de error HTTP 500.
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>