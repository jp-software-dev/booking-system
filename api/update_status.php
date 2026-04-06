<?php
session_start();

require_once '../config/Database.php';
require_once '../src/Helpers/Mailer.php';

// 👇 CORRECCIÓN 1: Importamos la clase Mailer correctamente 👇
use Helpers\Mailer;

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
$estado = $input['estado'] ?? '';

if (!$id_cita || !$estado) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

$db = Database::getInstance();

try {
    $db->beginTransaction();

    $update = $db->prepare("UPDATE citas SET estado_cita = ? WHERE id_cita = ?");
    $update->execute([$estado, $id_cita]);

    if ($estado === 'Confirmada' || $estado === 'Cancelada') {
        
        $query = "SELECT c.fecha_cita, c.hora_inicio, p.nombre AS paciente, p.email, 
                         d.nombre AS doctor, d.apellido_paterno AS doctor_ap 
                  FROM citas c 
                  JOIN pacientes p ON c.id_paciente = p.id_paciente 
                  JOIN doctores d ON c.id_doctor = d.id_doctor 
                  WHERE c.id_cita = ?";
        $infoStmt = $db->prepare($query);
        $infoStmt->execute([$id_cita]);
        $info = $infoStmt->fetch();

        if ($info && !empty($info['email'])) {
            $fechaFormato = date('d/m/Y', strtotime($info['fecha_cita']));
            $horaFormato = date('h:i A', strtotime($info['hora_inicio']));
            $nombreDr = "Dr. " . $info['doctor'] . " " . $info['doctor_ap'];

            if ($estado === 'Confirmada') {
                $asunto = "Cita Confirmada - MediAgenda Elite";
                $colorHeader = "#0d6efd"; 
                $titulo = "¡Tu cita está confirmada!";
                $texto = "Nos complace confirmarte que tu cita médica ha sido agendada con éxito. <br><br><strong>¡Gracias por elegirnos!</strong> Valoramos mucho tu confianza en MediAgenda Elite para el cuidado de tu salud. Te esperamos en nuestras instalaciones en la fecha y hora indicadas.";
            } else { 
                $asunto = "Cita Cancelada - MediAgenda Elite";
                $colorHeader = "#dc3545"; 
                $titulo = "Cita Cancelada";
                $texto = "Te informamos que tu cita médica ha sido cancelada. Si se trató de un error o deseas reprogramarla para otra fecha, puedes hacerlo ingresando a tu portal de paciente.";
            }

            $mensajeHTML = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.05);">
                <div style="background-color: '.$colorHeader.'; padding: 25px; text-align: center;">
                    <h2 style="color: #ffffff; margin: 0; font-size: 24px;">MediAgenda Elite</h2>
                </div>
                <div style="padding: 30px; background-color: #ffffff;">
                    <h3 style="color: #333333; margin-top: 0;">'.$titulo.'</h3>
                    <p style="color: #555555; font-size: 16px;">Hola <strong>' . htmlspecialchars($info['paciente']) . '</strong>,</p>
                    <p style="color: #555555; font-size: 16px; line-height: 1.5;">'.$texto.'</p>
                        
                    <div style="background-color: #f8f9fa; border-left: 4px solid '.$colorHeader.'; padding: 15px; margin: 25px 0;">
                        <p style="margin: 5px 0; color: #333;"><strong>Especialista:</strong> '.$nombreDr.'</p>
                        <p style="margin: 5px 0; color: #333;"><strong>Fecha:</strong> '.$fechaFormato.'</p>
                        <p style="margin: 5px 0; color: #333;"><strong>Hora:</strong> '.$horaFormato.'</p>
                    </div>
                        
                    <p style="color: #777777; font-size: 14px; text-align: center; margin-top: 30px;">Si tienes alguna duda, por favor contáctanos respondiendo a este correo.</p>
                </div>
            </div>';

            // 👇 CORRECCIÓN 2: Agregamos "true" al final para que PHPMailer sepa que es formato HTML 👇
            $mailEnviado = Mailer::enviarCorreo($info['email'], $asunto, $mensajeHTML, true);
            
            if($mailEnviado !== true) {
                error_log("Error enviando correo de confirmación a " . $info['email'] . ": " . $mailEnviado);
            }
        }
    }

    $db->commit();
    echo json_encode(['status' => 'success', 'message' => 'Estado actualizado y notificado']);

// 👇 CORRECCIÓN 3: Cambiamos Exception por Throwable 👇
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error crítico en update_status.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor procesando el estado.']);
}
?>