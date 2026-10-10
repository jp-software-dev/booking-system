<?php

declare(strict_types=1);

namespace App\Core;

/**
 * CARGADOR DE VARIABLES DE ENTORNO
 *
 * Lee el archivo .env de la raíz del proyecto y expone sus valores con Env::get().
 * El formato soportado es el habitual: una variable por línea (CLAVE=valor),
 * comentarios con "#" y valores opcionalmente entre comillas simples o dobles.
 *
 * Las variables definidas en el entorno real del servidor (SetEnv de Apache,
 * variables del sistema) tienen prioridad sobre las del archivo, de modo que
 * producción puede configurarse sin tocar el .env.
 */
final class Env
{
    /**
     * Valores leídos del archivo .env.
     *
     * @var array<string, string>
     */
    private static array $valores = [];

    /**
     * Carga las variables del archivo indicado.
     *
     * Si el archivo no existe no hace nada: la aplicación usará los valores por
     * defecto de cada llamada a Env::get().
     *
     * @param string $ruta Ruta absoluta al archivo .env.
     */
    public static function cargar(string $ruta): void
    {
        if (!is_file($ruta) || !is_readable($ruta)) {
            return;
        }

        $lineas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lineas as $linea) {
            $linea = trim($linea);

            // Se ignoran comentarios y líneas que no tienen la forma CLAVE=valor.
            if ($linea === '' || str_starts_with($linea, '#') || !str_contains($linea, '=')) {
                continue;
            }

            [$clave, $valor] = explode('=', $linea, 2);
            $clave = trim($clave);

            if ($clave !== '') {
                self::$valores[$clave] = self::limpiarValor($valor);
            }
        }
    }

    /**
     * Devuelve el valor de una variable de entorno.
     *
     * Orden de búsqueda: entorno real del servidor, archivo .env y, por último,
     * el valor por defecto recibido.
     *
     * @param string      $clave      Nombre de la variable (por ejemplo, "DB_HOST").
     * @param string|null $porDefecto Valor a devolver si la variable no está definida.
     */
    public static function get(string $clave, ?string $porDefecto = null): ?string
    {
        $delServidor = getenv($clave);
        if ($delServidor !== false) {
            return $delServidor;
        }

        return self::$valores[$clave] ?? $porDefecto;
    }

    /**
     * Quita los espacios de los extremos y, si el valor viene entre comillas del
     * mismo tipo, también las comillas.
     *
     * @param string $valor Texto crudo que aparece después del "=".
     */
    private static function limpiarValor(string $valor): string
    {
        $valor = trim($valor);
        $largo = strlen($valor);

        if ($largo >= 2) {
            $primera = $valor[0];
            if (($primera === '"' || $primera === "'") && $valor[$largo - 1] === $primera) {
                return substr($valor, 1, -1);
            }
        }

        return $valor;
    }
}
