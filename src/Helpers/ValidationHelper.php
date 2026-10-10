<?php

declare(strict_types=1);

namespace App\Helpers;

use DateTime;

/**
 * VALIDACIONES DE FORMATO REUTILIZABLES
 *
 * Funciones estáticas para validar fechas, horas, correos, teléfonos y la
 * longitud de los textos que llegan del cliente.
 */
final class ValidationHelper
{
    /**
     * Valida que una fecha exista y tenga exactamente el formato indicado.
     *
     * Rechaza fechas imposibles como "2026-02-31" (PHP las desbordaría al 3 de marzo).
     *
     * @param string $fecha   Fecha a validar.
     * @param string $formato Formato esperado (por defecto Y-m-d).
     */
    public static function validateDate(string $fecha, string $formato = 'Y-m-d'): bool
    {
        $d = DateTime::createFromFormat($formato, $fecha);

        return $d !== false && $d->format($formato) === $fecha;
    }

    /**
     * Valida que una hora tenga exactamente el formato indicado (por defecto HH:MM, 24 h).
     *
     * @param string $hora    Hora a validar.
     * @param string $formato Formato esperado.
     */
    public static function validateTime(string $hora, string $formato = 'H:i'): bool
    {
        $t = DateTime::createFromFormat($formato, $hora);

        return $t !== false && $t->format($formato) === $hora;
    }

    /**
     * Valida el formato de un correo electrónico.
     *
     * @param string $email Correo a validar.
     */
    public static function validateEmail(string $email): bool
    {
        return strlen($email) <= 100 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Valida un teléfono de exactamente 10 dígitos.
     *
     * @param string $telefono Teléfono a validar.
     */
    public static function validatePhone(string $telefono): bool
    {
        return preg_match('/^[0-9]{10}$/', $telefono) === 1;
    }

    /**
     * Indica si un texto supera la longitud máxima permitida (en caracteres, no en bytes).
     *
     * @param string $texto  Texto a medir.
     * @param int    $maximo Longitud máxima.
     */
    public static function excedeLongitud(string $texto, int $maximo): bool
    {
        return mb_strlen($texto, 'UTF-8') > $maximo;
    }
}
