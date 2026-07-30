<?php
/**
 * MODELO DE MÉTRICAS DEL DASHBOARD ADMINISTRATIVO
 *
 * Reemplaza la consulta monolítica de get_totales.php (que dependía de
 * JSON_ARRAYAGG/JSON_OBJECT) por un método por bloque de métricas. Se evitan
 * esas funciones a propósito: el entorno corre MariaDB 10.4.32, y
 * JSON_ARRAYAGG requiere MariaDB 10.5+ / MySQL 5.7.22+ — con la consulta
 * anterior, este endpoint fallaba en este entorno.
 *
 * Ningún método expone datos identificables de pacientes (nombre, CURP,
 * motivo de consulta): solo conteos y agrupaciones por estado, especialidad,
 * doctor y periodo.
 *
 * @declare(strict_types=1)
 */

declare(strict_types=1);

class Metrica
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    /**
     * Valida y resuelve un rango de fechas a partir de un preset o fechas personalizadas.
     * @throws InvalidArgumentException si el rango es inválido.
     */
    public function resolverRango(string $rango, ?string $desde, ?string $hasta): array
    {
        switch ($rango) {
            case '7d':
                return [date('Y-m-d', strtotime('-6 days')), date('Y-m-d')];
            case '15d':
                return [date('Y-m-d', strtotime('-14 days')), date('Y-m-d')];
            case '30d':
                return [date('Y-m-d', strtotime('-29 days')), date('Y-m-d')];
            case '6m':
                return [date('Y-m-d', strtotime('-6 months')), date('Y-m-d')];
            case '12m':
                return [date('Y-m-d', strtotime('-12 months')), date('Y-m-d')];
            case 'custom':
                $d = DateTime::createFromFormat('Y-m-d', (string)$desde);
                $h = DateTime::createFromFormat('Y-m-d', (string)$hasta);
                if (!$d || $d->format('Y-m-d') !== $desde || !$h || $h->format('Y-m-d') !== $hasta) {
                    throw new InvalidArgumentException('Fechas inválidas');
                }
                if ($d > $h) {
                    throw new InvalidArgumentException('La fecha inicial no puede ser posterior a la final');
                }
                return [$d->format('Y-m-d'), $h->format('Y-m-d')];
            default:
                throw new InvalidArgumentException('Rango no reconocido');
        }
    }

    public function kpis(string $desde, string $hasta): array
    {
        $stmt = $this->conn->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN estado_cita = 'Cancelada' THEN 1 ELSE 0 END) AS canceladas,
                SUM(CASE WHEN estado_cita = 'Completada' THEN 1 ELSE 0 END) AS completadas,
                SUM(CASE WHEN fecha_cita = CURDATE() THEN 1 ELSE 0 END) AS hoy
            FROM citas WHERE fecha_cita BETWEEN ? AND ?"
        );
        $stmt->execute([$desde, $hasta]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $total = (int)($row['total'] ?? 0);
        $canceladas = (int)($row['canceladas'] ?? 0);

        return [
            'total' => $total,
            'canceladas' => $canceladas,
            'completadas' => (int)($row['completadas'] ?? 0),
            'hoy' => (int)($row['hoy'] ?? 0),
            'tasa_cancelacion' => $total > 0 ? round(($canceladas / $total) * 100, 2) : 0.0,
        ];
    }

    public function porEstado(string $desde, string $hasta): array
    {
        $stmt = $this->conn->prepare(
            "SELECT estado_cita, COUNT(*) AS cantidad
             FROM citas
             WHERE fecha_cita BETWEEN ? AND ?
             GROUP BY estado_cita"
        );
        $stmt->execute([$desde, $hasta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function porEspecialidad(string $desde, string $hasta): array
    {
        $stmt = $this->conn->prepare(
            "SELECT e.nombre_especialidad, COUNT(c.id_cita) AS cantidad
             FROM citas c
             JOIN doctores d ON c.id_doctor = d.id_doctor
             JOIN especialidades e ON d.id_especialidad = e.id_especialidad
             WHERE c.fecha_cita BETWEEN ? AND ?
             GROUP BY e.id_especialidad
             ORDER BY cantidad DESC"
        );
        $stmt->execute([$desde, $hasta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function topDoctores(string $desde, string $hasta, int $limite = 10): array
    {
        $limite = max(1, min(50, $limite));
        $stmt = $this->conn->prepare(
            "SELECT d.nombre, d.apellido_paterno, COUNT(c.id_cita) AS cantidad
             FROM citas c
             JOIN doctores d ON c.id_doctor = d.id_doctor
             WHERE c.fecha_cita BETWEEN ? AND ?
             GROUP BY d.id_doctor
             ORDER BY cantidad DESC
             LIMIT $limite"
        );
        $stmt->execute([$desde, $hasta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tendencia mensual fija de los últimos 12 meses, independiente del filtro de rango activo.
     */
    public function tendenciaMensual(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT DATE_FORMAT(fecha_cita, '%Y-%m') AS periodo, COUNT(*) AS cantidad
             FROM citas
             WHERE fecha_cita >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
             GROUP BY periodo
             ORDER BY periodo ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Pacientes nuevos por mes, últimos 12 meses fijos (mismo criterio que tendenciaMensual:
     * independiente del filtro de rango activo). Usa pacientes.created_at.
     */
    public function pacientesNuevosPorMes(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS periodo, COUNT(*) AS cantidad
             FROM pacientes
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
             GROUP BY periodo
             ORDER BY periodo ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Total de pacientes con cuenta activa (excluye autoeliminados/anonimizados).
     * No depende del rango de fechas: es un conteo del estado actual.
     */
    public function totalPacientesActivos(): int
    {
        $stmt = $this->conn->query("SELECT COUNT(*) FROM pacientes WHERE cuenta_eliminada = 0");
        return (int)$stmt->fetchColumn();
    }

    /**
     * Doctores activos sin consultorio asignado actualmente. No depende del rango de fechas.
     */
    public function doctoresSinConsultorio(): array
    {
        $stmt = $this->conn->query(
            "SELECT id_doctor, nombre, apellido_paterno
             FROM doctores
             WHERE estado = 1 AND id_consultorio IS NULL
             ORDER BY nombre ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tasa de ocupación por doctor activo dentro del rango: citas confirmadas/completadas
     * entre el total de horarios disponibles en ese rango.
     *
     * El esquema no tiene una tabla de horarios por doctor, así que "horarios disponibles"
     * se aproxima con la misma rejilla fija que usa el resto del sistema para agendar citas
     * (07:00–23:30 en intervalos de 30 min, ver el modal "Nueva Cita" en admin.php): 34 slots
     * por día natural del rango, multiplicados por los días del rango.
     */
    public function ocupacionPorDoctor(string $desde, string $hasta, int $limite = 10): array
    {
        $limite = max(1, min(50, $limite));
        $dias = (new DateTime($desde))->diff(new DateTime($hasta))->days + 1;
        $slotsPorDia = 34;
        $slotsDisponibles = max(1, $dias * $slotsPorDia);

        $stmt = $this->conn->prepare(
            "SELECT d.id_doctor, d.nombre, d.apellido_paterno,
                    SUM(CASE WHEN c.estado_cita IN ('Confirmada', 'Completada') THEN 1 ELSE 0 END) AS ocupadas
             FROM doctores d
             LEFT JOIN citas c ON c.id_doctor = d.id_doctor AND c.fecha_cita BETWEEN ? AND ?
             WHERE d.estado = 1
             GROUP BY d.id_doctor
             ORDER BY ocupadas DESC
             LIMIT $limite"
        );
        $stmt->execute([$desde, $hasta]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filas as &$fila) {
            $ocupadas = (int)$fila['ocupadas'];
            $fila['ocupadas'] = $ocupadas;
            $fila['slots_disponibles'] = $slotsDisponibles;
            $fila['tasa_ocupacion'] = round(($ocupadas / $slotsDisponibles) * 100, 2);
        }
        unset($fila);

        return $filas;
    }
}
