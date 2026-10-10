<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * MODELO DE MÉDICOS
 *
 * Consultas sobre la tabla `doctores`. El campo `estado` es un ENUM
 * ('Activo', 'Inactivo'); se compara siempre con el texto, no con su índice
 * numérico, para que las consultas sean legibles y no dependan del orden del ENUM.
 */
final class Doctor
{
    public const ESTADO_ACTIVO = 'Activo';
    public const ESTADO_INACTIVO = 'Inactivo';

    private PDO $db;

    /**
     * @param PDO|null $db Conexión a usar. Si es null se usa la conexión compartida.
     */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Lista los médicos activos ordenados por nombre.
     *
     * @param bool $soloConConsultorio true para devolver solo los que tienen
     *                                 consultorio asignado (los únicos que
     *                                 pueden recibir citas).
     * @return list<array<string, mixed>>
     */
    public function listarActivos(bool $soloConConsultorio = false): array
    {
        $sql = "SELECT d.id_doctor, d.nombre, d.apellido_paterno, d.id_consultorio, d.id_especialidad,
                       c.nombre AS consultorio_nombre
                FROM doctores d
                LEFT JOIN consultorios c ON d.id_consultorio = c.id_consultorio
                WHERE d.estado = 'Activo'";

        if ($soloConConsultorio) {
            $sql .= ' AND d.id_consultorio IS NOT NULL';
        }

        return $this->db->query($sql . ' ORDER BY d.nombre ASC, d.apellido_paterno ASC')->fetchAll();
    }

    /**
     * Lista todos los médicos, activos o no (para filtros de historial, donde
     * aparecen médicos que ya fueron dados de baja).
     *
     * @return list<array<string, mixed>>
     */
    public function listarTodos(): array
    {
        return $this->db
            ->query('SELECT id_doctor, nombre, apellido_paterno, estado FROM doctores ORDER BY nombre ASC, apellido_paterno ASC')
            ->fetchAll();
    }

    /**
     * Busca un médico por id.
     *
     * @param int $idDoctor Médico.
     * @return array<string, mixed>|null
     */
    public function obtenerPorId(int $idDoctor): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id_doctor, nombre, apellido_paterno, id_especialidad, id_consultorio, estado FROM doctores WHERE id_doctor = ?'
        );
        $stmt->execute([$idDoctor]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Devuelve el consultorio asignado a un médico activo, o null si no tiene.
     *
     * El consultorio de una cita se resuelve siempre en el servidor con este
     * método; nunca se acepta un id_consultorio enviado por el cliente.
     *
     * @param int $idDoctor Médico.
     */
    public function obtenerConsultorioAsignado(int $idDoctor): ?int
    {
        $stmt = $this->db->prepare("SELECT id_consultorio FROM doctores WHERE id_doctor = ? AND estado = 'Activo'");
        $stmt->execute([$idDoctor]);
        $idConsultorio = $stmt->fetchColumn();

        return ($idConsultorio === false || $idConsultorio === null) ? null : (int) $idConsultorio;
    }
}
