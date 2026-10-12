<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Helpers\FiltroCitas;
use App\Helpers\Paginacion;
use PDO;

/**
 * MODELO DE CITAS
 *
 * Acceso a la tabla `citas`: alta, disponibilidad de horarios, reprogramación,
 * cambios de estado, cancelación y consultas de apoyo para notificaciones.
 *
 * Integridad de horarios: la columna generada `slot_activo` tiene un índice
 * único que impide, a nivel de base de datos, dos citas activas del mismo
 * médico en la misma fecha y hora. Si dos personas reservan a la vez, una de
 * las dos escrituras falla con SQLSTATE 23000 y quien llama debe atraparla.
 */
final class Cita
{
    /** Todos los estados posibles, en el orden del flujo de atención. */
    public const ESTADOS = ['Pendiente', 'Confirmada', 'En Curso', 'Completada', 'Cancelada'];

    /** Estados que ocupan el horario del médico. */
    public const ESTADOS_ACTIVOS = ['Pendiente', 'Confirmada', 'En Curso'];

    /** Estados en los que el paciente todavía puede cancelar o reprogramar. */
    public const ESTADOS_MODIFICABLES = ['Pendiente', 'Confirmada'];

    /** Tablas y alias que usan los listados del panel (compatibles con FiltroCitas). */
    private const FROM_PANEL = 'FROM citas c
        JOIN pacientes p ON c.id_paciente = p.id_paciente
        JOIN doctores d ON c.id_doctor = d.id_doctor
        LEFT JOIN consultorios co ON c.id_consultorio = co.id_consultorio';

    private PDO $db;

