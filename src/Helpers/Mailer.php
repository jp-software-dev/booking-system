<?php
namespace Helpers;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    /**
     * Envía un correo electrónico usando la configuración SMTP.
     *
     * @param string $destinatario Correo del destinatario
     * @param string $asunto       Asunto del mensaje
     * @param string $mensaje      Cuerpo del mensaje
     * @param bool   $isHtml       Si el mensaje es HTML (true) o texto plano (false)
     * @return bool                True si se envió correctamente, False en caso de error
     */
    public static function enviarCorreo(string $destinatario, string $asunto, string $mensaje, bool $isHtml = false): bool
    {
        $config = include __DIR__ . '/../../config/mail.php';
        
        $mail = new PHPMailer(true);
        
        try {
            // Configuración del servidor SMTP
            $mail->isSMTP();
            $mail->Host       = $config['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $config['username'];
            $mail->Password   = $config['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $config['port'];
            
            // Remitente y destinatario
            $mail->setFrom($config['from_email'], $config['from_name']);
            $mail->addAddress($destinatario);
            
            // Contenido
            $mail->isHTML($isHtml);
            $mail->Subject = $asunto;
            $mail->Body    = $isHtml ? $mensaje : nl2br($mensaje);
            $mail->AltBody = $isHtml ? strip_tags($mensaje) : $mensaje;
            
            $mail->send();
            return true;
            
        } catch (Exception $e) {
            error_log("Error enviando correo a $destinatario: " . $mail->ErrorInfo);
            return false;
        }
    }
}