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
require_once __DIR__ . '/../Models/Doctor.php';
require_once __DIR__ . '/../Helpers/AppointmentValidator.php';

use Helpers\AppointmentValidator;

class AppointmentController {

    private $db;
    private $citaModel;
    private $doctorModel;

    public function __construct($dbConnection = null) {
        if ($dbConnection === null) {
            $this->db = Database::getInstance();
        } else {
            $this->db = $dbConnection;
        }
        $this->citaModel = new Cita($this->db);
        $this->doctorModel = new Doctor($this->db);
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
        }

        // VALIDACIÓN DE DATOS: Se delega la validación a la clase especializada.
        $validationResult = AppointmentValidator::validate($data, $sessionRole);
        if (!empty($validationResult)) {
            return $validationResult;
        }

        // CONSULTORIO: se resuelve en el servidor a partir del doctor seleccionado, nunca se
        // confía en un id_consultorio enviado por el cliente (ninguno de los formularios lo envía).
        $idConsultorio = $this->doctorModel->obtenerConsultorioAsignado((int)$data['id_doctor']);
        if ($idConsultorio === null) {
            return ['status' => 'error', 'message' => 'El médico seleccionado no tiene un consultorio asignado actualmente'];
        }
        $data['id_consultorio'] = $idConsultorio;

        try {
            $this->db->beginTransaction();

            $disponible = $this->citaModel->verificarDisponibilidad((int)$data['id_doctor'], $data['fecha_cita'], $data['hora_inicio']);

            if (!$disponible) {
                $this->db->rollBack();
                return ['status' => 'error', 'message' => 'El horario ya no está disponible'];
            }
            $resultado = $this->citaModel->agendar($data);

            $this->db->commit();

            if ($resultado) {
                return ['status' => 'success', 'message' => 'Cita creada exitosamente'];
            } else {
                return ['status' => 'error', 'message' => 'No se pudo crear la cita'];
            }

        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            // SQLSTATE 23000: violación de la restricción única de slot (empalme concurrente).
            if ($e->getCode() == 23000) {
                return ['status' => 'error', 'message' => 'El horario seleccionado acaba de ser ocupado por otro paciente. Intenta con otro horario.'];
            }
            error_log("Error en AppointmentController::create: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Error interno del servidor'];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
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