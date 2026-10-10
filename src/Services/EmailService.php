<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Helpers\Mailer;

/**
 * CORREOS DE LA APLICACIÓN
 *
 * Arma el asunto y el cuerpo de cada correo que envía el sistema y delega el
 * envío en Mailer. Los datos de las citas vienen de Cita::obtenerDetalleNotificacion().
 *
 * Todos los métodos devuelven true si el correo se envió y false si no (por
 * configuración, preferencia del paciente o error de SMTP). Un correo fallido
 * nunca debe deshacer la operación que lo originó.
 */
final class EmailService
{
    /**
     * Confirma al paciente que su cita quedó registrada (pendiente de confirmación).
     *
     * No se envía si el paciente desactivó las notificaciones.
     *
     * @param array<string, mixed> $cita Detalle de la cita.
     */
    public static function enviarConfirmacionCita(array $cita): bool
    {
        if (empty($cita['recibir_notificaciones'])) {
            return false;
        }

        $mensaje = 'Hola ' . $cita['paciente_nombre'] . ",\n\n"
            . 'Tu cita con el Dr. ' . self::nombreDoctor($cita) . ' para el ' . fecha_corta((string) $cita['fecha_cita'])
            . ' a las ' . hora_corta((string) $cita['hora_inicio']) . " quedó registrada.\n\n"
            . 'Consultorio: ' . ($cita['consultorio_nombre'] ?? 'Por asignar') . "\n"
            . 'Motivo: ' . $cita['motivo_consulta'] . "\n\n"
            . "La cita está pendiente de confirmación. Te avisaremos por correo cuando sea confirmada.\n\n"
            . "Gracias por tu preferencia.\nMediAgenda";

        return Mailer::enviarCorreo((string) $cita['paciente_email'], 'Cita registrada - MediAgenda', $mensaje);
    }

    /**
     * Avisa al administrador que se agendó una cita nueva.
     *
     * Se envía al correo de la variable ADMIN_EMAIL; si no está configurada no se envía.
     *
     * @param array<string, mixed> $cita Detalle de la cita.
     */
    public static function notificarAdminNuevaCita(array $cita): bool
    {
        $correoAdmin = trim((string) Env::get('ADMIN_EMAIL', ''));
        if ($correoAdmin === '') {
            return false;
        }

        $mensaje = "Se agendó una nueva cita en el sistema:\n\n"
            . 'Cita: #' . $cita['id_cita'] . "\n"
            . 'Paciente: ' . $cita['paciente_nombre'] . ' (' . $cita['paciente_email'] . ")\n"
            . 'Médico: Dr. ' . self::nombreDoctor($cita) . "\n"
            . 'Consultorio: ' . ($cita['consultorio_nombre'] ?? 'Por asignar') . "\n"
            . 'Fecha: ' . fecha_corta((string) $cita['fecha_cita']) . "\n"
            . 'Hora: ' . hora_corta((string) $cita['hora_inicio']) . "\n"
            . 'Motivo: ' . $cita['motivo_consulta'];

        return Mailer::enviarCorreo($correoAdmin, 'Nueva cita agendada - MediAgenda', $mensaje);
    }

    /**
     * Informa al paciente que su cita fue confirmada o cancelada por el equipo.
     *
     * No se envía si el paciente desactivó las notificaciones.
     *
     * @param array<string, mixed> $cita   Detalle de la cita.
     * @param string               $estado 'Confirmada' o 'Cancelada'.
     */
    public static function notificarCambioEstado(array $cita, string $estado): bool
    {
        if (empty($cita['recibir_notificaciones'])) {
            return false;
        }

        $confirmada = $estado === 'Confirmada';
        $color = $confirmada ? '#1a56db' : '#dc3545';
        $textoEstado = $confirmada
            ? 'ha sido confirmada por nuestro equipo. ¡Te esperamos!'
            : 'ha sido cancelada por nuestro equipo.';

        $html = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
            <div style="background-color: ' . $color . '; padding: 20px; text-align: center; color: #ffffff;">
                <h2 style="margin: 0;">MediAgenda</h2>
            </div>
            <div style="padding: 25px; color: #1f2937; line-height: 1.6;">
                <p style="font-size: 16px;">Hola <strong>' . e($cita['paciente_nombre']) . '</strong>,</p>
                <p style="font-size: 15px;">Te informamos que tu cita médica #' . (int) $cita['id_cita'] . ' ' . $textoEstado . '</p>
                <div style="background-color: #f8f9fa; border-left: 4px solid ' . $color . '; padding: 15px; margin: 25px 0;">
                    <p style="margin: 6px 0; font-size: 14px;"><strong>Especialista:</strong> Dr. ' . e(self::nombreDoctor($cita)) . '</p>
                    <p style="margin: 6px 0; font-size: 14px;"><strong>Fecha:</strong> ' . fecha_corta((string) $cita['fecha_cita']) . '</p>
                    <p style="margin: 6px 0; font-size: 14px;"><strong>Hora:</strong> ' . hora_corta((string) $cita['hora_inicio']) . '</p>
                </div>
                <p style="color: #6b7280; font-size: 13px; text-align: center; margin-top: 30px; border-top: 1px solid #eeeeee; padding-top: 12px;">
                    Si tienes alguna duda, ponte en contacto con nosotros.
                </p>
            </div>
        </div>';

        return Mailer::enviarCorreo((string) $cita['paciente_email'], 'Actualización de tu cita - MediAgenda', $html, true);
    }

    /**
     * Envía el enlace para restablecer la contraseña olvidada.
     *
     * @param string $email  Correo del paciente.
     * @param string $enlace URL con el token de restablecimiento.
     */
    public static function enviarEnlaceRecuperacion(string $email, string $enlace): bool
    {
        $mensaje = "Hola,\n\nHaz clic en el siguiente enlace para restablecer tu contraseña:\n\n{$enlace}\n\n"
            . "El enlace vence en 1 hora.\n\nSi no solicitaste este cambio, ignora este mensaje.";

        return Mailer::enviarCorreo($email, 'Recuperación de contraseña - MediAgenda', $mensaje);
    }

    /**
     * Envía el enlace para confirmar un cambio de contraseña solicitado desde la cuenta.
     *
     * @param string $email  Correo del paciente.
     * @param string $enlace URL con el token de confirmación.
     */
    public static function enviarConfirmacionCambioPassword(string $email, string $enlace): bool
    {
        $mensaje = "Hola,\n\nSolicitaste cambiar tu contraseña en MediAgenda. Si fuiste tú, confírmalo aquí:\n\n{$enlace}\n\n"
            . "El enlace vence en 1 hora.\n\n"
            . 'Si no reconoces esta solicitud, ignora este correo: tu contraseña actual seguirá funcionando.';

        return Mailer::enviarCorreo($email, 'Confirma tu cambio de contraseña - MediAgenda', $mensaje);
    }

    /**
     * Nombre completo del médico de la cita.
     *
     * @param array<string, mixed> $cita Detalle de la cita.
     */
    private static function nombreDoctor(array $cita): string
    {
        return trim($cita['doctor_nombre'] . ' ' . $cita['doctor_apellido']);
    }
}
