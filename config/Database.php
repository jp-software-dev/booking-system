<?php
/**
 * CLASE DATABASE (PATRÓN SINGLETON)
 *
 * Gestiona la conexión a la base de datos usando el patrón Singleton para asegurar
 * que solo exista una instancia activa de PDO durante toda la ejecución del script.
 *
 * @requires config/config.php
 * @throws PDOException Si falla la conexión a la base de datos.
 */

require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $conn;

    // Constructor privado: evita la instanciación directa desde fuera de la clase.
    private function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            die("Error de conexión a la base de datos. Consulte al administrador.");
        }
    }

    // Obtiene la instancia única de la conexión.
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->conn;
    }
}
?>