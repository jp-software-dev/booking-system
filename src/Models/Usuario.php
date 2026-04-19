<?php
/**
 * MODELO DE USUARIO (ADMIN)
 *
 * Define la estructura y el comportamiento para interactuar con la tabla
 * de administradores (usuarios) en la base de datos.
 *
 * @requires PDO
 * @declare(strict_types=1)
 */

declare(strict_types=1);

class Usuario {
    
    private PDO $conn;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    // MÉTODO DE BÚSQUEDA: Recibe el nombre de usuario y devuelve sus datos o null.
    public function obtenerPorUsuario(string $usuario): ?array {
        
        $query = "SELECT id_usuario, usuario, nombre, password_hash, rol FROM usuarios WHERE usuario = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$usuario]);
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user ?: null;
    }
}
?>