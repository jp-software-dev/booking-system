<?php
/**
 * MODELO DE DOCTOR
 *
 * Define la estructura orientada a objetos para gestionar la información
 * de los médicos en la base de datos, principalmente para listar los activos.
 *
 * @requires PDO
 * @declare(strict_types=1)
 */

declare(strict_types=1);

class Doctor {
    
    private PDO $conn;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    // MÉTODO DE LECTURA: Retorna un arreglo con los datos de los doctores disponibles y su consultorio asignado.
    public function listarActivos(): array {
        
        $query = "SELECT d.id_doctor, d.nombre, d.apellido_paterno, c.nombre as consultorio_nombre
                  FROM doctores d
                  LEFT JOIN consultorios c ON d.id_consultorio = c.id_consultorio
                  WHERE d.estado = 1 AND d.id_consultorio IS NOT NULL
                  ORDER BY d.nombre ASC";
                  
        $stmt = $this->conn->query($query);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Devuelve el id_consultorio actualmente asignado a un doctor activo, o null si no tiene uno.
    // Se resuelve en el servidor (no se confía en un id_consultorio enviado por el cliente) para
    // evitar que una cita quede con un consultorio nulo o desincronizado del doctor real.
    public function obtenerConsultorioAsignado(int $id_doctor): ?int {

        $query = "SELECT id_consultorio FROM doctores WHERE id_doctor = ? AND estado = 1";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id_doctor]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila || $fila['id_consultorio'] === null) {
            return null;
        }

        return (int)$fila['id_consultorio'];
    }
}
?>