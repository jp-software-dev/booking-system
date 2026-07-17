<?php
/**
 * VALIDADOR DE PETICIONES DE CITAS
 *
 * Clase que centraliza la lógica de validación para las operaciones
 * del controlador de citas (AppointmentController).
 *
 * @namespace Helpers
 */

namespace Helpers;

require_once __DIR__ . '/../Helpers/ValidationHelper.php';

use Helpers\ValidationHelper;

class AppointmentValidator
{
    /**
     * Valida los datos para la creación de una nueva cita.
     *
     * @param array $data Datos de la petición.
     * @param string $sessionRole Rol del usuario que realiza la petición.
     * @return array Arreglo de error o un arreglo vacío si la validación es exitosa.
     */
    public static function validate(array $data, string $sessionRole): array
    {
        if ($sessionRole === 'admin' && empty($data['id_paciente'])) {
            return ['status' => 'error', 'message' => 'id_paciente es obligatorio para el administrador'];
        }

        if (empty($data['id_doctor']) || empty($data['fecha_cita']) || empty($data['hora_inicio'])) {
            return ['status' => 'error', 'message' => 'Faltan datos obligatorios (médico, fecha u hora)'];
        }

        if (!ValidationHelper::validateDate($data['fecha_cita'])) {
            return ['status' => 'error', 'message' => 'Formato de fecha inválido'];
        }
        
        if (!ValidationHelper::validateTime(substr($data['hora_inicio'], 0, 5))) {
            return ['status' => 'error', 'message' => 'Formato de hora inválido'];
        }

        return []; // No hay errores
    }
}