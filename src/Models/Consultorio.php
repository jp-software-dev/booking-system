<?php
/**
 * MODELO DE CONSULTORIOS
 *
 * Define la estructura para gestionar las 5 habitaciones disponibles
 * en el sistema hospitalario.
 *
 * @requires PDO
 * @declare(strict_types=1)
 */

declare(strict_types=1);

class Consultorio {
    
    private PDO $conn;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    // MÉTODO DE LECTURA: Retorna un arreglo con los datos de los 5 consultorios.
    public function listarTodos(): array {
        
        $query = "SELECT id_consultorio, nombre, ubicacion FROM consultorios ORDER BY id_consultorio ASC";
                  
        $stmt = $this->conn->query($query);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>