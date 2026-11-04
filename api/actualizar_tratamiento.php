<?php

/**
 * ENDPOINT: PAUSAR, REANUDAR O ELIMINAR UN TRATAMIENTO
 *
 * - pausar / reanudar: cualquier tratamiento del paciente. Pausado no envía
 *   avisos, pero sus tomas se conservan.
 * - eliminar: solo los recordatorios que creó el propio paciente. Los
 *   recetados por un médico forman parte de la receta y no se borran (se pueden pausar).
 *   Eliminar es lógico (estado 'Cancelado'), para conservar el historial.
 *
 * Método:    POST (JSON)
 * Acceso:    paciente, con token CSRF; solo sus propios tratamientos
 * Entrada:   { id_tratamiento, accion: pausar|reanudar|eliminar }
 * Respuesta: { status, message, estado }
 */

require_once __DIR__ . '/../config/config.php';

use App\Helpers\Bitacora;
use App\Http\Api;
use App\Http\Sesion;
use App\Models\Tratamiento;

Api::iniciar('POST');
$idPaciente = Api::exigirSesion(Sesion::ROL_PACIENTE);
$entrada = Api::leerJson();
Api::exigirCsrf($entrada);

$tratamientos = new Tratamiento();
$idTratamiento = filter_var($entrada['id_tratamiento'] ?? null, FILTER_VALIDATE_INT);
$tratamiento = $idTratamiento !== false ? $tratamientos->obtenerDePaciente((int) $idTratamiento, $idPaciente) : null;

if ($tratamiento === null || $tratamiento['estado'] === Tratamiento::CANCELADO) {
    Api::error('Tratamiento no encontrado', 404);
}

$accion = (string) ($entrada['accion'] ?? '');
[$nuevoEstado, $mensaje] = match ($accion) {
    'pausar'   => [Tratamiento::PAUSADO, "Pausado: no recibirás avisos de {$tratamiento['medicamento']}"],
    'reanudar' => [Tratamiento::ACTIVO, "Reanudado: volverás a recibir avisos de {$tratamiento['medicamento']}"],
    'eliminar' => [Tratamiento::CANCELADO, "Recordatorio de {$tratamiento['medicamento']} eliminado"],
    default    => Api::error('Acción no válida'),
};

if ($accion === 'eliminar' && $tratamiento['origen'] !== Tratamiento::ORIGEN_PACIENTE) {
    Api::error('Los medicamentos recetados por tu médico no se eliminan; puedes pausar sus avisos.', 403);
}

$tratamientos->cambiarEstado((int) $tratamiento['id_tratamiento'], $nuevoEstado);
Bitacora::registrar($accion . '_tratamiento', 'tratamiento', (int) $tratamiento['id_tratamiento'], (string) $tratamiento['medicamento']);

Api::exito($mensaje, ['estado' => $nuevoEstado]);
