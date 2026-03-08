<?php
// CONTROLADOR DE CITAS: Clase que encapsula la lógica de negocio para validar, procesar y eliminar citas, separando la interfaz de la base de datos.

// DEPENDENCIAS: Carga el núcleo de configuración de la base de datos y el modelo específico de Cita para operar sobre las tablas.
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../Models/Cita.php';

class AppointmentController {
    
    // PROPIEDADES PRIVADAS: Almacenan la instancia de la base de datos y el modelo de Cita aislandolos del exterior.
    private $db;
    private $citaModel;

    // CONSTRUCTOR FLEXIBLE: Permite inyectar una conexión existente (ideal para pruebas) o instancia una nueva por defecto mediante Singleton.
    public function __construct($dbConnection = null) {
        if ($dbConnection === null) {
            $this->db = Database::getInstance();
        } else {
            $this->db = $dbConnection;
        }
        // INICIALIZACIÓN DE MODELO: Instancia la clase Cita inyectándole la conexión de base de datos activa.
        $this->citaModel = new Cita($this->db);
    }

    // MÉTODO DE CREACIÓN: Centraliza las reglas de negocio y validaciones antes de intentar registrar una nueva cita en el sistema.
    public function create(array $data): array {
        
        // VALIDACIÓN DE CAMPOS: Verifica estrictamente que los identificadores y tiempos de la cita no estén vacíos ni sean nulos.
        if (empty($data['id_paciente']) || empty($data['id_doctor']) || empty($data['fecha_cita']) || empty($data['hora_inicio'])) {
            return ['status' => 'error', 'message' => 'Faltan datos obligatorios'];
        }

        // VALIDACIÓN DE FECHA: Asegura mediante la clase nativa DateTime que la fecha proporcionada cumpla con el formato estándar (YYYY-MM-DD).
        if (!DateTime::createFromFormat('Y-m-d', $data['fecha_cita'])) {
            return ['status' => 'error', 'message' => 'Fecha inválida'];
        }

        // VALIDACIÓN DE HORA: Confirma que la hora de inicio respete el formato de 24 horas (HH:MM) para prevenir fallos al guardar.
        if (!DateTime::createFromFormat('H:i', $data['hora_inicio'])) {
            return ['status' => 'error', 'message' => 'Hora inválida (use HH:MM)'];
        }

        try {
            // VERIFICACIÓN DE DISPONIBILIDAD: Delega al modelo la consulta preventiva para confirmar que el doctor seleccionado tenga el horario libre.
            $disponible = $this->citaModel->verificarDisponibilidad($data['id_doctor'], $data['fecha_cita'], $data['hora_inicio']);
            
            // PREVENCIÓN DE EMPALMES: Si el horario está ocupado, detiene el proceso y avisa al usuario inmediatamente.
            if (!$disponible) {
                return ['status' => 'error', 'message' => 'El horario ya no está disponible'];
            }

            // INSERCIÓN DE CITA: Llama al método agendar del modelo para guardar el registro y evalúa el resultado de la operación.
            $resultado = $this->citaModel->agendar($data);
            
            // RESPUESTA DE ESTADO: Retorna un arreglo asociativo indicando éxito o error según la respuesta de la base de datos.
            if ($resultado) {
                return ['status' => 'success', 'message' => 'Cita creada exitosamente'];
            } else {
                return ['status' => 'error', 'message' => 'No se pudo crear la cita'];
            }
            
        } catch (Exception $e) {
            // MANEJO DE ERRORES: Captura excepciones imprevistas, las registra en el log del servidor y devuelve un mensaje seguro para el frontend.
            error_log("Error en AppointmentController::create: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Error interno del servidor'];
        }
    }

    // MÉTODO DE BORRADO FÍSICO: Gestiona la solicitud administrativa para eliminar permanentemente un registro del historial médico.
    public function delete(int $id_cita): array {
        
        // VALIDACIÓN DE ID: Corta la ejecución inmediatamente si el identificador proporcionado es menor o igual a cero (inválido).
        if ($id_cita <= 0) {
            return ['status' => 'error', 'message' => 'ID de cita inválido'];
        }

        try {
            // EJECUCIÓN DE BORRADO: Delega la instrucción SQL al modelo y evalúa si realmente se encontró y eliminó el registro.
            $eliminado = $this->citaModel->eliminar($id_cita);
            
            // RESPUESTA DE ESTADO: Retorna el resultado de la operación al archivo API que lo invocó.
            if ($eliminado) {
                return ['status' => 'success', 'message' => 'Cita eliminada correctamente'];
            } else {
                return ['status' => 'error', 'message' => 'Cita no encontrada'];
            }
            
        } catch (Exception $e) {
            // MANEJO DE ERRORES: Protege el sistema registrando la falla técnica de forma privada y devolviendo un error genérico.
            error_log("Error en AppointmentController::delete: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Error interno del servidor'];
        }
    }
}
?>