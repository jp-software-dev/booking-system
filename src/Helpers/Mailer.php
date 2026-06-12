<?php
namespace Helpers;

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    public static function enviarCorreo(string $destinatario, string $asunto, string $mensaje, bool $isHtml = false)
    {
        if (empty($destinatario)) {
            return "Destinatario vacío";
        }

        $mail = null;

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['SMTP_USER'] ?? '';
            $mail->Password   = $_ENV['SMTP_PASS'] ?? '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $_ENV['SMTP_PORT'] ?? 587;
            $mail->CharSet    = 'UTF-8';
            
            $mail->setFrom($_ENV['SMTP_FROM'] ?? 'no-reply@mediagenda.mx', 'MediAgenda');
            $mail->addAddress($destinatario);
            
            $mail->isHTML($isHtml);
            $mail->Subject = $asunto;
            $mail->Body    = $isHtml ? $mensaje : nl2br($mensaje);
            $mail->AltBody = $isHtml ? strip_tags($mensaje) : $mensaje;
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            $errorMsg = "Error PHPMailer: " . ($mail ? $mail->ErrorInfo : $e->getMessage());
            error_log($errorMsg);
            return $errorMsg;
        }
    }
}