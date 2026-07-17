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

    /**
     * @param array  $data          Datos de la cita provenientes del cliente
     * @param int    $sessionUserId ID del usuario autenticado (de $_SESSION['user_id'])
     * @param string $sessionRole   Rol del usuario autenticado ('paciente' | 'admin')
     */
    public function create(array $data, int $sessionUserId, string $sessionRole): array {
        
        // SEGURIDAD: si quien llama no es admin, se ignora cualquier id_paciente
        // enviado desde el cliente y se fuerza al dueño de la sesión.
        if ($sessionRole !== 'admin') {
            $data['id_paciente'] = $sessionUserId;
        } elseif (empty($data['id_paciente'])) {
            return ['status' => 'error', 'message' => 'id_paciente es obligatorio para el administrador'];
        }


        // VALIDACIÓN DE CAMPOS: Verifica que los identificadores y tiempos no estén vacíos.
        if (empty($data['id_paciente']) || empty($data['id_doctor']) || empty($data['fecha_cita']) || empty($data['hora_inicio'])) {
            return ['status' => 'error', 'message' => 'Faltan datos obligatorios'];
        }

        if (!DateTime::createFromFormat('Y-m-d', $data['fecha_cita'])) {
            return ['status' => 'error', 'message' => 'Formato de fecha inválido'];
        }
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $data['hora_inicio'])) {
            return ['status' => 'error', 'message' => 'Formato de hora inválido'];
        }

        try {
            $disponible = $this->citaModel->verificarDisponibilidad((int)$data['id_doctor'], $data['fecha_cita'], $data['hora_inicio']);
            
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

    /**
     * @param int    $id_cita
     * @param string $sessionRole Rol del usuario autenticado — solo admin puede borrar físicamente
     */
    public function delete(int $id_cita, string $sessionRole): array {
        
        if ($sessionRole !== 'admin') {
            return ['status' => 'error', 'message' => 'No autorizado'];
        }

        
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