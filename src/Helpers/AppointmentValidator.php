<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * VALIDADOR DE DATOS DE CITAS
 *
 * Concentra las reglas que debe cumplir una cita antes de guardarse (al crearla
 * o reprogramarla). Cada método devuelve el mensaje de error para el usuario, o
 * null si los datos son válidos.
 */
final class AppointmentValidator
{
    /** Longitud máxima del motivo de consulta, en caracteres. */
    public const MAX_MOTIVO = 1000;

    /** Motivo que se guarda cuando el paciente no escribe ninguno. */
    public const MOTIVO_POR_DEFECTO = 'Consulta general';

    /**
     * Valida los datos para crear una cita.
     *
     * @param array<string, mixed> $datos Debe incluir id_doctor, fecha_cita, hora_inicio y, opcionalmente, motivo.
     * @return string|null Mensaje de error o null si todo es válido.
     */
    public static function validarCreacion(array $datos): ?string
    {
        if (empty($datos['id_doctor']) || empty($datos['fecha_cita']) || empty($datos['hora_inicio'])) {
            return 'Faltan datos obligatorios (médico, fecha u hora)';
        }

        return self::validarHorario((string) $datos['fecha_cita'], (string) $datos['hora_inicio'])
            ?? self::validarMotivo((string) ($datos['motivo'] ?? ''));
    }

    /**
     * Valida la fecha y hora de una cita: formato, horario de atención, día
     * hábil y que no estén en el pasado.
     *
     * @param string $fecha Fecha en formato Y-m-d.
     * @param string $hora  Hora en formato HH:MM (se ignoran los segundos si vienen).
     * @return string|null Mensaje de error o null si es válida.
     */
    public static function validarHorario(string $fecha, string $hora): ?string
    {
        $hora = substr($hora, 0, 5);

        if (!ValidationHelper::validateDate($fecha)) {
            return 'Formato de fecha inválido';
        }

        if (!ValidationHelper::validateTime($hora)) {
            return 'Formato de hora inválido (use HH:MM)';
        }

        if (!Horario::esSlotValido($hora)) {
            return sprintf(
                'La hora debe estar entre %s y %s, en intervalos de %d minutos',
                Horario::PRIMERA_CITA,
                Horario::ULTIMA_CITA,
                Horario::INTERVALO_MINUTOS
            );
        }

        if (!Horario::esDiaHabil($fecha)) {
            return 'Las citas se agendan de lunes a sábado';
        }

        if (Horario::yaPaso($fecha, $hora)) {
            return 'No se puede agendar en una fecha u hora que ya pasó';
        }

        return null;
    }

    /**
     * Valida la longitud del motivo de consulta.
     *
     * @param string $motivo Texto escrito por el paciente.
     * @return string|null Mensaje de error o null si es válido.
     */
    public static function validarMotivo(string $motivo): ?string
    {
        if (ValidationHelper::excedeLongitud(trim($motivo), self::MAX_MOTIVO)) {
            return 'El motivo de consulta no puede exceder ' . self::MAX_MOTIVO . ' caracteres';
        }

        return null;
    }

    /**
     * Limpia el motivo y aplica el texto por defecto si viene vacío.
     *
     * @param mixed $motivo Valor recibido del cliente.
     */
    public static function normalizarMotivo(mixed $motivo): string
    {
        $motivo = trim((string) ($motivo ?? ''));

        return $motivo === '' ? self::MOTIVO_POR_DEFECTO : $motivo;
    }
}
