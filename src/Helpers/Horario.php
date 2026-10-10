<?php

declare(strict_types=1);

namespace App\Helpers;

use DateTimeImmutable;

/**
 * REGLAS DEL HORARIO DE CITAS
 *
 * Fuente única de verdad del horario de atención. Antes estas reglas estaban
 * copiadas en calendar.js, admin.php y Metrica.php. Ahora el servidor valida
 * con esta clase y las vistas reciben de aquí los horarios que deben mostrar.
 */
final class Horario
{
    /** Hora de la primera cita del día (HH:MM). */
    public const PRIMERA_CITA = '07:00';

    /** Hora de la última cita del día (HH:MM). */
    public const ULTIMA_CITA = '23:30';

    /** Duración de cada bloque de cita en minutos. */
    public const INTERVALO_MINUTOS = 30;

    /**
     * Días en que se agendan citas, numerados según date('N'): 1 = lunes ... 7 = domingo.
     * Los domingos se reservan para urgencias, como indica la página de contacto.
     */
    public const DIAS_HABILES = [1, 2, 3, 4, 5, 6];

    /**
     * Lista todos los horarios de inicio de cita de un día ("07:00", "07:30", ...).
     *
     * @return list<string>
     */
    public static function slots(): array
    {
        $slots = [];
        $actual = new DateTimeImmutable('today ' . self::PRIMERA_CITA);
        $ultima = new DateTimeImmutable('today ' . self::ULTIMA_CITA);

        while ($actual <= $ultima) {
            $slots[] = $actual->format('H:i');
            $actual = $actual->modify('+' . self::INTERVALO_MINUTOS . ' minutes');
        }

        return $slots;
    }

    /**
     * Cantidad de horarios disponibles por día y por médico.
     */
    public static function slotsPorDia(): int
    {
        return count(self::slots());
    }

    /**
     * Indica si una hora (HH:MM) coincide con uno de los horarios de cita.
     *
     * @param string $hora Hora en formato HH:MM.
     */
    public static function esSlotValido(string $hora): bool
    {
        return in_array($hora, self::slots(), true);
    }

    /**
     * Indica si en la fecha (Y-m-d) se agendan citas.
     *
     * @param string $fecha Fecha en formato Y-m-d.
     */
    public static function esDiaHabil(string $fecha): bool
    {
        $dia = (int) (new DateTimeImmutable($fecha))->format('N');

        return in_array($dia, self::DIAS_HABILES, true);
    }

    /**
     * Indica si la fecha y hora indicadas ya pasaron.
     *
     * @param string $fecha Fecha en formato Y-m-d.
     * @param string $hora  Hora en formato HH:MM.
     */
    public static function yaPaso(string $fecha, string $hora): bool
    {
        return new DateTimeImmutable($fecha . ' ' . $hora) <= new DateTimeImmutable();
    }
}
