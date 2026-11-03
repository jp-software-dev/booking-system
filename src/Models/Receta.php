<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * MODELO DE RECETAS MÉDICAS
 *
 * Una receta la emite un médico a un paciente, normalmente durante una cita.
 * Sus medicamentos son tratamientos (App\Models\Tratamiento) ligados por
 * id_receta, de modo que al recetar quedan programados los recordatorios.
 */
final class Receta
{
    private PDO $db;

    /**
     * @param PDO|null $db Conexión a usar. Si es null se usa la conexión compartida.
     */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Registra la receta y devuelve su id.
     */
    public function crear(int $idPaciente, int $idDoctor, ?int $idCita, string $diagnostico, ?string $indicaciones): int
    {
        return Database::insertar(
            $this->db,
            'INSERT INTO recetas (id_paciente, id_doctor, id_cita, diagnostico, indicaciones, fecha_emision)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$idPaciente, $idDoctor, $idCita, $diagnostico, $indicaciones, date('Y-m-d H:i:s')],
            'id_receta'
        );
    }

    /**
     * Historial de recetas emitidas por un médico, con filtros opcionales.
     *
     * @param array{paciente?: string, desde?: string, hasta?: string} $filtros
     * @return list<array<string, mixed>>
     */
    public function listarPorDoctor(int $idDoctor, array $filtros = []): array
    {
        $condiciones = ['r.id_doctor = ?'];
        $params = [$idDoctor];

        $paciente = trim((string) ($filtros['paciente'] ?? ''));
        if ($paciente !== '') {
            $patron = '%' . addcslashes($paciente, '%_\\') . '%';
            $condiciones[] = "(LOWER(p.nombre) LIKE LOWER(?) OR LOWER(p.apellido_paterno) LIKE LOWER(?)"
                . " OR LOWER(CONCAT(p.nombre, ' ', p.apellido_paterno)) LIKE LOWER(?))";
            array_push($params, $patron, $patron, $patron);
        }
        if (!empty($filtros['desde'])) {
            $condiciones[] = 'r.fecha_emision >= ?';
            $params[] = $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $condiciones[] = 'r.fecha_emision <= ?';
            $params[] = $filtros['hasta'] . ' 23:59:59';
        }

        $stmt = $this->db->prepare(
            'SELECT r.id_receta, r.diagnostico, r.fecha_emision, r.id_cita,
                    p.id_paciente, p.nombre AS paciente_nombre, p.apellido_paterno AS paciente_apellido,
                    (SELECT COUNT(*) FROM tratamientos t WHERE t.id_receta = r.id_receta) AS medicamentos
             FROM recetas r
             INNER JOIN pacientes p ON p.id_paciente = r.id_paciente
             WHERE ' . implode(' AND ', $condiciones) . '
             ORDER BY r.fecha_emision DESC, r.id_receta DESC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Recetas recibidas por un paciente.
     *
     * @return list<array<string, mixed>>
     */
    public function listarPorPaciente(int $idPaciente): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.id_receta, r.diagnostico, r.fecha_emision,
                    d.nombre AS doctor_nombre, d.apellido_paterno AS doctor_apellido, e.nombre_especialidad,
                    (SELECT COUNT(*) FROM tratamientos t WHERE t.id_receta = r.id_receta) AS medicamentos
             FROM recetas r
             INNER JOIN doctores d ON d.id_doctor = r.id_doctor
             LEFT JOIN especialidades e ON e.id_especialidad = d.id_especialidad
             WHERE r.id_paciente = ?
             ORDER BY r.fecha_emision DESC, r.id_receta DESC'
        );
        $stmt->execute([$idPaciente]);

        return $stmt->fetchAll();
    }

    /**
     * Receta completa (médico, paciente y medicamentos con su avance), o null.
     *
     * @return array<string, mixed>|null
     */
    public function obtenerDetalle(int $idReceta): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT r.id_receta, r.id_paciente, r.id_doctor, r.id_cita, r.diagnostico, r.indicaciones, r.fecha_emision,
                    p.nombre AS paciente_nombre, p.apellido_paterno AS paciente_apellido, p.apellido_materno AS paciente_apellido_materno,
                    p.fecha_nacimiento, p.genero,
                    d.nombre AS doctor_nombre, d.apellido_paterno AS doctor_apellido, e.nombre_especialidad,
                    c.nombre AS consultorio_nombre
             FROM recetas r
             INNER JOIN pacientes p ON p.id_paciente = r.id_paciente
             INNER JOIN doctores d ON d.id_doctor = r.id_doctor
             LEFT JOIN especialidades e ON e.id_especialidad = d.id_especialidad
             LEFT JOIN consultorios c ON c.id_consultorio = d.id_consultorio
             WHERE r.id_receta = ?'
        );
        $stmt->execute([$idReceta]);
        $receta = $stmt->fetch();

        if (!$receta) {
            return null;
        }

        $receta['medicamentos'] = (new Tratamiento($this->db))->listarPorReceta($idReceta);

        return $receta;
    }

    /**
     * Folio legible de una receta (ej. "RX-000042").
     */
    public static function folio(int $idReceta): string
    {
        return sprintf('RX-%06d', $idReceta);
    }
}
