<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Tratamiento;
use DateTimeImmutable;

/**
 * VALIDACIÓN DE UN MEDICAMENTO (TRATAMIENTO) RECIBIDO DESDE LA INTERFAZ
 *
 * La usan api/emitir_receta.php (cada medicamento de la receta) y
 * api/crear_tratamiento.php (recordatorio del propio paciente), para que las
 * reglas sean las mismas en los dos caminos.
 */
final class ValidadorTratamiento
{
    /** Inicio más antiguo permitido: se puede registrar un tratamiento que empezó ayer. */
    private const MAX_HORAS_ATRAS = 24;

    /** Inicio más lejano permitido. */
    private const MAX_DIAS_ADELANTE = 60;

    /**
     * Valida y normaliza los datos de un medicamento.
     *
     * @param array<string, mixed> $entrada Campos: medicamento, dosis, frecuencia_horas,
     *                                      duracion_dias, fecha_inicio (AAAA-MM-DDTHH:MM) y notas.
     * @return array{0: array{medicamento: string, dosis: string, frecuencia_horas: int, duracion_dias: int,
     *                        fecha_inicio: DateTimeImmutable, notas: string|null}|null, 1: string|null}
     *         [datos, null] si es válido o [null, mensaje de error].
     */
    public static function validar(array $entrada): array
    {
        $medicamento = trim((string) ($entrada['medicamento'] ?? ''));
        $dosis = trim((string) ($entrada['dosis'] ?? ''));
        $notas = trim((string) ($entrada['notas'] ?? ''));
        $frecuencia = filter_var($entrada['frecuencia_horas'] ?? null, FILTER_VALIDATE_INT);
        $duracion = filter_var($entrada['duracion_dias'] ?? null, FILTER_VALIDATE_INT);
        $textoInicio = str_replace('T', ' ', trim((string) ($entrada['fecha_inicio'] ?? '')));

        if (mb_strlen($medicamento) < 2 || mb_strlen($medicamento) > 120) {
            return [null, 'Escribe el nombre del medicamento (2 a 120 caracteres)'];
        }
        if ($dosis === '' || mb_strlen($dosis) > 120) {
            return [null, "Indica la dosis de {$medicamento} (por ejemplo, 1 tableta de 500 mg)"];
        }
        if ($frecuencia === false || !in_array($frecuencia, Tratamiento::FRECUENCIAS, true)) {
            return [null, "Elige cada cuántas horas se toma {$medicamento}"];
        }
        if ($duracion === false || $duracion < 1 || $duracion > Tratamiento::DURACION_MAXIMA_DIAS) {
            return [null, 'La duración debe ser de 1 a ' . Tratamiento::DURACION_MAXIMA_DIAS . ' días'];
        }
        if (mb_strlen($notas) > 255) {
            return [null, 'Las notas no pueden pasar de 255 caracteres'];
        }

        $inicio = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $textoInicio);
        if ($inicio === false || $inicio->format('Y-m-d H:i') !== $textoInicio) {
            return [null, "Indica la fecha y hora de la primera toma de {$medicamento}"];
        }

        $ahora = new DateTimeImmutable();
        if ($inicio < $ahora->modify('-' . self::MAX_HORAS_ATRAS . ' hours')) {
            return [null, 'La primera toma no puede ser de hace más de 24 horas'];
        }
        if ($inicio > $ahora->modify('+' . self::MAX_DIAS_ADELANTE . ' days')) {
            return [null, 'La primera toma no puede ser dentro de más de ' . self::MAX_DIAS_ADELANTE . ' días'];
        }

        return [[
            'medicamento'      => $medicamento,
            'dosis'            => $dosis,
            'frecuencia_horas' => $frecuencia,
            'duracion_dias'    => $duracion,
            'fecha_inicio'     => $inicio,
            'notas'            => $notas !== '' ? $notas : null,
        ], null];
    }
}
