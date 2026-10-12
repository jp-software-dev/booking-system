<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * MODELO DEL PERSONAL MÉDICO (DOCTORES Y ENFERMEROS)
 *
 * Alta, edición, baja y listado del equipo que se administra desde
 * admin_equipo.php. Los médicos se dan de baja de forma lógica (estado
 * 'Inactivo') para no romper el historial de citas; los enfermeros no tienen
 * citas asociadas y se borran físicamente.
 */
final class Personal
{
    /** Tipos de personal aceptados por los endpoints. */
    public const TIPO_DOCTOR = 'doctor';
    public const TIPO_ENFERMERO = 'enfermero';

    /** Turnos válidos de enfermería (ENUM de la tabla `enfermeros`). */
    public const TURNOS = ['Matutino', 'Vespertino', 'Nocturno'];

    private PDO $db;

    /**
     * @param PDO|null $db Conexión a usar. Si es null se usa la conexión compartida.
     */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Lista médicos activos y enfermeros en una sola consulta, ordenados por
     * puesto y nombre. La columna `detalle` trae la especialidad del médico o
     * el turno del enfermero.
     *
     * @return list<array<string, mixed>>
     */
    public function listarEquipoCompleto(): array
    {
        $sql = "SELECT d.id_doctor AS id, d.nombre, d.apellido_paterno, 'Doctor' AS puesto,
                       e.nombre_especialidad AS detalle, d.id_consultorio, d.id_especialidad, NULL AS turno
                FROM doctores d
                LEFT JOIN especialidades e ON d.id_especialidad = e.id_especialidad
                WHERE d.estado = 'Activo'
                UNION ALL
                SELECT en.id_enfermero AS id, en.nombre, en.apellido_paterno, 'Enfermero' AS puesto,
                       en.turno AS detalle, en.id_consultorio, NULL AS id_especialidad, en.turno
                FROM enfermeros en
                ORDER BY puesto ASC, nombre ASC";

        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Da de alta un médico activo.
     *
     * @param string   $nombre         Nombre(s).
     * @param string   $apellido       Apellido paterno.
     * @param int      $idEspecialidad Especialidad (obligatoria).
     * @param int|null $idConsultorio  Consultorio, o null si queda sin asignar.
     * @return int Id del médico creado.
     */
    public function agregarDoctor(string $nombre, string $apellido, int $idEspecialidad, ?int $idConsultorio): int
    {
        return Database::insertar(
            $this->db,
            "INSERT INTO doctores (nombre, apellido_paterno, id_especialidad, id_consultorio, estado) VALUES (?, ?, ?, ?, 'Activo')",
            [$nombre, $apellido, $idEspecialidad, $idConsultorio],
            'id_doctor'
        );
    }

    /**
     * Da de alta un enfermero.
     *
     * @param string   $nombre        Nombre(s).
     * @param string   $apellido      Apellido paterno.
     * @param string   $turno         Uno de Personal::TURNOS.
     * @param int|null $idConsultorio Consultorio, o null para disponibilidad flotante.
     * @return int Id del enfermero creado.
     */
    public function agregarEnfermero(string $nombre, string $apellido, string $turno, ?int $idConsultorio): int
    {
        return Database::insertar(
            $this->db,
            'INSERT INTO enfermeros (nombre, apellido_paterno, turno, id_consultorio) VALUES (?, ?, ?, ?)',
            [$nombre, $apellido, $turno, $idConsultorio],
            'id_enfermero'
        );
    }

    /**
     * Indica si existe el miembro del personal (para médicos, solo si está activo).
     *
     * @param string $tipo Personal::TIPO_DOCTOR o Personal::TIPO_ENFERMERO.
     * @param int    $id   Id del médico o enfermero.
     */
    public function existe(string $tipo, int $id): bool
    {
        $sql = $tipo === self::TIPO_DOCTOR
            ? "SELECT COUNT(*) FROM doctores WHERE id_doctor = ? AND estado = 'Activo'"
            : 'SELECT COUNT(*) FROM enfermeros WHERE id_enfermero = ?';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Actualiza los datos de un médico.
     *
     * @param int      $id             Médico.
     * @param string   $nombre         Nombre(s).
     * @param string   $apellido       Apellido paterno.
     * @param int      $idEspecialidad Especialidad.
     * @param int|null $idConsultorio  Consultorio o null.
     */
    public function actualizarDoctor(int $id, string $nombre, string $apellido, int $idEspecialidad, ?int $idConsultorio): void
    {
        $this->db
            ->prepare('UPDATE doctores SET nombre = ?, apellido_paterno = ?, id_especialidad = ?, id_consultorio = ? WHERE id_doctor = ?')
            ->execute([$nombre, $apellido, $idEspecialidad, $idConsultorio, $id]);
    }

    /**
     * Actualiza los datos de un enfermero.
     *
     * @param int      $id            Enfermero.
     * @param string   $nombre        Nombre(s).
     * @param string   $apellido      Apellido paterno.
     * @param string   $turno         Uno de Personal::TURNOS.
     * @param int|null $idConsultorio Consultorio o null.
     */
    public function actualizarEnfermero(int $id, string $nombre, string $apellido, string $turno, ?int $idConsultorio): void
    {
        $this->db
            ->prepare('UPDATE enfermeros SET nombre = ?, apellido_paterno = ?, turno = ?, id_consultorio = ? WHERE id_enfermero = ?')
            ->execute([$nombre, $apellido, $turno, $idConsultorio, $id]);
    }

    /**
     * Baja lógica de un médico: queda 'Inactivo' y libera su consultorio.
     *
     * @param int $id Médico.
     * @return bool true si el médico existía.
     */
    public function darDeBajaDoctor(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE doctores SET estado = 'Inactivo', id_consultorio = NULL WHERE id_doctor = ? AND estado = 'Activo'");
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Baja física de un enfermero.
     *
     * @param int $id Enfermero.
     * @return bool true si el enfermero existía.
     */
    public function eliminarEnfermero(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM enfermeros WHERE id_enfermero = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }
}
