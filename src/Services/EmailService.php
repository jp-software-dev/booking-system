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
     * @return bool|string
     */
    public static function enviarConfirmacionCita(string $destinatario, array $datosCita)
    {
        if (empty($destinatario)) {
            return "Destinatario no proporcionado";
        }
        
        $asunto = "Cita agendada - MediAgenda";
        $mensaje = "Hola " . ($datosCita['paciente_nombre'] ?? 'Paciente') . ",\n\n" .
                   "Tu cita con el Dr. " . ($datosCita['doctor_nombre'] ?? 'médico') . " para el día " . ($datosCita['fecha'] ?? 'fecha por definir') . " a las " . ($datosCita['hora'] ?? 'hora por definir') . " ha sido agendada exitosamente.\n\n" .
                   "Está pendiente de confirmación por el administrador. Te notificaremos cuando sea confirmada.\n\n" .
                   "Motivo: " . ($datosCita['motivo'] ?? 'No especificado') . "\n\n" .
                   "Gracias por preferirnos.\n\nMediAgenda";

        return Mailer::enviarCorreo($destinatario, $asunto, $mensaje, false);
    }

    /**
     * Envía notificación al administrador de nueva cita.
     *
     * @param array $datosCita Datos de la cita
     * @return bool|string
     */
    public static function notificarAdminNuevaCita(array $datosCita)
    {
        $adminEmail = $_ENV['SMTP_FROM'] ?? 'mediagenda.sistema@gmail.com';
        
        if (empty($adminEmail)) {
            return "Email de administrador no configurado";
        }
        
        $asunto = "Nueva cita agendada - MediAgenda";
        $mensaje = "Se ha agendado una nueva cita en el sistema:\n\n" .
                   "Paciente: " . ($datosCita['paciente_nombre'] ?? 'No especificado') . "\n" .
                   "Correo: " . ($datosCita['paciente_email'] ?? 'No especificado') . "\n" .
                   "Doctor: Dr. " . ($datosCita['doctor_nombre'] ?? 'No especificado') . "\n" .
                   "Fecha: " . ($datosCita['fecha'] ?? 'No especificada') . "\n" .
                   "Hora: " . ($datosCita['hora'] ?? 'No especificada') . "\n" .
                   "Motivo: " . ($datosCita['motivo'] ?? 'No especificado') . "\n\n" .
                   "Por favor, revise y confirme la cita en el panel administrativo.\n\nMediAgenda";

        return Mailer::enviarCorreo($adminEmail, $asunto, $mensaje, false);
    }
}
?>