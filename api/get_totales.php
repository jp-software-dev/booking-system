<?php
// ENDPOINT MÉTRICAS: Archivo API que calcula y devuelve la cantidad de citas totales y pendientes para los contadores del panel de administrador.
session_start();

// DEPENDENCIAS: Carga la configuración centralizada para establecer la conexión con la base de datos.
require_once '../config/Database.php';

// FORMATO DE RESPUESTA: Define la cabecera HTTP para que el frontend reciba e interprete los datos numéricos como un objeto JSON.
header('Content-Type: application/json');

// VALIDADOR DE SEGURIDAD: Bloquea inmediatamente el acceso si el usuario no tiene una sesión activa o carece de privilegios de administrador.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// CONEXIÓN DB: Obtiene la instancia única de la base de datos utilizando el patrón de diseño Singleton.
$db = Database::getInstance();

try {
    // CONTEO TOTAL: Ejecuta una consulta SQL rápida para obtener el número absoluto de todas las citas registradas en el sistema.
    $totalStmt = $db->query("SELECT COUNT(*) FROM citas");
    $total = $totalStmt->fetchColumn();

    // CONTEO PENDIENTES: Prepara y ejecuta una consulta específica para contar únicamente las citas que requieren atención (estado 'Pendiente').
    $pendientesStmt = $db->prepare("SELECT COUNT(*) FROM citas WHERE estado_cita = 'Pendiente'");
    $pendientesStmt->execute();
    $pendientes = $pendientesStmt->fetchColumn();

    // RESPUESTA EXITOSA: Empaqueta los resultados convirtiéndolos explícitamente a números enteros (int) en formato JSON para el frontend.
    echo json_encode(['total' => (int)$total, 'pendientes' => (int)$pendientes]);
} catch (Exception $e) {
    // MANEJO DE ERRORES: Captura cualquier fallo en las consultas, devuelve un código HTTP 500 y envía el detalle técnico del error.
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>