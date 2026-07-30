<?php
/**
 * ENDPOINT DE AUTOGESTIÓN DE PERFIL (PACIENTE)
 *
 * Permite a un paciente autenticado actualizar sus propios datos personales y sus
 * preferencias de notificación. El cambio de contraseña NO se maneja aquí: requiere
 * confirmación por correo, ver solicitar_cambio_password.php / confirmar_cambio_password.php.
 * El id_paciente siempre se toma de la sesión — nunca del cliente.
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
require_once __DIR__ . '/../src/Helpers/ValidationHelper.php';
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

$id_paciente = $_SESSION['user_id'];
$db = Database::getInstance();

$actualizaPerfil = array_key_exists('nombre', $input);
$actualizaNotificaciones = array_key_exists('recibir_notificaciones', $input);

if (!$actualizaPerfil && !$actualizaNotificaciones) {
    echo json_encode(['status' => 'error', 'message' => 'No hay cambios que aplicar']);
    exit;
}

try {
    $db->beginTransaction();

    if ($actualizaPerfil) {
        $nombre           = trim($input['nombre'] ?? '');
        $apellido_paterno = trim($input['apellido_paterno'] ?? '');
        $apellido_materno = trim($input['apellido_materno'] ?? '');
        $telefono         = trim($input['telefono'] ?? '');
        $email            = trim($input['email'] ?? '');

        if (empty($nombre) || empty($apellido_paterno) || empty($telefono) || empty($email)) {
            $db->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Nombre, apellido paterno, teléfono y correo son obligatorios']);
            exit;
        }

        if (!preg_match('/^[0-9]{10}$/', $telefono)) {
            $db->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'El teléfono debe contener exactamente 10 dígitos numéricos']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $db->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'El correo electrónico no es válido']);
            exit;
        }

        $checkEmail = $db->prepare("SELECT id_paciente FROM pacientes WHERE email = ? AND id_paciente != ?");
        $checkEmail->execute([$email, $id_paciente]);
        if ($checkEmail->fetch()) {
            $db->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Ese correo ya está en uso por otra cuenta']);
            exit;
        }

        $update = $db->prepare("UPDATE pacientes SET nombre = ?, apellido_paterno = ?, apellido_materno = ?, telefono = ?, email = ? WHERE id_paciente = ?");
        $update->execute([$nombre, $apellido_paterno, $apellido_materno ?: null, $telefono, $email, $id_paciente]);

        $_SESSION['user_name'] = $nombre;
    }

    if ($actualizaNotificaciones) {
        $recibirNotificaciones = !empty($input['recibir_notificaciones']) ? 1 : 0;
        $db->prepare("UPDATE pacientes SET recibir_notificaciones = ? WHERE id_paciente = ?")
           ->execute([$recibirNotificaciones, $id_paciente]);
    }

    $db->commit();

    $detalle = implode(', ', array_filter([
        $actualizaPerfil ? 'datos personales' : null,
        $actualizaNotificaciones ? 'preferencias de notificación' : null,
    ]));
    Bitacora::registrar('actualizar_perfil_paciente', 'paciente', (int)$id_paciente, "Campos actualizados: $detalle");

    echo json_encode(['status' => 'success', 'message' => 'Datos actualizados correctamente']);

} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log("PDO Error en update_perfil.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error de base de datos']);
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log("Error en update_perfil.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor']);
}
