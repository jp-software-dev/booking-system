<?php
// ENDPOINT DE ESTADO: Archivo API que actualiza el estado de una cita y notifica al paciente con un correo HTML solo si fue confirmada o cancelada.

// INICIALIZACIÓN DE SESIÓN: Reanuda la sesión activa para poder verificar los permisos de quien hace la petición.
session_start();

// DEPENDENCIAS: Carga la configuración centralizada de la base de datos y la clase Mailer para notificaciones.
require_once '../config/Database.php';
require_once '../src/Helpers/Mailer.php';

// FORMATO DE RESPUESTA: Configura la cabecera HTTP asegurando que el frontend reciba e interprete los datos como JSON.
header('Content-Type: application/json');

// VALIDADOR DE MÉTODO: Protege el archivo bloqueando cualquier petición directa por URL (GET) y obligando el uso de POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

// CONTROL DE ACCESO: Restringe la ejecución del script exclusivamente a usuarios autenticados con rol de administrador.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

// LECTOR DE DATOS: Captura y decodifica el cuerpo de la petición JSON enviada desde la tabla del panel administrativo.
$input = json_decode(file_get_contents('php://input'), true);
$id_cita = $input['id_cita'] ?? 0;
$estado = $input['estado'] ?? '';

// VALIDADOR DE CAMPOS: Verifica que el identificador de la cita y el nuevo estado no estén vacíos.
if (!$id_cita || !$estado) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

// CONEXIÓN DB: Llama a la instancia activa y única de la base de datos utilizando el patrón de diseño Singleton.
$db = Database::getInstance();

try {
    // TRANSACCIÓN: Inicia un proceso seguro para garantizar que la actualización y el envío de datos sean consistentes.
    $db->beginTransaction();

    // ACTUALIZACIÓN DE ESTADO: Ejecuta la sentencia SQL para sobreescribir el estado de la cita especificada.
    $update = $db->prepare("UPDATE citas SET estado_cita = ? WHERE id_cita = ?");
    $update->execute([$estado, $id_cita]);

    // FILTRO DE NOTIFICACIÓN: Condiciona el envío de correos estrictamente para cuando la cita se Confirma o se Cancela.
    if ($estado === 'Confirmada' || $estado === 'Cancelada') {
        
        // EXTRACCIÓN DE DATOS: Consulta la información completa de la cita, paciente y doctor para personalizar el correo.
        $query = "SELECT c.fecha_cita, c.hora_inicio, p.nombre AS paciente, p.email, 
                         d.nombre AS doctor, d.apellido_paterno AS doctor_ap 
                  FROM citas c 
                  JOIN pacientes p ON c.id_paciente = p.id_paciente 
                  JOIN doctores d ON c.id_doctor = d.id_doctor 
                  WHERE c.id_cita = ?";
        $infoStmt = $db->prepare($query);
        $infoStmt->execute([$id_cita]);
        $info = $infoStmt->fetch();

        // VALIDACIÓN DE CORREO: Verifica que la consulta haya devuelto datos y que el paciente tenga un email registrado.
        if ($info && !empty($info['email'])) {
            $fechaFormato = date('d/m/Y', strtotime($info['fecha_cita']));
            $horaFormato = date('h:i A', strtotime($info['hora_inicio']));
            $nombreDr = "Dr. " . $info['doctor'] . " " . $info['doctor_ap'];

            // CONFIGURACIÓN VISUAL: Define el color (verde/rojo), el asunto y los textos dependiendo del nuevo estado de la cita.
            if ($estado === 'Confirmada') {
                $asunto = "Cita Confirmada - MediAgenda";
                $colorHeader = "#28a745"; 
                $titulo = "¡Tu cita ha sido confirmada!";
                $texto = "Nos complace informarte que tu cita médica ha sido confirmada exitosamente. Te esperamos en nuestras instalaciones en la fecha y hora indicadas.";
            } else { 
                $asunto = "Cita Cancelada - MediAgenda";
                $colorHeader = "#dc3545"; 
                $titulo = "Cita Cancelada";
                $texto = "Te informamos que tu cita médica ha sido cancelada. Si deseas reprogramarla, puedes hacerlo ingresando a tu portal de paciente.";
            }

            // PLANTILLA HTML: Construye el cuerpo del correo con un diseño profesional inyectando los datos dinámicos.
            $mensajeHTML = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.05);">
                <div style="background-color: '.$colorHeader.'; padding: 25px; text-align: center;">
                    <h2 style="color: #ffffff; margin: 0; font-size: 24px;">MediAgenda Elite</h2>
                </div>
                <div style="padding: 30px; background-color: #ffffff;">
                    <h3 style="color: #333333; margin-top: 0;">'.$titulo.'</h3>
                    <p style="color: #555555; font-size: 16px;">Hola <strong>' . htmlspecialchars($info['paciente']) . '</strong>,</p>
                    <p style="color: #555555; font-size: 16px;">'.$texto.'</p>
                        
                    <div style="background-color: #f8f9fa; border-left: 4px solid '.$colorHeader.'; padding: 15px; margin: 25px 0;">
                        <p style="margin: 5px 0;"><strong>Especialista:</strong> '.$nombreDr.'</p>
                        <p style="margin: 5px 0;"><strong>Fecha:</strong> '.$fechaFormato.'</p>
                        <p style="margin: 5px 0;"><strong>Hora:</strong> '.$horaFormato.'</p>
                    </div>
                        
                    <p style="color: #777777; font-size: 14px;">Si tienes alguna duda, por favor contáctanos.</p>
                </div>
            </div>';

            // DESPACHO DE CORREO: Envía la notificación HTML al paciente mediante la clase estática Mailer.
            Mailer::enviarCorreo($info['email'], $asunto, $mensajeHTML);
        }
    }

    // CONFIRMACIÓN DE DB: Aplica y guarda definitivamente la actualización del estado en la base de datos.
    $db->commit();
    echo json_encode(['status' => 'success', 'message' => 'Estado actualizado correctamente']);

} catch (Exception $e) {
    // MANEJO DE ERRORES: Si algo falla, revierte la actualización en la BD y devuelve un error al frontend.
    $db->rollBack();
    error_log("Error en update_status.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor']);
}
?>