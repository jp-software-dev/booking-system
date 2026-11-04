<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * MODELO DE TRATAMIENTOS Y SUS TOMAS (RECORDATORIOS DE MEDICAMENTOS)
 *
 * Un tratamiento es un medicamento con su dosis, cada cuántas horas se toma y
 * durante cuántos días. Lo crea el médico al emitir una receta (origen
 * 'doctor') o el propio paciente (origen 'paciente').
 *
 * Al crearlo se calculan y guardan todas sus tomas con la hora exacta
 * (tabla `tomas`): la primera a la hora de inicio y después una cada
 * `frecuencia_horas` mientras no se cumpla la duración. Ejemplo: cada 8 horas
 * durante 7 días, empezando el lunes a las 08:00 = 21 tomas (08:00, 16:00 y
 * 00:00 de cada día). Guardarlas permite marcar cada toma como tomada u
 * omitida y que el envío de notificaciones sea una consulta simple.
 *
 * Todas las fechas van en la hora local de la aplicación (America/Mexico_City).
 */
final class Tratamiento
{
    public const ORIGEN_DOCTOR = 'doctor';
    public const ORIGEN_PACIENTE = 'paciente';

    public const ACTIVO = 'Activo';
    public const PAUSADO = 'Pausado';
    public const CANCELADO = 'Cancelado';

    public const TOMA_PENDIENTE = 'Pendiente';
    public const TOMA_TOMADA = 'Tomada';
    public const TOMA_OMITIDA = 'Omitida';

    /** Frecuencias permitidas, en horas (cada 1 h ... cada 24 h). */
    public const FRECUENCIAS = [1, 2, 3, 4, 6, 8, 12, 24];

    /** Duración máxima de un tratamiento, en días. */
    public const DURACION_MAXIMA_DIAS = 30;

    /** Una toma se puede marcar como tomada hasta este tiempo antes de su hora. */
    public const MARGEN_ANTICIPADO_MINUTOS = 60;

    private PDO $db;

    /**
     * @param PDO|null $db Conexión a usar. Si es null se usa la conexión compartida.
     */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    // === CÁLCULO DEL CRONOGRAMA ===

    /**
     * Calcula las horas exactas de cada toma.
     *
     * @param DateTimeImmutable $inicio          Fecha y hora de la primera toma.
     * @param int               $frecuenciaHoras Horas entre una toma y la siguiente.
     * @param int               $duracionDias    Días que dura el tratamiento.
     * @return list<string> Fechas "Y-m-d H:i:s" en orden.
     */
    public static function calcularTomas(DateTimeImmutable $inicio, int $frecuenciaHoras, int $duracionDias): array
    {
        if (!in_array($frecuenciaHoras, self::FRECUENCIAS, true)) {
            throw new InvalidArgumentException('Frecuencia no permitida');
        }
        if ($duracionDias < 1 || $duracionDias > self::DURACION_MAXIMA_DIAS) {
            throw new InvalidArgumentException('Duración fuera de rango');
        }

        $fin = self::calcularFin($inicio, $duracionDias);
        $paso = new DateInterval('PT' . $frecuenciaHoras . 'H');
        $tomas = [];

        for ($toma = $inicio; $toma < $fin; $toma = $toma->add($paso)) {
            $tomas[] = $toma->format('Y-m-d H:i:s');
        }

        return $tomas;
    }

    /**
     * Momento en que termina el tratamiento (exclusivo): inicio + duración en días.
     */
    public static function calcularFin(DateTimeImmutable $inicio, int $duracionDias): DateTimeImmutable
    {
        return $inicio->add(new DateInterval('P' . $duracionDias . 'D'));
    }

    /**
     * Momento del día de una toma, que da el color de su "tapa" en el pastillero:
     * mañana (05:00-11:59), tarde (12:00-18:59) o noche (19:00-04:59).
     *
     * @param string $fechaHora Fecha "Y-m-d H:i:s".
     * @return 'manana'|'tarde'|'noche'
     */
    public static function momentoDelDia(string $fechaHora): string
    {
        $hora = (int) substr($fechaHora, 11, 2);

        return match (true) {
            $hora >= 5 && $hora < 12  => 'manana',
            $hora >= 12 && $hora < 19 => 'tarde',
            default                   => 'noche',
        };
    }

