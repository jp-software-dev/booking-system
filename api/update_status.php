<?php
/**
 * ENDPOINT DE GESTIÓN DE ESTADOS Y NOTIFICACIONES AUTOMATIZADAS
 *
 * Actualiza el estado de una cita y despacha una notificación por correo electrónico 
 * con redacción optimizada y clara hacia el paciente.
 */

session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../src/Helpers/Mailer.php';
require_once __DIR__ . '/../src/Helpers/CsrfHelper.php';
require_once __DIR__ . '/../src/Helpers/Bitacora.php';

use Helpers\CsrfHelper;
use Helpers\Mailer;
use Helpers\Bitacora;

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id_cita = $input['id_cita'] ?? 0;

$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? ($input['csrf_token'] ?? null);
if (!CsrfHelper::validateToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Token de seguridad inválido']);
    exit;
}
$estado = $input['estado'] ?? '';

if (!$id_cita || empty($estado)) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

$estadosValidos = ['Pendiente', 'Confirmada', 'En Curso', 'Completada', 'Cancelada'];
if (!in_array($estado, $estadosValidos, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Estado no válido']);
    exit;
}

try {
    $db = Database::getInstance();
    $db->beginTransaction();

    $stmtUpdate = $db->prepare("UPDATE citas SET estado_cita = ? WHERE id_cita = ?");
    $stmtUpdate->execute([$estado, $id_cita]);

    // Se verifica si la actualización afectó a alguna fila. Si no, la cita no existía.
    if ($stmtUpdate->rowCount() === 0) {
        $db->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Cita no encontrada']);
        exit;
    }

    if (in_array($estado, ['Confirmada', 'Cancelada'])) {
        $stmtInfo = $db->prepare("SELECT p.email, p.nombre AS paciente_nombre, p.recibir_notificaciones, d.nombre AS doctor_nombre, d.apellido_paterno AS doctor_ap, c.fecha_cita, c.hora_inicio FROM citas c JOIN pacientes p ON c.id_paciente = p.id_paciente JOIN doctores d ON c.id_doctor = d.id_doctor WHERE c.id_cita = ?");
        $stmtInfo->execute([$id_cita]);
        $info = $stmtInfo->fetch();

        if (!$info) { throw new Exception("No se pudo recuperar la información de la cita para notificar."); }

        // El paciente puede desactivar los correos de confirmación/cancelación desde
        // Administrar Cuenta > Preferencias de notificación.
        if ($info['recibir_notificaciones']) {
            $nombreDr = htmlspecialchars($info['doctor_nombre'] . ' ' . $info['doctor_ap']);
            $fechaFormato = date('d/m/Y', strtotime($info['fecha_cita']));
            $horaFormato = date('h:i A', strtotime($info['hora_inicio']));

            $asunto = "Actualización de tu cita - MediAgenda";
            $colorHeader = $estado === 'Confirmada' ? '#1a56db' : '#dc3545';

            // Redacción natural solicitada para el cuerpo del mensaje
            if ($estado === 'Confirmada') {
                $textoStatus = 'ha sido confirmada por nuestro equipo. ¡Te esperamos!';
            } else {
                $textoStatus = 'ha sido cancelada por nuestro equipo.';
            }

            $mensajeHTML = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                <div style="background-color: ' . $colorHeader . '; padding: 20px; text-align: center; color: white;">
                    <h2 style="margin: 0; font-weight: bold;">MediAgenda</h2>
                </div>
                <div style="padding: 25px; color: #1f2937; line-height: 1.6;">
                    <p style="font-size: 16px;">Hola <strong>' . htmlspecialchars($info['paciente_nombre']) . '</strong>,</p>
                    <p style="font-size: 15px;">Te informamos que tu cita médica con el ID #' . $id_cita . ' ' . $textoStatus . '</p>

                    <div style="background-color: #f8f9fa; border-left: 4px solid ' . $colorHeader . '; padding: 15px; margin: 25px 0; border-radius: 0 6px 6px 0;">
                        <p style="margin: 6px 0; font-size: 14px; color: #4b5563;"><strong>Especialista:</strong> Dr. ' . $nombreDr . '</p>
                        <p style="margin: 6px 0; font-size: 14px; color: #4b5563;"><strong>Fecha:</strong> ' . $fechaFormato . '</p>
                        <p style="margin: 6px 0; font-size: 14px; color: #4b5563;"><strong>Hora:</strong> ' . $horaFormato . '</p>
                    </div>

                    <p style="color: #6b7280; font-size: 13px; text-align: center; margin-top: 30px; border-top: 1px solid #eee; pt-3;">
                        Si tienes alguna duda o aclaración, por favor ponte en contacto con nosotros.
                    </p>
                </div>
            </div>';

            Mailer::enviarCorreo($info['email'], $asunto, $mensajeHTML, true);
        }
    }

    Bitacora::registrar('cambiar_estado_cita', 'cita', (int)$id_cita, 'Nuevo estado: ' . $estado);

    $db->commit();
    echo json_encode(['status' => 'success', 'message' => 'Estado actualizado correctamente']);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error en update_status.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error al actualizar el estado de la cita']);
}