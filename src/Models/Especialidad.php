<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * MODELO DE ESPECIALIDADES MÉDICAS
 *
 * Catálogo de la tabla `especialidades`, usado en los formularios de personal
 * y de consultorios.
 */
final class Especialidad
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
     * Lista las especialidades ordenadas alfabéticamente.
     *
     * @return list<array{id_especialidad: int, nombre_especialidad: string}>
     */
    public function listar(): array
    {
        return $this->db
            ->query('SELECT id_especialidad, nombre_especialidad FROM especialidades ORDER BY nombre_especialidad ASC')
            ->fetchAll();
    }

    /**
     * Indica si existe la especialidad.
     *
     * @param int $idEspecialidad Especialidad.
     */
    public function existe(int $idEspecialidad): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM especialidades WHERE id_especialidad = ?');
        $stmt->execute([$idEspecialidad]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
