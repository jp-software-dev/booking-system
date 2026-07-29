<?php
/**
 * ENDPOINT DE MÉTRICAS DEL DASHBOARD ADMINISTRATIVO
 *
 * Devuelve KPIs y series agregadas (por estado, especialidad, top doctores,
 * tendencia mensual) para el dashboard interactivo. Refactorizado a partir de
 * la consulta monolítica anterior — ver src/Models/Metrica.php para el detalle
 * de por qué se abandonó JSON_ARRAYAGG.
 *
 * @requires session_start
 * @requires config/Database.php
 * @requires admin role
 * @response application/json
 */

session_start();

require_once '../config/Database.php';
require_once __DIR__ . '/../src/Models/Metrica.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$rango = $_GET['rango'] ?? '30d';
$desdeParam = $_GET['desde'] ?? null;
$hastaParam = $_GET['hasta'] ?? null;

if (!in_array($rango, ['7d', '30d', '12m', 'custom'], true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Rango inválido']);
    exit;
}

$db = Database::getInstance();
$metrica = new Metrica($db);

try {
    [$desde, $hasta] = $metrica->resolverRango($rango, $desdeParam, $hastaParam);

    echo json_encode([
        'status' => 'success',
        'rango' => ['desde' => $desde, 'hasta' => $hasta],
        'data' => [
            'kpis' => $metrica->kpis($desde, $hasta),
            'por_estado' => $metrica->porEstado($desde, $hasta),
            'por_especialidad' => $metrica->porEspecialidad($desde, $hasta),
            'top_doctores' => $metrica->topDoctores($desde, $hasta, 10),
            'tendencia_mensual' => $metrica->tendenciaMensual(),
            'pacientes_nuevos_mensual' => $metrica->pacientesNuevosPorMes(),
            'pacientes_activos' => $metrica->totalPacientesActivos(),
            'doctores_sin_consultorio' => $metrica->doctoresSinConsultorio(),
            'ocupacion_doctores' => $metrica->ocupacionPorDoctor($desde, $hasta, 10),
        ],
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error en get_totales.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor']);
}
