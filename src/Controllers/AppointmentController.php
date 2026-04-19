<?php
/**
 * CONTROLADOR DE CITAS
 *
 * Clase que encapsula la lógica de negocio para validar, procesar y eliminar citas,
 * separando la interfaz de usuario de la interacción con la base de datos.
 *
 * @requires config/Database.php
 * @requires src/Models/Cita.php
 */

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../Models/Cita.php';

class AppointmentController {
    
    private $db;
    private $citaModel;

    public function __construct($dbConnection = null) {
        if ($dbConnection === null) {
            $this->db = Database::getInstance();
        } else {
            $this->db = $dbConnection;
        }
        $this->citaModel = new Cita($this->db);
    }

    // MÉTODO DE CREACIÓN: Centraliza las reglas de negocio y validaciones antes de registrar una nueva cita.
    public function create(array $data): array {
        
        // VALIDACIÓN DE CAMPOS: Verifica que los identificadores y tiempos no estén vacíos.
        if (empty($data['id_paciente']) || empty($data['id_doctor']) || empty($data['fecha_cita']) || empty($data['hora_inicio'])) {
            return ['status' => 'error', 'message' => 'Faltan datos obligatorios'];
        }

        if (!DateTime::createFromFormat('Y-m-d', $data['fecha_cita'])) {
            return ['status' => 'error', 'message' => 'Fecha inválida'];
        }

        if (!DateTime::createFromFormat('H:i', $data['hora_inicio'])) {
            return ['status' => 'error', 'message' => 'Hora inválida (use HH:MM)'];
        }

        try {
            // VERIFICACIÓN DE DISPONIBILIDAD: Consulta si el doctor tiene el horario libre.
            $disponible = $this->citaModel->verificarDisponibilidad($data['id_doctor'], $data['fecha_cita'], $data['hora_inicio']);
            
            if (!$disponible) {
                return ['status' => 'error', 'message' => 'El horario ya no está disponible'];
            }

            $resultado = $this->citaModel->agendar($data);
            
            if ($resultado) {
                return ['status' => 'success', 'message' => 'Cita creada exitosamente'];
            } else {
                return ['status' => 'error', 'message' => 'No se pudo crear la cita'];
            }
            
        } catch (Exception $e) {
            error_log("Error en AppointmentController::create: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Error interno del servidor'];
        }
    }

    // MÉTODO DE BORRADO FÍSICO: Gestiona la solicitud para eliminar permanentemente un registro.
    public function delete(int $id_cita): array {
        
        if ($id_cita <= 0) {
            return ['status' => 'error', 'message' => 'ID de cita inválido'];
        }

        try {
            $eliminado = $this->citaModel->eliminar($id_cita);
            
            if ($eliminado) {
                return ['status' => 'success', 'message' => 'Cita eliminada correctamente'];
            } else {
                return ['status' => 'error', 'message' => 'Cita no encontrada'];
            }
            
        } catch (Exception $e) {
            error_log("Error en AppointmentController::delete: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Error interno del servidor'];
        }
    }
}
?>