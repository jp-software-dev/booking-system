<?php
/**
 * ENDPOINT DE ELIMINACIÓN DE CITAS (ADMIN)
 *
 * Script exclusivo para administradores que realiza un borrado físico (Hard Delete)
 * de un registro de cita en la base de datos.
 *
 * @requires session_start
 * @requires config/Database.php
 * @requires method POST
 * @requires admin role
 * @response application/json
 */

session_start();
require_once '../config/Database.php';
require_once '../src/Controllers/AppointmentController.php';
require_once '../src/Helpers/CsrfHelper.php';
require_once '../src/Helpers/Bitacora.php';

use Helpers\CsrfHelper;
use Helpers\Bitacora;

header('Content-Type: application/json');

// VALIDADOR DE MÉTODO: Bloquea accesos que no utilicen el método POST.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

// VALIDADOR DE PERMISOS: Asegura que el usuario tenga sesión activa y rol de administrador.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

// CAPTURA DE DATOS: Lee el cuerpo de la petición y extrae el ID de la cita.
$input = json_decode(file_get_contents('php://input'), true);
$id_cita = (int)($input['id_cita'] ?? 0);

$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? ($input['csrf_token'] ?? null);
if (!CsrfHelper::validateToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Token de seguridad inválido']);
    exit;
}

// VALIDADOR DE PARÁMETROS: Verifica que se haya recibido un ID válido.
if (!$id_cita) {
    echo json_encode(['status' => 'error', 'message' => 'ID de cita no proporcionado']);
    exit;
}
$controller = new AppointmentController();
$resultado = $controller->delete($id_cita, $_SESSION['role']);

if (($resultado['status'] ?? '') === 'success') {
    Bitacora::registrar('eliminar_cita', 'cita', $id_cita);
}

echo json_encode($resultado);