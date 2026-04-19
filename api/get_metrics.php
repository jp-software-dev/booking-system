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
    // CONTEO TOTAL: Obtiene el número absoluto de todas las citas registradas.
    $totalStmt = $db->query("SELECT COUNT(*) FROM citas");
    $total = $totalStmt->fetchColumn();

    // CONTEO PENDIENTES: Cuenta específicamente las citas que requieren atención.
    $pendientesStmt = $db->prepare("SELECT COUNT(*) FROM citas WHERE estado_cita = 'Pendiente'");
    $pendientesStmt->execute();
    $pendientes = $pendientesStmt->fetchColumn();

    // RESPUESTA EXITOSA: Convierte los resultados a enteros y los envía al frontend.
    echo json_encode(['total' => (int)$total, 'pendientes' => (int)$pendientes]);

} catch (Exception $e) {
    // MANEJO DE ERRORES: Registra el fallo y envía un código de error HTTP 500.
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>