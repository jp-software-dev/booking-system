<?php
namespace Helpers;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
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
     * @return bool|string         True si se envió correctamente, mensaje de error en caso contrario
     */
    public static function enviarCorreo(string $destinatario, string $asunto, string $mensaje, bool $isHtml = false)
    {
        // Verificar que el destinatario no esté vacío
        if (empty($destinatario)) {
            return "Destinatario vacío";
        }

        try {
            // Cargar configuración
            $configPath = __DIR__ . '/../../config/mail.php';
            if (!file_exists($configPath)) {
                return "Archivo de configuración mail.php no encontrado en: " . $configPath;
            }
            
            $config = include $configPath;
            
            // Verificar que la configuración tenga los datos necesarios
            if (empty($config['host']) || empty($config['username']) || empty($config['password'])) {
                return "Configuración SMTP incompleta. Host: " . ($config['host'] ?? 'vacio') . ", User: " . ($config['username'] ?? 'vacio');
            }
            
            $mail = new PHPMailer(true);
            
            // Configuración de depuración (opcional, activar solo para pruebas)
            // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            
            // Configuración del servidor SMTP
            $mail->isSMTP();
            $mail->Host       = $config['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $config['username'];
            $mail->Password   = $config['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $config['port'];
            $mail->CharSet    = 'UTF-8';
            
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
            $errorMsg = "Error PHPMailer: " . $mail->ErrorInfo;
            error_log($errorMsg);
            return $errorMsg;
        } catch (\Throwable $e) {
            $errorMsg = "Error general: " . $e->getMessage();
            error_log($errorMsg);
            return $errorMsg;
        }
    }
}
?>