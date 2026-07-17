<?php
/**
 * ENDPOINT DE MÉTRICAS PARA EL PANEL ADMINISTRATIVO
 *
 * API que calcula y devuelve el número total de citas registradas en el sistema
 * y la cantidad de citas que se encuentran en estado 'Pendiente'. Estos datos
 * se utilizan para poblar los indicadores visuales (contadores) en el panel
 * de control del administrador.
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
    // CONTEO EFICIENTE: Obtiene ambas métricas en una sola consulta.
    $stmt = $db->query("SELECT 
                            COUNT(*) AS total, 
                            SUM(CASE WHEN estado_cita = 'Pendiente' THEN 1 ELSE 0 END) AS pendientes 
                        FROM citas");
    $metrics = $stmt->fetch(PDO::FETCH_ASSOC);

    // RESPUESTA EXITOSA: Convierte los resultados a enteros y los envía al frontend.
    echo json_encode(['total' => (int)($metrics['total'] ?? 0), 'pendientes' => (int)($metrics['pendientes'] ?? 0)]);

} catch (Exception $e) {
    // MANEJO DE ERRORES: Registra el fallo y envía un código de error HTTP 500.
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>