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
     * @param string $ruta Ruta relativa dentro de public/assets (ej. "js/calendar.js").
     */
    function asset(string $ruta): string
    {
        $ruta = ltrim($ruta, '/');
        $archivo = __DIR__ . '/../public/assets/' . $ruta;
        $version = is_file($archivo) ? (string) filemtime($archivo) : '0';

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
            'Pendiente'  => 'bg-warning text-dark',
            'Confirmada' => 'bg-info text-dark',
            'En Curso'   => 'bg-primary text-white',
            'Completada' => 'bg-success text-white',
            'Cancelada'  => 'bg-danger text-white',
            default      => 'bg-secondary text-white',
        };
    }
}