    /**
     * Texto legible de una frecuencia ("cada 8 horas", "una vez al día").
     */
    public static function describirFrecuencia(int $horas): string
    {
        return match ($horas) {
            24      => 'una vez al día',
            1       => 'cada hora',
            default => "cada {$horas} horas",
        };
    }

    // === ALTA ===

    /**
     * Crea un tratamiento con todas sus tomas.
     *
     * Si ya hay una transacción abierta (por ejemplo, al emitir una receta con
     * varios medicamentos) se usa esa; si no, se abre una propia.
     *
     * @param array{
     *     id_paciente: int, id_receta: int|null, origen: string, medicamento: string,
     *     dosis: string, frecuencia_horas: int, duracion_dias: int,
     *     fecha_inicio: DateTimeImmutable, notas: string|null
     * } $datos
     * @return int id_tratamiento creado.
     */
    public function crear(array $datos): int
    {
        $tomas = self::calcularTomas($datos['fecha_inicio'], $datos['frecuencia_horas'], $datos['duracion_dias']);
        $fin = self::calcularFin($datos['fecha_inicio'], $datos['duracion_dias']);
        $transaccionPropia = !$this->db->inTransaction();

        if ($transaccionPropia) {
            $this->db->beginTransaction();
        }

        try {
            $idTratamiento = Database::insertar(
                $this->db,
                'INSERT INTO tratamientos (id_paciente, id_receta, origen, medicamento, dosis, frecuencia_horas,
                                           duracion_dias, fecha_inicio, fecha_fin, notas, estado, creado_en)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $datos['id_paciente'],
                    $datos['id_receta'],
                    $datos['origen'],
                    $datos['medicamento'],
                    $datos['dosis'],
                    $datos['frecuencia_horas'],
                    $datos['duracion_dias'],
                    $datos['fecha_inicio']->format('Y-m-d H:i:s'),
                    $fin->format('Y-m-d H:i:s'),
                    $datos['notas'],
                    self::ACTIVO,
                    date('Y-m-d H:i:s'),
                ],
                'id_tratamiento'
            );

            // Un INSERT por lote de 200 tomas (VALUES (...), (...)), válido en ambos motores.
            foreach (array_chunk($tomas, 200) as $lote) {
                $marcadores = implode(', ', array_fill(0, count($lote), '(?, ?)'));
                $valores = [];
                foreach ($lote as $hora) {
                    array_push($valores, $idTratamiento, $hora);
                }
                $this->db->prepare("INSERT INTO tomas (id_tratamiento, programada_en) VALUES {$marcadores}")->execute($valores);
            }

            if ($transaccionPropia) {
                $this->db->commit();
            }

