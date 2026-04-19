<?php
/**
 * ENDPOINT DE REPROGRAMACIÓN DE CITA (PACIENTE)
 *
 * Permite a un paciente autenticado modificar su propia cita. Al reprogramar,
 * el estado de la cita vuelve a 'Pendiente' para que el administrador la revise
 * y la confirme nuevamente.
 *
 * @requires session_start
 * @requires config/Database.php
 * @requires src/Helpers/Mailer.php
 * @requires method POST
 * @requires paciente role
 * @response application/json
 */

session_start();

require_once '../config/Database.php';
require_once '../src/Helpers/Mailer.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

$id_cita = $input['id_cita'] ?? 0;
$fecha = $input['fecha_cita'] ?? '';
$hora = $input['hora_inicio'] ?? '';
$motivo = $input['motivo'] ?? '';

if (!$id_cita || !$fecha || !$hora) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

if (!DateTime::createFromFormat('Y-m-d', $fecha)) {
    echo json_encode(['status' => 'error', 'message' => 'Fecha inválida']);
    exit;
}

if (!DateTime::createFromFormat('H:i', $hora)) {
    echo json_encode(['status' => 'error', 'message' => 'Hora inválida (use HH:MM)']);
    exit;
}

$db = Database::getInstance();

try {
    // PROTECCIÓN DE PROPIEDAD: Verifica que la cita pertenezca al paciente autenticado.
    $check = $db->prepare("SELECT id_cita, id_doctor, estado_cita FROM citas WHERE id_cita = ? AND id_paciente = ?");
    $check->execute([$id_cita, $_SESSION['user_id']]);
    $cita = $check->fetch();
    
    if (!$cita) {
        echo json_encode(['status' => 'error', 'message' => 'Cita no encontrada o no pertenece al paciente']);
        exit;
    }

    // RESTRICCIÓN DE ESTADO: Evita modificar citas canceladas o completadas.
    if ($cita['estado_cita'] === 'Cancelada' || $cita['estado_cita'] === 'Completada') {
        echo json_encode(['status' => 'error', 'message' => 'No se puede modificar una cita cancelada o completada']);
        exit;
    }

    // PREVENCIÓN DE EMPALMES: Verifica que el nuevo horario esté disponible.
    $checkDisponible = $db->prepare("SELECT id_cita FROM citas WHERE id_doctor = ? AND fecha_cita = ? AND hora_inicio = ? AND estado_cita != 'Cancelada' AND id_cita != ?");
    $checkDisponible->execute([$cita['id_doctor'], $fecha, $hora, $id_cita]);
    if ($checkDisponible->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'El horario seleccionado ya no está disponible']);
        exit;
    }

    // ACTUALIZACIÓN DE DATOS: Aplica los cambios y fuerza el estado a 'Pendiente'.
    $update = $db->prepare("UPDATE citas SET fecha_cita = ?, hora_inicio = ?, motivo_consulta = ?, estado_cita = 'Pendiente' WHERE id_cita = ?");
    $update->execute([$fecha, $hora, $motivo, $id_cita]);

    echo json_encode(['status' => 'success', 'message' => 'Cita actualizada correctamente']);
    
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo json_encode(['status' => 'error', 'message' => 'El horario seleccionado acaba de ser ocupado por otro paciente. Intenta con otro.']);
    } else {
        error_log("Error en update_cita_user.php: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Error de base de datos']);
    }
} catch (Exception $e) {
    error_log("Error en update_cita_user.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor']);
}
?>