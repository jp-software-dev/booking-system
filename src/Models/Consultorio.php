<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * MODELO DE CONSULTORIOS
 *
 * Consultas sobre la tabla `consultorios` y la asignación de médicos.
 * Regla de negocio: un consultorio tiene como máximo un médico asignado.
 */
final class Consultorio
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
     * Lista todos los consultorios ordenados por id.
     *
     * @return list<array<string, mixed>>
     */
    public function listarTodos(): array
    {
        return $this->db
            ->query('SELECT id_consultorio, nombre, ubicacion, id_especialidad FROM consultorios ORDER BY id_consultorio ASC')
            ->fetchAll();
    }

    /**
     * Busca un consultorio por id.
     *
     * @param int $idConsultorio Consultorio.
     * @return array<string, mixed>|null
     */
    public function obtenerPorId(int $idConsultorio): ?array
    {
        $stmt = $this->db->prepare('SELECT id_consultorio, nombre, id_especialidad FROM consultorios WHERE id_consultorio = ?');
        $stmt->execute([$idConsultorio]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Verifica que un médico pueda ocupar el consultorio.
     *
     * Reglas: el consultorio debe existir; si tiene especialidad predeterminada,
     * el médico debe ser de esa especialidad; y, salvo que se vaya a reemplazar
     * al ocupante, el consultorio no debe estar asignado a otro médico.
     *
     * @param int      $idConsultorio      Consultorio destino.
     * @param int      $idEspecialidad     Especialidad del médico.
     * @param int|null $idDoctor           Médico que se asigna (null si es un alta nueva).
     * @param bool     $reemplazarOcupante true si el médico actual será desasignado
     *                                     (pantalla de Consultorios).
     * @return string|null Mensaje de error o null si la asignación es válida.
     */
    public function validarAsignacion(int $idConsultorio, int $idEspecialidad, ?int $idDoctor, bool $reemplazarOcupante): ?string
    {
        $consultorio = $this->obtenerPorId($idConsultorio);
        if ($consultorio === null) {
            return 'El consultorio seleccionado no existe';
        }

        if ($consultorio['id_especialidad'] !== null && (int) $consultorio['id_especialidad'] !== $idEspecialidad) {
            return 'La especialidad del médico no coincide con la del consultorio';
        }

        if (!$reemplazarOcupante) {
            $stmt = $this->db->prepare(
                "SELECT nombre, apellido_paterno FROM doctores
                 WHERE id_consultorio = ? AND estado = 'Activo' AND id_doctor <> ? LIMIT 1"
            );
            $stmt->execute([$idConsultorio, $idDoctor ?? 0]);
            $ocupante = $stmt->fetch();

            if ($ocupante) {
                return sprintf(
                    '%s ya está asignado al Dr. %s %s. Libéralo primero desde Consultorios.',
                    $consultorio['nombre'],
                    $ocupante['nombre'],
                    $ocupante['apellido_paterno']
                );
            }
        }

        return null;
    }

    /**
     * Asigna un médico al consultorio o lo deja libre.
     *
     * Primero desasigna a quien lo ocupe y después asigna al nuevo médico.
     * Debe llamarse dentro de una transacción para que ambos pasos sean atómicos.
     *
     * @param int      $idConsultorio Consultorio.
     * @param int|null $idDoctor      Médico a asignar, o null para liberar el consultorio.
     */
    public function asignarDoctor(int $idConsultorio, ?int $idDoctor): void
    {
        $this->db->prepare('UPDATE doctores SET id_consultorio = NULL WHERE id_consultorio = ?')->execute([$idConsultorio]);

        if ($idDoctor !== null) {
            $this->db->prepare('UPDATE doctores SET id_consultorio = ? WHERE id_doctor = ?')->execute([$idConsultorio, $idDoctor]);
        }
    }

    /**
     * Cambia (o quita) la especialidad predeterminada del consultorio.
     *
     * @param int      $idConsultorio  Consultorio.
     * @param int|null $idEspecialidad Especialidad, o null para quitarla.
     */
    public function actualizarEspecialidad(int $idConsultorio, ?int $idEspecialidad): void
    {
        $this->db
            ->prepare('UPDATE consultorios SET id_especialidad = ? WHERE id_consultorio = ?')
            ->execute([$idEspecialidad, $idConsultorio]);
    }
}
