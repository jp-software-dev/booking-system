<?php
// CLASE MODELO: Define la estructura y los métodos principales para gestionar todo el ciclo de vida de las citas médicas en la base de datos.
class Cita {
    
    // PROPIEDAD PRIVADA: Encapsula la conexión a la base de datos (PDO) garantizando que solo esta clase pueda interactuar con ella.
    private PDO $conn;

    // CONSTRUCTOR: Inicializa el modelo inyectando la dependencia de conexión a la base de datos para poder ejecutar las consultas.
    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    // VERIFICADOR DE EMPALMES: Consulta si un doctor ya tiene un espacio ocupado en esa fecha y hora, ignorando inteligentemente las citas canceladas.
    public function verificarDisponibilidad(int $id_doctor, string $fecha, string $hora): bool {
        
        // CONSULTA CONDICIONAL: Selecciona el ID de la cita buscando coincidencias exactas de tiempo y excluyendo los estados inactivos.
        $query = "SELECT id_cita FROM citas WHERE id_doctor = ? AND fecha_cita = ? AND hora_inicio = ? AND estado_cita != 'Cancelada'";
        
        // PREPARACIÓN SEGURA: Pre-compila la instrucción SQL en el motor de base de datos para evitar cualquier riesgo de inyección (SQLi).
        $stmt = $this->conn->prepare($query);
        
        // EJECUCIÓN PARAMETRIZADA: Pasa los datos de forma estructurada para que reemplacen los marcadores de posición sin romper la sintaxis.
        $stmt->execute([$id_doctor, $fecha, $hora]);
        
        // RETORNO BOOLEANO: Evalúa el conteo de filas; si es estrictamente igual a cero (0), significa que el horario está completamente libre (true).
        return $stmt->rowCount() === 0;
    }

    // CREADOR DE CITAS: Recibe un arreglo de información validada e inserta un nuevo registro en la base de datos con el estado inicial en 'Pendiente'.
    public function agendar(array $data): bool {
        
        // INSERCIÓN PREPARADA: Define los campos a llenar dejando que la base de datos maneje el ID autoincremental y los timestamps.
        $query = "INSERT INTO citas (id_paciente, id_doctor, fecha_cita, hora_inicio, motivo_consulta, estado_cita) 
                  VALUES (?, ?, ?, ?, ?, 'Pendiente')";
                  
        $stmt = $this->conn->prepare($query);
        
        // EJECUCIÓN MAPEADA: Ejecuta la consulta mapeando cada valor del arreglo (aplicando un fallback de texto vacío si no hay motivo).
        return $stmt->execute([
            $data['id_paciente'],
            $data['id_doctor'],
            $data['fecha_cita'],
            $data['hora_inicio'],
            $data['motivo'] ?? ''
        ]);
    }

    // BORRADO FÍSICO: Ejecuta una eliminación definitiva (Hard Delete) del registro de la base de datos, ideal para la administración del sistema.
    public function eliminar(int $id_cita): bool {
        $query = "DELETE FROM citas WHERE id_cita = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id_cita]);
    }

    // HISTORIAL DE PACIENTE: Extrae todas las citas asociadas a un ID de usuario específico para poblar las tablas del portal del paciente.
    public function obtenerPorPaciente(int $id_paciente): array {
        
        // CONSULTA RELACIONAL: Une la tabla de citas con la de doctores (INNER JOIN) para poder mostrarle al paciente los nombres reales de sus médicos.
        $query = "SELECT c.id_cita, c.fecha_cita, c.hora_inicio, c.motivo_consulta, c.estado_cita,
                         d.nombre AS doctor_nombre, d.apellido_paterno AS doctor_ap
                  FROM citas c
                  INNER JOIN doctores d ON c.id_doctor = d.id_doctor
                  WHERE c.id_paciente = ?
                  ORDER BY c.fecha_cita DESC, c.hora_inicio DESC";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id_paciente]);
        
        // EXTRACCIÓN MASIVA: Devuelve la lista completa de resultados ordenados desde el más reciente hasta el más antiguo en formato de arreglo.
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // BORRADO LÓGICO: Modifica el estado de una cita a 'Cancelada' (Soft Delete) comprobando estrictamente que le pertenezca al paciente solicitante.
    public function cancelar(int $id_cita, int $id_paciente): bool {
        
        // PROTECCIÓN DE PERTENENCIA: Exige que coincidan tanto el ID de la cita como el del paciente para evitar que un usuario cancele citas ajenas.
        $query = "UPDATE citas SET estado_cita = 'Cancelada' WHERE id_cita = ? AND id_paciente = ? AND estado_cita != 'Cancelada'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id_cita, $id_paciente]);
        
        // CONFIRMACIÓN DE ACCIÓN: Verifica si el estado realmente cambió (evita dobles cancelaciones) y retorna verdadero o falso según sea el caso.
        return $stmt->rowCount() > 0;
    }
}
?>