<?php
/**
 * VALIDADOR DE DATOS
 *
 * Clase estática con funciones reutilizables para validar formatos comunes
 * como fechas, horas y CURP mexicana, incluyendo el cálculo del dígito verificador.
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

    /**
     * Valida una CURP mexicana, incluyendo el dígito verificador.
     */
    public static function validateCURP(string $curp): bool
    {
        $curp = strtoupper(trim($curp));

        // Expresión regular estructural (primeros 17 caracteres)
        $pattern = '/^[A-Z]{4}[0-9]{6}[H,M][A-Z]{5}[A-Z0-9]{2}$/';
        if (!preg_match($pattern, $curp)) {
            return false;
        }

        // Mapeo de caracteres a números para el cálculo del dígito verificador
        $valores = [
            'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5, 'G' => 6,
            'H' => 7, 'I' => 8, 'J' => 9, 'K' => 10, 'L' => 11, 'M' => 12,
            'N' => 13, 'Ñ' => 14, 'O' => 15, 'P' => 16, 'Q' => 17, 'R' => 18,
            'S' => 19, 'T' => 20, 'U' => 21, 'V' => 22, 'W' => 23, 'X' => 24,
            'Y' => 25, 'Z' => 26, '0' => 0, '1' => 1, '2' => 2, '3' => 3,
            '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9
        ];

        $digitos = substr($curp, 0, 17);
        $suma = 0;
        for ($i = 0; $i < 17; $i++) {
            $caracter = $digitos[$i];
            $valor = $valores[$caracter] ?? 0;
            $factor = 18 - $i;
            $suma += $valor * $factor;
        }

        $residuo = $suma % 10;
        $digitoEsperado = $residuo == 0 ? 0 : 10 - $residuo;
        $digitoReal = intval(substr($curp, 17, 1));

        return $digitoEsperado === $digitoReal;
    }
}
?>