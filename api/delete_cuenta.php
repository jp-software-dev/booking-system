<?php
/**
 * ENDPOINT DE BAJA DE CUENTA (PACIENTE)
 *
 * Autogestión: el paciente elimina su propia cuenta. Se aplica BORRADO LÓGICO
 * con anonimización, no DELETE físico:
 *  - `citas` referencia a `pacientes` con FK ON DELETE CASCADE. Un DELETE
 *    físico del paciente arrastraría también sus citas pasadas, rompiendo el
 *    historial clínico y las métricas del dashboard administrativo (Parte 8)
 *    que dependen de esas filas.
 *  - Es un sistema de salud: el registro de atenciones pasadas tiene valor
 *    administrativo/estadístico incluso sin el paciente activo.
 * Por eso se anonimizan los datos identificables (nombre, email, teléfono)
 * y se marca `cuenta_eliminada = 1`, dejando las citas intactas pero
 * ya no asociables a una persona identificable.
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
require_once __DIR__ . '/../src/Helpers/Bitacora.php';

use Helpers\CsrfHelper;
use Helpers\Bitacora;

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

// Confirmación explícita: refleja server-side el doble check del modal (checkbox + botón).
if (empty($input['confirmar'])) {
    echo json_encode(['status' => 'error', 'message' => 'Confirmación requerida']);
    exit;
}

$id_paciente = $_SESSION['user_id'];
$db = Database::getInstance();

try {
    $db->beginTransaction();

    $check = $db->prepare("SELECT id_paciente FROM pacientes WHERE id_paciente = ? AND cuenta_eliminada = 0");
    $check->execute([$id_paciente]);
    if (!$check->fetch()) {
        $db->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Cuenta no encontrada']);
        exit;
    }

    $emailAnonimo = 'baja_' . $id_paciente . '_' . bin2hex(random_bytes(6)) . '@cuenta-eliminada.local';

    $update = $db->prepare("UPDATE pacientes SET
            nombre = 'Usuario eliminado',
            apellido_paterno = '',
            apellido_materno = NULL,
            telefono = NULL,
            email = ?,
            cuenta_eliminada = 1
        WHERE id_paciente = ?");
    $update->execute([$emailAnonimo, $id_paciente]);

    // Invalida la contraseña para que la cuenta no pueda volver a autenticarse.
    $hashInvalido = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $db->prepare("UPDATE pacientes_credenciales SET password_hash = ?, cuenta_verificada = 0 WHERE id_paciente = ?")
       ->execute([$hashInvalido, $id_paciente]);

    Bitacora::registrar('eliminar_cuenta_paciente', 'paciente', $id_paciente, 'Autogestión: baja de cuenta');

    $db->commit();

    // Mismo patrón de destrucción de sesión que logout.php.
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();

    echo json_encode(['status' => 'success', 'message' => 'Tu cuenta ha sido eliminada.', 'redirect' => 'index.php']);

} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log("PDO Error en delete_cuenta.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error de base de datos']);
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log("Error en delete_cuenta.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor']);
}
