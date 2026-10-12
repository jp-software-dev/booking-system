<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Helpers\Horario;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

/**
 * MÉTRICAS DEL DASHBOARD ADMINISTRATIVO
 *
 * Un método por bloque de métricas del dashboard (api/get_totales.php).
 *
 * Compatibilidad: el SQL funciona en MariaDB 10.4+, MySQL y PostgreSQL. Por eso
 * no se usan funciones exclusivas de un motor (JSON_ARRAYAGG, DATE_FORMAT,
 * CURDATE); las fechas de referencia se calculan en PHP.
 *
 * Privacidad: ningún método expone datos identificables de pacientes (nombre,
 * motivo de consulta); solo conteos y agrupaciones.
 */
final class Metrica
{
    /** Rangos de fechas que acepta el dashboard. */
    public const RANGOS = ['7d', '15d', '30d', '6m', '12m', 'custom'];

    private PDO $db;

    /**
     * @param PDO|null $db Conexión a usar. Si es null se usa la conexión compartida.
     */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Convierte un rango predefinido o personalizado en fechas [desde, hasta].
     *
     * @param string      $rango Uno de Metrica::RANGOS.
     * @param string|null $desde Fecha inicial (Y-m-d), solo para 'custom'.
     * @param string|null $hasta Fecha final (Y-m-d), solo para 'custom'.
     * @return array{0: string, 1: string}
     * @throws InvalidArgumentException Si el rango o las fechas no son válidos.
     */
    public function resolverRango(string $rango, ?string $desde, ?string $hasta): array
    {
        $hoy = new DateTimeImmutable('today');

        $inicio = match ($rango) {
            '7d'    => $hoy->modify('-6 days'),
            '15d'   => $hoy->modify('-14 days'),
            '30d'   => $hoy->modify('-29 days'),
            '6m'    => $hoy->modify('-6 months'),
            '12m'   => $hoy->modify('-12 months'),
            'custom' => null,
            default => throw new InvalidArgumentException('Rango no reconocido'),
        };

        if ($inicio !== null) {
            return [$inicio->format('Y-m-d'), $hoy->format('Y-m-d')];
        }

        $d = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $desde);
        $h = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $hasta);

        if (!$d || $d->format('Y-m-d') !== $desde || !$h || $h->format('Y-m-d') !== $hasta) {
            throw new InvalidArgumentException('Fechas inválidas');
        }
        if ($d > $h) {
            throw new InvalidArgumentException('La fecha inicial no puede ser posterior a la final');
        }

        return [$desde, $hasta];
    }

    /**
     * Indicadores principales del rango: total, canceladas, completadas, citas de
     * hoy y tasa de cancelación (en porcentaje).
     *
     * @return array{total: int, canceladas: int, completadas: int, hoy: int, tasa_cancelacion: float}
     */
    public function kpis(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN estado_cita = 'Cancelada' THEN 1 ELSE 0 END) AS canceladas,
                    SUM(CASE WHEN estado_cita = 'Completada' THEN 1 ELSE 0 END) AS completadas,
                    SUM(CASE WHEN fecha_cita = ? THEN 1 ELSE 0 END) AS hoy
             FROM citas WHERE fecha_cita BETWEEN ? AND ?"
        );
        $stmt->execute([date('Y-m-d'), $desde, $hasta]);
        $fila = $stmt->fetch() ?: [];

        $total = (int) ($fila['total'] ?? 0);
        $canceladas = (int) ($fila['canceladas'] ?? 0);

        return [
            'total'            => $total,
            'canceladas'       => $canceladas,
            'completadas'      => (int) ($fila['completadas'] ?? 0),
            'hoy'              => (int) ($fila['hoy'] ?? 0),
            'tasa_cancelacion' => $total > 0 ? round($canceladas / $total * 100, 2) : 0.0,
        ];
    }

    /**
     * Cantidad de citas por estado dentro del rango.
     *
     * @return list<array{estado_cita: string, cantidad: int}>
     */
    public function porEstado(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            'SELECT estado_cita, COUNT(*) AS cantidad FROM citas
             WHERE fecha_cita BETWEEN ? AND ? GROUP BY estado_cita'
        );
        $stmt->execute([$desde, $hasta]);

        return $stmt->fetchAll();
    }

    /**
     * Cantidad de citas por especialidad dentro del rango, de mayor a menor.
     *
     * @return list<array{nombre_especialidad: string, cantidad: int}>
     */
    public function porEspecialidad(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare(
            'SELECT e.nombre_especialidad, COUNT(c.id_cita) AS cantidad
             FROM citas c
             JOIN doctores d ON c.id_doctor = d.id_doctor
             JOIN especialidades e ON d.id_especialidad = e.id_especialidad
             WHERE c.fecha_cita BETWEEN ? AND ?
             GROUP BY e.id_especialidad, e.nombre_especialidad
             ORDER BY cantidad DESC'
        );
        $stmt->execute([$desde, $hasta]);

        return $stmt->fetchAll();
    }

    /**
     * Médicos con más citas dentro del rango.
     *
     * @param int $limite Cantidad de médicos a devolver (entre 1 y 50).
     * @return list<array{nombre: string, apellido_paterno: string, cantidad: int}>
     */
    public function topDoctores(string $desde, string $hasta, int $limite = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT d.nombre, d.apellido_paterno, COUNT(c.id_cita) AS cantidad
             FROM citas c
             JOIN doctores d ON c.id_doctor = d.id_doctor
             WHERE c.fecha_cita BETWEEN ? AND ?
             GROUP BY d.id_doctor, d.nombre, d.apellido_paterno
             ORDER BY cantidad DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $desde);
        $stmt->bindValue(2, $hasta);
        $stmt->bindValue(3, max(1, min(50, $limite)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Citas por mes de los últimos 12 meses (independiente del rango elegido).
     *
     * @return list<array{periodo: string, cantidad: int}>
     */
    public function tendenciaMensual(): array
    {
        return $this->contarPorMes('citas', 'fecha_cita');
    }

    /**
     * Pacientes nuevos por mes de los últimos 12 meses (según pacientes.created_at).
     *
     * @return list<array{periodo: string, cantidad: int}>
     */
    public function pacientesNuevosPorMes(): array
    {
        return $this->contarPorMes('pacientes', 'created_at');
    }

    /**
     * Total de pacientes con cuenta activa (excluye cuentas eliminadas).
     */
    public function totalPacientesActivos(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM pacientes WHERE cuenta_eliminada = 0')->fetchColumn();
    }

    /**
     * Médicos activos que no tienen consultorio asignado (no pueden recibir citas).
     *
     * @return list<array{id_doctor: int, nombre: string, apellido_paterno: string}>
     */
    public function doctoresSinConsultorio(): array
    {
        return $this->db->query(
            "SELECT id_doctor, nombre, apellido_paterno FROM doctores
             WHERE estado = 'Activo' AND id_consultorio IS NULL
             ORDER BY nombre ASC"
        )->fetchAll();
    }

    /**
     * Tasa de ocupación por médico activo dentro del rango: citas confirmadas o
     * completadas entre los horarios disponibles del rango.
     *
     * No existe una tabla de horarios por médico, así que los horarios
     * disponibles se calculan con la misma rejilla que se usa para agendar
     * (Horario::slotsPorDia()) multiplicada por los días del rango.
     *
     * @param int $limite Cantidad de médicos a devolver (entre 1 y 50).
     * @return list<array<string, mixed>>
     */
    public function ocupacionPorDoctor(string $desde, string $hasta, int $limite = 10): array
    {
        $dias = (new DateTimeImmutable($desde))->diff(new DateTimeImmutable($hasta))->days + 1;
        $slotsDisponibles = max(1, $dias * Horario::slotsPorDia());

        $stmt = $this->db->prepare(
            "SELECT d.id_doctor, d.nombre, d.apellido_paterno,
                    SUM(CASE WHEN c.estado_cita IN ('Confirmada', 'Completada') THEN 1 ELSE 0 END) AS ocupadas
             FROM doctores d
             LEFT JOIN citas c ON c.id_doctor = d.id_doctor AND c.fecha_cita BETWEEN ? AND ?
             WHERE d.estado = 'Activo'
             GROUP BY d.id_doctor, d.nombre, d.apellido_paterno
             ORDER BY ocupadas DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $desde);
        $stmt->bindValue(2, $hasta);
        $stmt->bindValue(3, max(1, min(50, $limite)), PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static function (array $fila) use ($slotsDisponibles): array {
            // SUM() devuelve NULL para médicos sin citas en el rango.
            $ocupadas = (int) ($fila['ocupadas'] ?? 0);

            return array_merge($fila, [
                'ocupadas'          => $ocupadas,
                'slots_disponibles' => $slotsDisponibles,
                'tasa_ocupacion'    => round($ocupadas / $slotsDisponibles * 100, 2),
            ]);
        }, $stmt->fetchAll());
    }

    /**
     * Cuenta registros por mes durante los últimos 12 meses.
     *
     * Se agrupa con EXTRACT(YEAR/MONTH ...), que existe igual en MySQL y en
     * PostgreSQL (DATE_FORMAT es solo de MySQL), y el texto "AAAA-MM" se arma en PHP.
     *
     * @param string $tabla   Tabla a contar (valor fijo del código, nunca del usuario).
     * @param string $columna Columna de fecha (valor fijo del código, nunca del usuario).
     * @return list<array{periodo: string, cantidad: int}>
     */
    private function contarPorMes(string $tabla, string $columna): array
    {
        $stmt = $this->db->prepare(
            "SELECT EXTRACT(YEAR FROM {$columna}) AS anio, EXTRACT(MONTH FROM {$columna}) AS mes, COUNT(*) AS cantidad
             FROM {$tabla}
             WHERE {$columna} >= ?
             GROUP BY EXTRACT(YEAR FROM {$columna}), EXTRACT(MONTH FROM {$columna})
             ORDER BY anio ASC, mes ASC"
        );
        $stmt->execute([date('Y-m-d', (int) strtotime('-12 months'))]);

        return array_map(static fn (array $fila): array => [
            'periodo'  => sprintf('%04d-%02d', (int) $fila['anio'], (int) $fila['mes']),
            'cantidad' => (int) $fila['cantidad'],
        ], $stmt->fetchAll());
    }
}