    /**
     * @param PDO|null $db Conexión a usar. Si es null se usa la conexión compartida.
     */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Indica si el médico tiene libre el horario indicado.
     *
     * @param int      $idDoctor      Médico.
     * @param string   $fecha         Fecha (Y-m-d).
     * @param string   $hora          Hora (HH:MM).
     * @param int|null $excluirIdCita Cita a ignorar (la que se está reprogramando).
     */
    public function verificarDisponibilidad(int $idDoctor, string $fecha, string $hora, ?int $excluirIdCita = null): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM citas
             WHERE id_doctor = ? AND fecha_cita = ? AND hora_inicio = ?
               AND estado_cita <> 'Cancelada' AND id_cita <> ?"
        );
        $stmt->execute([$idDoctor, $fecha, $hora, $excluirIdCita ?? 0]);

        return (int) $stmt->fetchColumn() === 0;
    }

    /**
     * Inserta una cita nueva en estado Pendiente.
     *
     * @param int    $idPaciente    Paciente.
     * @param int    $idDoctor      Médico.
     * @param int    $idConsultorio Consultorio asignado al médico.
     * @param string $fecha         Fecha (Y-m-d).
     * @param string $hora          Hora (HH:MM).
     * @param string $motivo        Motivo de consulta.
     * @return int Id de la cita creada.
     */
    public function agendar(int $idPaciente, int $idDoctor, int $idConsultorio, string $fecha, string $hora, string $motivo): int
    {
        return Database::insertar(
            $this->db,
            "INSERT INTO citas (id_paciente, id_doctor, id_consultorio, fecha_cita, hora_inicio, motivo_consulta, estado_cita)
             VALUES (?, ?, ?, ?, ?, ?, 'Pendiente')",
            [$idPaciente, $idDoctor, $idConsultorio, $fecha, $hora, $motivo],
            'id_cita'
        );
    }

    /**
     * Borra físicamente una cita (solo administradores).
     *
     * @param int $idCita Cita a borrar.
     * @return bool true si existía y se borró.
     */
    public function eliminar(int $idCita): bool
    {
        $stmt = $this->db->prepare('DELETE FROM citas WHERE id_cita = ?');
        $stmt->execute([$idCita]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Obtiene una cita y bloquea su fila hasta el final de la transacción
     * (SELECT ... FOR UPDATE). Debe llamarse dentro de una transacción.
     *
     * @param int      $idCita     Cita buscada.
     * @param int|null $idPaciente Si se indica, solo la devuelve si pertenece a ese paciente.
     * @return array<string, mixed>|null
     */
    public function obtenerParaActualizar(int $idCita, ?int $idPaciente = null): ?array
    {
        $sql = 'SELECT id_cita, id_paciente, id_doctor, fecha_cita, hora_inicio, motivo_consulta, estado_cita
                FROM citas WHERE id_cita = ?';
        $params = [$idCita];

        if ($idPaciente !== null) {
            $sql .= ' AND id_paciente = ?';
            $params[] = $idPaciente;
        }

        $stmt = $this->db->prepare($sql . ' FOR UPDATE');
        $stmt->execute($params);

        return $stmt->fetch() ?: null;
    }

    /**
     * Cambia la fecha y hora de una cita.
     *
     * @param int         $idCita           Cita a mover.
     * @param string      $fecha            Nueva fecha (Y-m-d).
     * @param string      $hora             Nueva hora (HH:MM).
     * @param string|null $motivo           Nuevo motivo, o null para conservar el actual.
     * @param bool        $volverAPendiente true para regresar la cita a Pendiente
     *                                      (cuando la reprograma el paciente, el
     *                                      administrador debe confirmarla otra vez).
     */
    public function reprogramar(int $idCita, string $fecha, string $hora, ?string $motivo, bool $volverAPendiente): void
    {
        $sql = 'UPDATE citas SET fecha_cita = ?, hora_inicio = ?, motivo_consulta = COALESCE(?, motivo_consulta)';
        if ($volverAPendiente) {
            $sql .= ", estado_cita = 'Pendiente'";
        }

        $this->db->prepare($sql . ' WHERE id_cita = ?')->execute([$fecha, $hora, $motivo, $idCita]);
    }

    /**
     * Cambia el estado de una cita.
     *
     * @param int    $idCita Cita.
     * @param string $estado Uno de Cita::ESTADOS.
     */
    public function cambiarEstado(int $idCita, string $estado): void
    {
        $this->db->prepare('UPDATE citas SET estado_cita = ? WHERE id_cita = ?')->execute([$estado, $idCita]);
    }

    /**
     * Cuenta las citas activas y futuras de un paciente (para el límite MAX_CITAS_ACTIVAS).
     *
     * Las citas pasadas que quedaron en Pendiente no cuentan: ya no ocupan un lugar real.
     *
     * @param int $idPaciente Paciente.
     */
    public function contarActivasPorPaciente(int $idPaciente): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM citas
             WHERE id_paciente = ? AND estado_cita IN ('Pendiente', 'Confirmada', 'En Curso')
               AND fecha_cita >= ?"
        );
        $stmt->execute([$idPaciente, date('Y-m-d')]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Cuenta las citas futuras pendientes o confirmadas de un médico.
     *
     * @param int $idDoctor Médico.
     */
    public function contarFuturasActivasPorDoctor(int $idDoctor): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM citas
             WHERE id_doctor = ? AND estado_cita IN ('Pendiente', 'Confirmada')
               AND (fecha_cita > ? OR (fecha_cita = ? AND hora_inicio > ?))"
        );
        $stmt->execute(array_merge([$idDoctor], self::parametrosFuturo()));

        return (int) $stmt->fetchColumn();
    }

    /**
     * Cancela todas las citas futuras pendientes o confirmadas de un paciente
     * (se usa al eliminar su cuenta, para liberar esos horarios).
     *
     * @param int $idPaciente Paciente.
     * @return int Cantidad de citas canceladas.
     */
    public function cancelarFuturasPorPaciente(int $idPaciente): int
    {
        $stmt = $this->db->prepare(
            "UPDATE citas SET estado_cita = 'Cancelada'
             WHERE id_paciente = ? AND estado_cita IN ('Pendiente', 'Confirmada')
               AND (fecha_cita > ? OR (fecha_cita = ? AND hora_inicio > ?))"
        );
        $stmt->execute(array_merge([$idPaciente], self::parametrosFuturo()));

        return $stmt->rowCount();
    }

    /**
     * Cuenta las citas que cumplen un filtro del panel administrativo.
     *
     * @param FiltroCitas $filtro Filtros activos.
     */
    public function contarConFiltro(FiltroCitas $filtro): int
    {
        [$where, $params] = $filtro->aSql();

        $stmt = $this->db->prepare('SELECT COUNT(*) ' . self::FROM_PANEL . ' ' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Lista las citas que cumplen un filtro, con paciente, médico y consultorio,
     * de la más reciente a la más antigua.
     *
     * @param FiltroCitas     $filtro     Filtros activos.
     * @param Paginacion|null $paginacion Página a traer, o null para traer todas.
     * @return list<array<string, mixed>>
     */
    public function listarConFiltro(FiltroCitas $filtro, ?Paginacion $paginacion = null): array
    {
        [$where, $params] = $filtro->aSql();

        $sql = 'SELECT c.id_cita, c.fecha_cita, c.hora_inicio, c.motivo_consulta, c.estado_cita,
                       p.nombre AS paciente_nombre, p.apellido_paterno AS paciente_apellido,
                       d.nombre AS doctor_nombre, d.apellido_paterno AS doctor_apellido,
                       co.nombre AS consultorio_nombre
                ' . self::FROM_PANEL . ' ' . $where . '
                ORDER BY c.fecha_cita DESC, c.hora_inicio DESC';

        if ($paginacion !== null) {
            return $paginacion->consultar($this->db, $sql, $params);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Totales globales por estado para los contadores del panel de citas.
     *
     * @return array{total: int, pendientes: int, confirmadas: int, completadas: int}
     */
    public function totalesPorEstado(): array
    {
        $fila = $this->db->query(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN estado_cita = 'Pendiente' THEN 1 ELSE 0 END) AS pendientes,
                    SUM(CASE WHEN estado_cita = 'Confirmada' THEN 1 ELSE 0 END) AS confirmadas,
                    SUM(CASE WHEN estado_cita = 'Completada' THEN 1 ELSE 0 END) AS completadas
             FROM citas"
        )->fetch() ?: [];

        return [
            'total'       => (int) ($fila['total'] ?? 0),
            'pendientes'  => (int) ($fila['pendientes'] ?? 0),
            'confirmadas' => (int) ($fila['confirmadas'] ?? 0),
            'completadas' => (int) ($fila['completadas'] ?? 0),
        ];
    }

    /**
     * Lista las citas de un paciente con médico y consultorio, de la más reciente a la más antigua.
     *
     * @param int  $idPaciente      Paciente.
     * @param bool $incluirOcultas  true para incluir las que el paciente quitó de su
     *                              historial (vista del administrador y exportación de datos).
     * @return list<array<string, mixed>>
     */
    public function listarPorPaciente(int $idPaciente, bool $incluirOcultas): array
    {
        $sql = 'SELECT c.id_cita, c.fecha_cita, c.hora_inicio, c.motivo_consulta, c.estado_cita,
                       d.nombre AS doctor_nombre, d.apellido_paterno AS doctor_apellido,
                       co.nombre AS consultorio_nombre
                FROM citas c
                JOIN doctores d ON c.id_doctor = d.id_doctor
                LEFT JOIN consultorios co ON c.id_consultorio = co.id_consultorio
                WHERE c.id_paciente = ?';

        if (!$incluirOcultas) {
            $sql .= ' AND c.oculto_para_paciente = 0';
        }

        $stmt = $this->db->prepare($sql . ' ORDER BY c.fecha_cita DESC, c.hora_inicio DESC');
        $stmt->execute([$idPaciente]);

        return $stmt->fetchAll();
    }

    /**
     * Próximas citas pendientes o confirmadas del paciente (para la página de inicio).
     *
     * @param int $idPaciente Paciente.
     * @param int $limite     Cantidad máxima de citas.
     * @return list<array<string, mixed>>
     */
    public function listarProximasPorPaciente(int $idPaciente, int $limite = 3): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.fecha_cita, c.hora_inicio, c.estado_cita,
                    d.nombre AS doctor_nombre, d.apellido_paterno AS doctor_apellido
             FROM citas c
             JOIN doctores d ON c.id_doctor = d.id_doctor
             WHERE c.id_paciente = ? AND c.fecha_cita >= ? AND c.estado_cita IN ('Pendiente', 'Confirmada')
             ORDER BY c.fecha_cita ASC, c.hora_inicio ASC
             LIMIT ?"
        );
        $stmt->bindValue(1, $idPaciente, PDO::PARAM_INT);
        $stmt->bindValue(2, date('Y-m-d'));
        $stmt->bindValue(3, $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Quita una cita cerrada (completada o cancelada) del historial que ve el paciente.
     *
     * No la borra: la fila sigue disponible para el administrador y las métricas.
     *
     * @param int $idCita     Cita.
     * @param int $idPaciente Paciente dueño de la cita.
     * @return string|null Mensaje de error, o null si se ocultó.
     */
    public function ocultarParaPaciente(int $idCita, int $idPaciente): ?string
    {
        $stmt = $this->db->prepare('SELECT estado_cita FROM citas WHERE id_cita = ? AND id_paciente = ?');
        $stmt->execute([$idCita, $idPaciente]);
        $estado = $stmt->fetchColumn();

        if ($estado === false) {
            return 'Cita no encontrada';
        }

        if (!in_array($estado, ['Completada', 'Cancelada'], true)) {
            return 'Solo puedes quitar de tu historial citas completadas o canceladas';
        }

        $this->db->prepare('UPDATE citas SET oculto_para_paciente = 1 WHERE id_cita = ?')->execute([$idCita]);

        return null;
    }

    /**
     * Parámetros [hoy, hoy, hora actual] para la condición "la cita todavía no ocurre":
     * (fecha_cita > ? OR (fecha_cita = ? AND hora_inicio > ?)).
     *
     * La fecha y la hora se calculan en PHP para no depender de la zona horaria
     * del servidor de base de datos.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private static function parametrosFuturo(): array
    {
        $hoy = date('Y-m-d');

        return [$hoy, $hoy, date('H:i:s')];
    }

    /**
     * Lista los horarios ocupados desde una fecha, para marcar la agenda.
     *
     * Por minimización de datos solo expone médico, fecha y hora: nunca el
     * paciente ni el motivo de consulta de otras personas.
     *
     * @param string $desde Fecha inicial (Y-m-d).
     * @return list<array{id_doctor: int, fecha: string, hora: string}>
     */
    public function listarHorariosOcupados(string $desde): array
    {
        $stmt = $this->db->prepare(
            "SELECT id_doctor, fecha_cita, hora_inicio FROM citas
             WHERE fecha_cita >= ? AND estado_cita IN ('Pendiente', 'Confirmada', 'En Curso')"
        );
        $stmt->execute([$desde]);

        return array_map(static fn (array $fila): array => [
            'id_doctor' => (int) $fila['id_doctor'],
            'fecha'     => (string) $fila['fecha_cita'],
            'hora'      => substr((string) $fila['hora_inicio'], 0, 5),
        ], $stmt->fetchAll());
    }

    /**
     * Devuelve los datos de una cita necesarios para enviar correos de notificación.
     *
     * @param int $idCita Cita.
     * @return array<string, mixed>|null
     */
    public function obtenerDetalleNotificacion(int $idCita): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT c.id_cita, c.fecha_cita, c.hora_inicio, c.motivo_consulta, c.estado_cita,
                    p.email AS paciente_email, p.nombre AS paciente_nombre, p.recibir_notificaciones,
                    d.nombre AS doctor_nombre, d.apellido_paterno AS doctor_apellido,
                    co.nombre AS consultorio_nombre
             FROM citas c
             JOIN pacientes p ON c.id_paciente = p.id_paciente
             JOIN doctores d ON c.id_doctor = d.id_doctor
             LEFT JOIN consultorios co ON c.id_consultorio = co.id_consultorio
             WHERE c.id_cita = ?'
        );
        $stmt->execute([$idCita]);

        return $stmt->fetch() ?: null;
    }
}
