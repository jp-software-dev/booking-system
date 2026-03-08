<?php
// CLASE MODELO: Define la estructura orientada a objetos para gestionar de forma segura los datos de los pacientes en la base de datos.
class Paciente {
    
    // PROPIEDAD PRIVADA: Encapsula la conexión a la base de datos (PDO) para evitar modificaciones externas no autorizadas.
    private PDO $conn;

    // CONSTRUCTOR: Inicializa la clase recibiendo una conexión activa a la base de datos mediante inyección de dependencias.
    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    // MÉTODO DE BÚSQUEDA: Función tipada que recibe un ID entero y devuelve los datos del paciente o nulo si no lo encuentra.
    public function obtenerPorId(int $id): ?array {
        
        // CONSULTA PREPARADA: Selecciona la información esencial del paciente sin exponer datos sensibles innecesarios.
        $query = "SELECT id_paciente, nombre, email FROM pacientes WHERE id_paciente = ?";
        
        // PREPARACIÓN: Pre-compila la sentencia SQL en el motor de la base de datos para neutralizar ataques de inyección (SQLi).
        $stmt = $this->conn->prepare($query);
        
        // EJECUCIÓN SEGURA: Reemplaza el marcador de posición (?) con el ID proporcionado antes de ejecutar la consulta.
        $stmt->execute([$id]);
        
        // EXTRACCIÓN: Recupera el registro coincidente de la base de datos y lo formatea como un arreglo asociativo limpio.
        $paciente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // RETORNO CONDICIONAL: Evalúa el resultado extraído; si contiene datos los devuelve, de lo contrario retorna un valor nulo estricto.
        return $paciente ?: null;
    }

    // VERIFICADOR DE DUPLICADOS: Función booleana que comprueba si un correo electrónico ya está registrado en el sistema.
    public function existeEmail(string $email): bool {
        
        // CONSULTA DE VERIFICACIÓN: Busca únicamente el ID asociado al correo para optimizar la velocidad y rendimiento de la consulta.
        $query = "SELECT id_paciente FROM pacientes WHERE email = ?";
        
        // PREPARACIÓN: Protege la consulta contra inyecciones SQL asegurando que el string del email no rompa la estructura de la base de datos.
        $stmt = $this->conn->prepare($query);
        
        // EJECUCIÓN: Pasa el correo proporcionado de forma segura como parámetro al motor de MySQL.
        $stmt->execute([$email]);
        
        // EVALUACIÓN BOOLEANA: Cuenta las filas encontradas; retorna verdadero (true) si hay coincidencias, ayudando a prevenir registros duplicados.
        return $stmt->rowCount() > 0;
    }
}
?>