<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Env;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * ENVÍO DE CORREOS (SMTP CON PHPMAILER)
 *
 * Variables de entorno usadas:
 *   MAIL_DRIVER  "smtp" (por defecto) envía el correo. "log" no lo envía: lo
 *                escribe en el log de PHP, útil en desarrollo sin servidor SMTP.
 *   SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM
 *
 * Nunca lanza excepciones: devuelve false y registra el motivo en el log, para
 * que un fallo de correo no rompa la operación que lo originó.
 */
final class Mailer
{
    /** Segundos máximos de espera al servidor SMTP antes de rendirse. */
    private const TIMEOUT_SMTP = 15;

    /**
     * Envía un correo.
     *
     * @param string $destinatario Correo del destinatario.
     * @param string $asunto       Asunto.
     * @param string $mensaje      Cuerpo del correo (texto plano o HTML).
     * @param bool   $esHtml       true si $mensaje es HTML.
     * @return bool true si se envió (o se registró en modo "log").
     */
    public static function enviarCorreo(string $destinatario, string $asunto, string $mensaje, bool $esHtml = false): bool
    {
        if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
            error_log('Mailer: destinatario inválido o vacío.');
            return false;
        }

        if (Env::get('MAIL_DRIVER', 'smtp') === 'log') {
            error_log(sprintf("Mailer (modo log) -> Para: %s | Asunto: %s\n%s", $destinatario, $asunto, $esHtml ? strip_tags($mensaje) : $mensaje));
            return true;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = (string) Env::get('SMTP_HOST', 'smtp.gmail.com');
            $mail->SMTPAuth   = true;
            $mail->Username   = (string) Env::get('SMTP_USER', '');
            $mail->Password   = (string) Env::get('SMTP_PASS', '');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) Env::get('SMTP_PORT', '587');
            $mail->Timeout    = self::TIMEOUT_SMTP;
            $mail->CharSet    = PHPMailer::CHARSET_UTF8;

            $mail->setFrom((string) Env::get('SMTP_FROM', 'no-reply@mediagenda.mx'), 'MediAgenda');
            $mail->addAddress($destinatario);

            $mail->isHTML($esHtml);
            $mail->Subject = $asunto;
            $mail->Body    = $mensaje;
            // Versión en texto plano para clientes de correo que no muestran HTML.
            $mail->AltBody = $esHtml ? strip_tags($mensaje) : $mensaje;

            $mail->send();
            return true;
        } catch (PHPMailerException $e) {
            error_log('Error de PHPMailer: ' . ($mail->ErrorInfo ?: $e->getMessage()));
            return false;
        }
    }
}
