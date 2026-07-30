<?php
// Modelo unificado de personal que elimina duplicados seleccionando de forma directa desde sus tablas base
class Personal {
    private $conn;
    public function __construct($db) { $this->conn = $db; }

    public function listarEquipoCompleto() {
        $query = "
            SELECT d.id_doctor as id, d.nombre, d.apellido_paterno, 'Doctor' as puesto, e.nombre_especialidad as detalle, d.id_consultorio, d.id_especialidad, NULL as turno
            FROM doctores d
            LEFT JOIN especialidades e ON d.id_especialidad = e.id_especialidad
            WHERE d.estado = 1
            UNION ALL
            SELECT en.id_enfermero as id, en.nombre, en.apellido_paterno, 'Enfermero' as puesto, en.turno as detalle, en.id_consultorio, NULL as id_especialidad, en.turno
            FROM enfermeros en
            ORDER BY puesto ASC, nombre ASC
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}