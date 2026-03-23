<?php
namespace Services;

use Helpers\Mailer;

class EmailService
{
    /**
     * Envía correo de confirmación al paciente.
     *
     * @param string $destinatario Correo del paciente
     * @param array  $datosCita    Datos de la cita (paciente_nombre, doctor_nombre, fecha, hora, motivo)
     * @return bool
     */
    public static function enviarConfirmacionCita(string $destinatario, array $datosCita): bool
    {
        $asunto = "Cita agendada - Pendiente de confirmación";
        $mensaje = "Hola {$datosCita['paciente_nombre']},\n\n" .
                   "Tu cita con el Dr. {$datosCita['doctor_nombre']} para el día {$datosCita['fecha']} a las {$datosCita['hora']} ha sido agendada exitosamente.\n\n" .
                   "Está pendiente de confirmación. Te notificaremos pronto.";

        return Mailer::enviarCorreo($destinatario, $asunto, $mensaje, false);
    }

    /**
     * Envía notificación al administrador de nueva cita.
     *
     * @param array $datosCita Datos de la cita (paciente_nombre, paciente_email, doctor_nombre, fecha, hora, motivo)
     * @return bool
     */
    public static function notificarAdminNuevaCita(array $datosCita): bool
    {
        $adminEmail = $_ENV['SMTP_FROM'] ?? 'mediagenda.sistema@gmail.com';
        $asunto = "Nueva cita agendada";
        $mensaje = "Se ha agendado una nueva cita:\n" .
                   "Paciente: {$datosCita['paciente_nombre']}\n" .
                   "Correo: {$datosCita['paciente_email']}\n" .
                   "Doctor: Dr. {$datosCita['doctor_nombre']}\n" .
                   "Fecha: {$datosCita['fecha']}\n" .
                   "Hora: {$datosCita['hora']}\n" .
                   "Motivo: {$datosCita['motivo']}";

        return Mailer::enviarCorreo($adminEmail, $asunto, $mensaje, false);
    }
}