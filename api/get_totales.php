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
    // CONTEO POR ESTADO: Cuenta cuántas citas hay en cada estado del sistema.
    $estadosStmt = $db->query("
        SELECT estado_cita, COUNT(*) as cantidad 
        FROM citas 
        GROUP BY estado_cita
    ");
    $porEstado = $estadosStmt->fetchAll(PDO::FETCH_ASSOC);

    // CONTEO POR ESPECIALIDAD: Cuenta cuántas citas hay agrupadas por especialidad médica.
    $especialidadesStmt = $db->query("
        SELECT e.nombre_especialidad, COUNT(c.id_cita) as cantidad
        FROM citas c
        JOIN doctores d ON c.id_doctor = d.id_doctor
        JOIN especialidades e ON d.id_especialidad = e.id_especialidad
        GROUP BY e.id_especialidad
        ORDER BY cantidad DESC
    ");
    $porEspecialidad = $especialidadesStmt->fetchAll(PDO::FETCH_ASSOC);

    // CONTEO POR DOCTOR: Cuenta cuántas citas tiene asignadas cada médico.
    $doctoresStmt = $db->query("
        SELECT d.nombre, d.apellido_paterno, COUNT(c.id_cita) as cantidad
        FROM citas c
        JOIN doctores d ON c.id_doctor = d.id_doctor
        GROUP BY d.id_doctor
        ORDER BY cantidad DESC
        LIMIT 10
    ");
    $porDoctor = $doctoresStmt->fetchAll(PDO::FETCH_ASSOC);

    // MÉTRICAS TEMPORALES: Calcula citas del día actual, de la semana y del mes.
    $hoyStmt = $db->prepare("SELECT COUNT(*) FROM citas WHERE fecha_cita = CURDATE()");
    $hoyStmt->execute();
    $citasHoy = $hoyStmt->fetchColumn();

    $semanaStmt = $db->prepare("
        SELECT COUNT(*) FROM citas 
        WHERE YEARWEEK(fecha_cita) = YEARWEEK(CURDATE())
    ");
    $semanaStmt->execute();
    $citasSemana = $semanaStmt->fetchColumn();

    $mesStmt = $db->prepare("
        SELECT COUNT(*) FROM citas 
        WHERE MONTH(fecha_cita) = MONTH(CURDATE()) 
        AND YEAR(fecha_cita) = YEAR(CURDATE())
    ");
    $mesStmt->execute();
    $citasMes = $mesStmt->fetchColumn();

    // TASA DE CANCELACIÓN: Calcula el porcentaje de citas que han sido canceladas.
    $totalStmt = $db->query("SELECT COUNT(*) FROM citas");
    $total = $totalStmt->fetchColumn();

    $canceladasStmt = $db->prepare("SELECT COUNT(*) FROM citas WHERE estado_cita = 'Cancelada'");
    $canceladasStmt->execute();
    $canceladas = $canceladasStmt->fetchColumn();

    $tasaCancelacion = $total > 0 ? round(($canceladas / $total) * 100, 2) : 0;

    // RESPUESTA EXITOSA: Empaqueta todas las métricas en un solo objeto JSON.
    echo json_encode([
        'status' => 'success',
        'data' => [
            'por_estado' => $porEstado,
            'por_especialidad' => $porEspecialidad,
            'por_doctor' => $porDoctor,
            'citas_hoy' => (int)$citasHoy,
            'citas_semana' => (int)$citasSemana,
            'citas_mes' => (int)$citasMes,
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