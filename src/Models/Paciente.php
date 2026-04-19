<?php
/**
 * MODELO DE PACIENTE
 *
 * Define la estructura orientada a objetos para gestionar de forma segura
 * los datos de los pacientes en la base de datos, incluyendo búsqueda por ID
 * y verificación de existencia por correo electrónico.
 *
 * @requires PDO
 * @declare(strict_types=1)
 */

declare(strict_types=1);

class Paciente {
    
    private PDO $conn;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    // MÉTODO DE BÚSQUEDA: Recibe un ID y devuelve los datos del paciente o null.
    public function obtenerPorId(int $id): ?array {
        
        $query = "SELECT id_paciente, nombre, email FROM pacientes WHERE id_paciente = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        
        $paciente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $paciente ?: null;
    }

    // VERIFICADOR DE DUPLICADOS: Comprueba si un correo ya está registrado.
    public function existeEmail(string $email): bool {
        
        $query = "SELECT id_paciente FROM pacientes WHERE email = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$email]);
        
        return $stmt->rowCount() > 0;
    }
}
?>