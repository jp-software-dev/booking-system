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

header('Content-Type: application/json');

// VALIDADOR DE MÉTODO: Bloquea accesos que no utilicen el método POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
$id_cita = $input['id_cita'] ?? 0;

// VALIDADOR DE PARÁMETROS: Verifica que se haya recibido un ID válido.
if (!$id_cita) {
    echo json_encode(['status' => 'error', 'message' => 'ID de cita no proporcionado']);
    exit;
}

$db = Database::getInstance();

try {
    // EJECUCIÓN DE BORRADO: Prepara y lanza la instrucción SQL para remover la cita permanentemente.
    $stmt = $db->prepare("DELETE FROM citas WHERE id_cita = ?");
    $stmt->execute([$id_cita]);

    // CONFIRMACIÓN DE ACCIÓN: Verifica si se borró un registro y responde en consecuencia.
    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Cita eliminada']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Cita no encontrada']);
    }
} catch (Exception $e) {
    // MANEJO DE EXCEPCIONES: Registra fallos en el log y devuelve un mensaje genérico.
    error_log("Error en delete_cita.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor']);
}
?>