            return $idTratamiento;
        } catch (Throwable $e) {
            if ($transaccionPropia && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    // === CONSULTAS ===

    /**
     * Tratamientos de un paciente con su avance (tomas totales, tomadas y omitidas).
     * Excluye los cancelados.
     *
     * @return list<array<string, mixed>>
     */
    public function listarPorPaciente(int $idPaciente): array
    {
        $stmt = $this->db->prepare(
            "SELECT t.id_tratamiento, t.id_receta, t.origen, t.medicamento, t.dosis, t.frecuencia_horas,
                    t.duracion_dias, t.fecha_inicio, t.fecha_fin, t.notas, t.estado,
                    d.nombre AS doctor_nombre, d.apellido_paterno AS doctor_apellido,
                    COUNT(tm.id_toma) AS total_tomas,
                    SUM(CASE WHEN tm.estado = 'Tomada' THEN 1 ELSE 0 END) AS tomadas,
                    SUM(CASE WHEN tm.estado = 'Omitida' THEN 1 ELSE 0 END) AS omitidas
             FROM tratamientos t
             LEFT JOIN recetas r ON r.id_receta = t.id_receta
             LEFT JOIN doctores d ON d.id_doctor = r.id_doctor
             LEFT JOIN tomas tm ON tm.id_tratamiento = t.id_tratamiento
             WHERE t.id_paciente = ? AND t.estado <> 'Cancelado'
             GROUP BY t.id_tratamiento, t.id_receta, t.origen, t.medicamento, t.dosis, t.frecuencia_horas,
                      t.duracion_dias, t.fecha_inicio, t.fecha_fin, t.notas, t.estado, d.nombre, d.apellido_paterno
             ORDER BY t.fecha_fin DESC, t.id_tratamiento DESC"
        );
        $stmt->execute([$idPaciente]);

        return array_map(self::normalizarAvance(...), $stmt->fetchAll());
    }

    /**
     * Tratamientos de una receta con su avance.
     *
     * @return list<array<string, mixed>>
     */
    public function listarPorReceta(int $idReceta): array
    {
        $stmt = $this->db->prepare(
            "SELECT t.id_tratamiento, t.medicamento, t.dosis, t.frecuencia_horas, t.duracion_dias,
                    t.fecha_inicio, t.fecha_fin, t.notas, t.estado,
                    COUNT(tm.id_toma) AS total_tomas,
                    SUM(CASE WHEN tm.estado = 'Tomada' THEN 1 ELSE 0 END) AS tomadas,
                    SUM(CASE WHEN tm.estado = 'Omitida' THEN 1 ELSE 0 END) AS omitidas
             FROM tratamientos t
             LEFT JOIN tomas tm ON tm.id_tratamiento = t.id_tratamiento
             WHERE t.id_receta = ?
             GROUP BY t.id_tratamiento, t.medicamento, t.dosis, t.frecuencia_horas, t.duracion_dias,
                      t.fecha_inicio, t.fecha_fin, t.notas, t.estado
             ORDER BY t.id_tratamiento ASC"
        );
        $stmt->execute([$idReceta]);

        return array_map(self::normalizarAvance(...), $stmt->fetchAll());
    }

    /**
     * Devuelve el tratamiento si pertenece al paciente, o null.
     *
     * @return array<string, mixed>|null
     */
    public function obtenerDePaciente(int $idTratamiento, int $idPaciente): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tratamientos WHERE id_tratamiento = ? AND id_paciente = ?');
        $stmt->execute([$idTratamiento, $idPaciente]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    /**
     * Tomas de un paciente entre dos fechas (inclusive), de tratamientos no cancelados.
     *
     * @param string $desde Fecha y hora "Y-m-d H:i:s".
     * @param string $hasta Fecha y hora "Y-m-d H:i:s".
     * @return list<array<string, mixed>>
     */
    public function tomasEntre(int $idPaciente, string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            "SELECT tm.id_toma, tm.programada_en, tm.estado, tm.registrada_en,
                    t.id_tratamiento, t.medicamento, t.dosis, t.estado AS estado_tratamiento
             FROM tomas tm
             INNER JOIN tratamientos t ON t.id_tratamiento = tm.id_tratamiento
             WHERE t.id_paciente = ? AND t.estado <> 'Cancelado'
               AND tm.programada_en >= ? AND tm.programada_en <= ?
             ORDER BY tm.programada_en ASC, t.medicamento ASC"
        );
        $stmt->execute([$idPaciente, $desde, $hasta]);

        return array_map(static function (array $toma): array {
            $toma['id_toma'] = (int) $toma['id_toma'];
            $toma['id_tratamiento'] = (int) $toma['id_tratamiento'];
            $toma['momento'] = self::momentoDelDia((string) $toma['programada_en']);

            return $toma;
        }, $stmt->fetchAll());
    }

    // === CAMBIOS ===

    /**
     * Pausa, reanuda o cancela un tratamiento.
     */
    public function cambiarEstado(int $idTratamiento, string $estado): void
    {
        if (!in_array($estado, [self::ACTIVO, self::PAUSADO, self::CANCELADO], true)) {
            throw new InvalidArgumentException('Estado de tratamiento inválido');
        }

        $this->db->prepare('UPDATE tratamientos SET estado = ? WHERE id_tratamiento = ?')->execute([$estado, $idTratamiento]);
    }

    /**
     * Registra una toma como tomada u omitida, o la regresa a pendiente.
     *
     * @return string|null Mensaje de error, o null si se registró.
     */
    public function registrarToma(int $idToma, int $idPaciente, string $estado): ?string
    {
        if (!in_array($estado, [self::TOMA_PENDIENTE, self::TOMA_TOMADA, self::TOMA_OMITIDA], true)) {
            return 'Estado de toma inválido';
        }

        $stmt = $this->db->prepare(
            'SELECT tm.programada_en, t.estado AS estado_tratamiento
             FROM tomas tm INNER JOIN tratamientos t ON t.id_tratamiento = tm.id_tratamiento
             WHERE tm.id_toma = ? AND t.id_paciente = ?'
        );
        $stmt->execute([$idToma, $idPaciente]);
        $toma = $stmt->fetch();

        if (!$toma) {
            return 'Toma no encontrada';
        }
        if ($toma['estado_tratamiento'] === self::CANCELADO) {
            return 'El tratamiento está cancelado';
        }

        $limite = (new DateTimeImmutable())->modify('+' . self::MARGEN_ANTICIPADO_MINUTOS . ' minutes')->format('Y-m-d H:i:s');
        if ($estado !== self::TOMA_PENDIENTE && (string) $toma['programada_en'] > $limite) {
            return 'Todavía no es hora de esta toma';
        }

        $this->db->prepare('UPDATE tomas SET estado = ?, registrada_en = ? WHERE id_toma = ?')->execute([
            $estado,
            $estado === self::TOMA_PENDIENTE ? null : date('Y-m-d H:i:s'),
            $idToma,
        ]);

        return null;
    }

    // === NOTIFICACIONES ===

    /**
     * Tomas que ya llegaron a su hora y todavía no se avisaron, de tratamientos
     * activos. Solo dentro de la ventana indicada: si el envío estuvo detenido
     * un tiempo, no se dispara un aluvión de avisos atrasados.
     *
     * @param int $ventanaMinutos Antigüedad máxima de una toma para avisarla.
     * @return list<array<string, mixed>>
     */
    public function pendientesDeAviso(int $ventanaMinutos = 15): array
    {
        $ahora = new DateTimeImmutable();
        $stmt = $this->db->prepare(
            "SELECT tm.id_toma, tm.programada_en, t.id_paciente, t.medicamento, t.dosis
             FROM tomas tm
             INNER JOIN tratamientos t ON t.id_tratamiento = tm.id_tratamiento
             WHERE t.estado = 'Activo' AND tm.estado = 'Pendiente' AND tm.notificada_en IS NULL
               AND tm.programada_en <= ? AND tm.programada_en >= ?
             ORDER BY tm.programada_en ASC"
        );
        $stmt->execute([
            $ahora->format('Y-m-d H:i:s'),
            $ahora->modify("-{$ventanaMinutos} minutes")->format('Y-m-d H:i:s'),
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Marca la toma como avisada (aunque el paciente no tenga navegadores suscritos).
     */
    public function marcarNotificada(int $idToma): void
    {
        $this->db->prepare('UPDATE tomas SET notificada_en = ? WHERE id_toma = ?')->execute([date('Y-m-d H:i:s'), $idToma]);
    }

    /**
     * Convierte los conteos a enteros y agrega el porcentaje de apego.
     *
     * @param array<string, mixed> $fila
     * @return array<string, mixed>
     */
    private static function normalizarAvance(array $fila): array
    {
        foreach (['id_tratamiento', 'frecuencia_horas', 'duracion_dias', 'total_tomas', 'tomadas', 'omitidas'] as $campo) {
            if (array_key_exists($campo, $fila)) {
                $fila[$campo] = (int) $fila[$campo];
            }
        }
        $registradas = $fila['tomadas'] + $fila['omitidas'];
        $fila['apego'] = $registradas > 0 ? (int) round($fila['tomadas'] * 100 / $registradas) : null;

        return $fila;
    }
}
