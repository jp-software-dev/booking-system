<?php
/**
 * ENDPOINT DE ACTUALIZACIÓN DE CITA (ADMIN)
 *
 * Permite a un administrador modificar la fecha y hora de una cita existente.
 * Incluye verificación de disponibilidad para evitar conflictos de horario.
 *
 * @requires session_start
 * @requires config/Database.php
 * @requires src/Helpers/ValidationHelper.php
 * @requires method POST
 * @requires admin role
 * @response application/json
 */

session_start();
require_once '../config/Database.php';
require_once '../src/Helpers/ValidationHelper.php';

use Helpers\ValidationHelper;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id_cita = $input['id_cita'] ?? 0;
$fecha = trim($input['fecha_cita'] ?? '');
$hora = trim($input['hora_inicio'] ?? '');

if (!$id_cita || empty($fecha) || empty($hora)) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

$hora = substr($hora, 0, 5);

if (!ValidationHelper::validateDate($fecha)) {
    echo json_encode(['status' => 'error', 'message' => 'Fecha inválida']);
    exit;
}
if (!ValidationHelper::validateTime($hora)) {
    echo json_encode(['status' => 'error', 'message' => 'Hora inválida (use HH:MM)']);
    exit;
}

$db = Database::getInstance();

try {
    $stmt = $db->prepare("SELECT id_doctor FROM citas WHERE id_cita = ?");
    $stmt->execute([$id_cita]);
    $cita = $stmt->fetch();

    if (!$cita) {
        echo json_encode(['status' => 'error', 'message' => 'Cita no encontrada']);
        exit;
    }

    $id_doctor = $cita['id_doctor'];

    $check = $db->prepare("SELECT id_cita FROM citas WHERE id_doctor = ? AND fecha_cita = ? AND hora_inicio = ? AND estado_cita != 'Cancelada' AND id_cita != ?");
    $check->execute([$id_doctor, $fecha, $hora, $id_cita]);

    if ($check->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'El horario seleccionado ya no está disponible']);
        exit;
    }

    $update = $db->prepare("UPDATE citas SET fecha_cita = ?, hora_inicio = ? WHERE id_cita = ?");
    $update->execute([$fecha, $hora, $id_cita]);

    echo json_encode(['status' => 'success', 'message' => 'Cita actualizada correctamente']);

} catch (Throwable $e) {
    error_log("Error crítico en update_cita_admin.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor']);
}
?>