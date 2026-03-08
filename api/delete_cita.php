<?php
// ELIMINAR CITA: Script exclusivo para administradores que realiza un borrado físico (Hard Delete) de un registro en la base de datos.
session_start();
require_once '../config/Database.php';

// FORMATO DE RESPUESTA: Establece la salida del archivo como JSON para ser procesada por las alertas del frontend.
header('Content-Type: application/json');

// VALIDADOR DE MÉTODO: Bloquea intentos de acceso que no utilicen el protocolo POST para mayor seguridad.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

// VALIDADOR DE PERMISOS: Asegura que el usuario tenga una sesión activa y posea estrictamente el rol de administrador.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

// CAPTURA DE DATOS: Lee el cuerpo de la petición y extrae el ID de la cita que se desea eliminar.
$input = json_decode(file_get_contents('php://input'), true);
$id_cita = $input['id_cita'] ?? 0;

// VALIDADOR DE PARÁMETROS: Verifica que se haya recibido un ID válido antes de intentar la operación en la BD.
if (!$id_cita) {
    echo json_encode(['status' => 'error', 'message' => 'ID de cita no proporcionado']);
    exit;
}

// CONEXIÓN DB: Obtiene la instancia única de acceso a la base de datos.
$db = Database::getInstance();

try {
    // EJECUCIÓN DE BORRADO: Prepara y lanza la instrucción SQL para remover permanentemente la cita según su ID único.
    $stmt = $db->prepare("DELETE FROM citas WHERE id_cita = ?");
    $stmt->execute([$id_cita]);

    // CONFIRMACIÓN DE ACCIÓN: Verifica si realmente se borró un registro y responde con éxito o error según el resultado.
    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Cita eliminada']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Cita no encontrada']);
    }
} catch (Exception $e) {
    // MANEJO DE EXCEPCIONES: Registra fallos inesperados en el log del servidor y devuelve un mensaje de error genérico.
    error_log("Error en delete_cita.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor']);
}
?>