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

    // MÉTODO DE LECTURA: Retorna un arreglo con los datos de los doctores disponibles.
    public function listarActivos(): array {
        
        $query = "SELECT id_doctor, nombre, apellido_paterno 
                  FROM doctores 
                  WHERE estado = 1 
                  ORDER BY nombre ASC";
                  
        $stmt = $this->conn->query($query);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>