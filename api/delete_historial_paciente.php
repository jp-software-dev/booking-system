<?php
/**
 * ENDPOINT DE OCULTAMIENTO DE HISTORIAL (PACIENTE)
 *
 * Permite a un paciente autenticado quitar una cita pasada de su propia vista
 * de historial. No es un DELETE físico: la fila se conserva para el historial
 * administrativo y las métricas del dashboard, solo se marca
 * `oculto_para_paciente = 1`.
 *
 * @requires session_start
 * @requires config/Database.php
 * @requires method POST
 * @requires paciente role
 * @response application/json
 */

session_start();

require_once '../config/Database.php';
require_once __DIR__ . '/../src/Helpers/CsrfHelper.php';

use Helpers\CsrfHelper;

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'paciente') {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['status' => 'error', 'message' => 'Datos inválidos']);
    exit;
}

$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? ($input['csrf_token'] ?? null);
if (!CsrfHelper::validateToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Token de seguridad inválido']);
    exit;
}

$id_cita = $input['id_cita'] ?? 0;
if (!$id_cita) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

$db = Database::getInstance();

try {
    // PROTECCIÓN DE PROPIEDAD: mismo patrón que update_cita_user.php.
    $check = $db->prepare("SELECT id_cita, estado_cita FROM citas WHERE id_cita = ? AND id_paciente = ?");
    $check->execute([$id_cita, $_SESSION['user_id']]);
    $cita = $check->fetch();

    if (!$cita) {
        echo json_encode(['status' => 'error', 'message' => 'Cita no encontrada o no pertenece al paciente']);
        exit;
    }

    if (!in_array($cita['estado_cita'], ['Completada', 'Cancelada'], true)) {
        echo json_encode(['status' => 'error', 'message' => 'Solo puedes eliminar registros de citas completadas o canceladas']);
        exit;
    }

    $db->prepare("UPDATE citas SET oculto_para_paciente = 1 WHERE id_cita = ?")->execute([$id_cita]);

    echo json_encode(['status' => 'success', 'message' => 'Registro eliminado de tu historial']);

} catch (PDOException $e) {
    error_log("PDO Error en delete_historial_paciente.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error de base de datos']);
} catch (Exception $e) {
    error_log("Error en delete_historial_paciente.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor']);
}
