<?php
// CLASE MODELO: Define la estructura orientada a objetos para gestionar la información de los médicos en la base de datos.
class Doctor {
    
    // PROPIEDAD PRIVADA: Encapsula la conexión PDO para evitar que sea alterada o manipulada directamente desde fuera de la clase.
    private PDO $conn;

    // CONSTRUCTOR: Inicializa la instancia del modelo recibiendo e inyectando la conexión activa a la base de datos.
    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    // MÉTODO DE LECTURA: Función tipada que retorna estrictamente un arreglo con los datos de los doctores disponibles para atender citas.
    public function listarActivos(): array {
        
        // CONSULTA SQL: Filtra específicamente a los médicos cuyo campo 'estado' sea 1 (activos) y los ordena alfabéticamente por nombre.
        $query = "SELECT id_doctor, nombre, apellido_paterno 
                  FROM doctores 
                  WHERE estado = 1 
                  ORDER BY nombre ASC";
                  
        // EJECUCIÓN DIRECTA: Lanza la instrucción a la base de datos de forma directa, ya que no intervienen variables externas que requieran sanitización.
        $stmt = $this->conn->query($query);
        
        // EXTRACCIÓN Y RETORNO: Recupera todos los registros devueltos por MySQL y los exporta inmediatamente como un arreglo asociativo limpio.
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>