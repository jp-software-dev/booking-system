<?php
// CLASE MODELO: Define la estructura y el comportamiento para interactuar con la tabla de administradores (usuarios) en la base de datos.
class Usuario {
    
    // PROPIEDAD PRIVADA: Encapsula la conexión a la base de datos para protegerla y evitar que sea alterada desde fuera de la clase.
    private PDO $conn;

    // CONSTRUCTOR: Inicializa el modelo recibiendo una instancia activa de PDO (inyección de dependencias) y asignándola a la propiedad interna.
    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    // MÉTODO DE BÚSQUEDA: Función tipada que recibe el nombre de usuario y devuelve sus datos en un arreglo asociativo, o null si no existe.
    public function obtenerPorUsuario(string $usuario): ?array {
        
        // CONSULTA PREPARADA: Define la instrucción SQL seleccionando únicamente los campos estrictamente necesarios para la autenticación.
        $query = "SELECT id_usuario, usuario, nombre, password_hash, rol FROM usuarios WHERE usuario = ?";
        
        // PREPARACIÓN: Pre-compila la consulta en el motor de la base de datos para prevenir de raíz los ataques de inyección SQL (SQLi).
        $stmt = $this->conn->prepare($query);
        
        // EJECUCIÓN SEGURA: Pasa el valor del nombre de usuario ingresado para que reemplace de forma segura el marcador de posición (?).
        $stmt->execute([$usuario]);
        
        // EXTRACCIÓN: Recupera el primer registro coincidente y lo formatea como un arreglo asociativo limpio.
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // RETORNO CONDICIONAL: Evalúa el resultado mediante un operador corto; si hay datos los devuelve, de lo contrario retorna estrictamente null.
        return $user ?: null;
    }
}
?>