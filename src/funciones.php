<?php

declare(strict_types=1);

/**
 * FUNCIONES GLOBALES DE APOYO PARA LAS VISTAS
 *
 * Composer carga este archivo automáticamente (sección "files" de composer.json),
 * así que las funciones están disponibles en cualquier plantilla sin require.
 */

if (!function_exists('e')) {
    /**
     * Escapa un valor para imprimirlo de forma segura dentro de HTML o de un atributo.
     *
     * @param mixed $valor Texto, número o null.
     */
    function e(mixed $valor): string
    {
        return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('asset')) {
    /**
     * Devuelve la ruta de un archivo de public/assets con un parámetro de versión
     * basado en su fecha de modificación. Así el navegador guarda el archivo en
     * caché y solo descarga uno nuevo cuando cambia.
     *
     * En Vercel todos los archivos desplegados tienen la misma fecha de
     * modificación, así que la versión es el commit del despliegue.
     *
     * @param string $ruta Ruta relativa dentro de public/assets (ej. "js/calendar.js").
     */
    function asset(string $ruta): string
    {
        $ruta = ltrim($ruta, '/');
        $archivo = __DIR__ . '/../public/assets/' . $ruta;
        $commit = (string) getenv('VERCEL_GIT_COMMIT_SHA');

        if ($commit !== '') {
            $version = substr($commit, 0, 12);
        } else {
            $version = is_file($archivo) ? (string) filemtime($archivo) : '0';
        }

        return 'assets/' . $ruta . '?v=' . $version;
    }
}

if (!function_exists('fecha_corta')) {
    /**
     * Formatea una fecha de la base de datos como dd/mm/aaaa.
     *
     * @param string $fecha Fecha en un formato que entienda strtotime() (ej. "2026-10-09").
     */
    function fecha_corta(string $fecha): string
    {
        return date('d/m/Y', (int) strtotime($fecha));
    }
}

if (!function_exists('hora_corta')) {
    /**
     * Formatea una hora de la base de datos en formato de 12 horas (ej. "09:30 AM").
     *
     * @param string $hora Hora en formato HH:MM o HH:MM:SS.
     */
    function hora_corta(string $hora): string
    {
        return date('h:i A', (int) strtotime($hora));
    }
}

if (!function_exists('clase_estado_cita')) {
    /**
     * Devuelve las clases de Bootstrap del distintivo (badge) de cada estado de cita,
     * para que todas las pantallas usen los mismos colores.
     *
     * @param string $estado Pendiente, Confirmada, En Curso, Completada o Cancelada.
     */
    function clase_estado_cita(string $estado): string
    {
        return match ($estado) {
            'Pendiente'  => 'bg-warning-subtle text-warning-emphasis',
            'Confirmada' => 'bg-primary-subtle text-primary-emphasis',
            'En Curso'   => 'bg-primary text-white',
            'Completada' => 'bg-success-subtle text-success-emphasis',
            'Cancelada'  => 'bg-danger-subtle text-danger-emphasis',
            default      => 'bg-light text-secondary',
        };
    }
}

if (!function_exists('fecha_larga')) {
    /**
     * Fecha en español, legible (ej. "martes 3 de noviembre").
     *
     * @param string $fecha       Fecha que entienda strtotime().
     * @param bool   $conAnio     true para agregar el año ("... de 2026").
     */
    function fecha_larga(string $fecha, bool $conAnio = false): string
    {
        $marca = (int) strtotime($fecha);
        $texto = DIAS_SEMANA[(int) date('N', $marca)] . ' ' . date('j', $marca) . ' de ' . MESES[(int) date('n', $marca)];

        return $conAnio ? $texto . ' de ' . date('Y', $marca) : $texto;
    }
}

if (!function_exists('dia_corto')) {
    /**
     * Día de la semana abreviado en español (ej. "mar").
     */
    function dia_corto(string $fecha): string
    {
        return mb_substr(DIAS_SEMANA[(int) date('N', (int) strtotime($fecha))], 0, 3);
    }
}

if (!function_exists('mes_corto')) {
    /**
     * Mes abreviado en español (ej. "nov").
     */
    function mes_corto(string $fecha): string
    {
        return mb_substr(MESES[(int) date('n', (int) strtotime($fecha))], 0, 3);
    }
}

if (!function_exists('edad')) {
    /**
     * Edad cumplida a partir de la fecha de nacimiento, o null si no hay fecha.
     */
    function edad(?string $fechaNacimiento): ?int
    {
        if ($fechaNacimiento === null || $fechaNacimiento === '') {
            return null;
        }

        return (new DateTimeImmutable($fechaNacimiento))->diff(new DateTimeImmutable('today'))->y;
    }
}

/** Nombres de los días según date('N'): 1 = lunes ... 7 = domingo. */
if (!defined('DIAS_SEMANA')) {
    define('DIAS_SEMANA', [1 => 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo']);
}

/** Nombres de los meses según date('n'). */
if (!defined('MESES')) {
    define('MESES', [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']);
}
