<?php
/**
 * VALIDADOR DE DATOS
 *
 * Clase estática con funciones reutilizables para validar formatos comunes
 * como fechas y horas.
 *
 * @namespace Helpers
 */

namespace Helpers;

class ValidationHelper
{
    /**
     * Valida una fecha en formato Y-m-d (por defecto)
     */
    public static function validateDate(string $date, string $format = 'Y-m-d'): bool
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * Valida una hora en formato H:i (por defecto)
     */
    public static function validateTime(string $time, string $format = 'H:i'): bool
    {
        $t = \DateTime::createFromFormat($format, $time);
        return $t && $t->format($format) === $time;
    }
}
?>