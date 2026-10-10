<?php

/**
 * ENDPOINT: EDICIÓN DE PERSONAL (ADMIN)
 *
 * Actualiza los datos de un médico activo o de un enfermero.
 *
 * Método:    POST (JSON)
 * Acceso:    administrador, con token CSRF
 * Entrada:   { id, tipo: doctor|enfermero, nombre, apellido_paterno,
 *              id_especialidad (médico), turno (enfermero), id_consultorio (opcional) }
 * Respuesta: { status, message }
 */

require_once __DIR__ . '/../config/config.php';

use App\Helpers\Bitacora;
use App\Helpers\PersonalValidator;
use App\Http\Api;
use App\Http\Sesion;
use App\Models\Doctor;
use App\Models\Personal;

Api::iniciar('POST');
Api::exigirSesion(Sesion::ROL_ADMIN);
$entrada = Api::leerJson();
Api::exigirCsrf($entrada);

$id = (int) ($entrada['id'] ?? 0);
if ($id <= 0) {
    Api::error('Miembro del personal no especificado');
}

$personal = new Personal();
$tipo = strtolower(trim((string) ($entrada['tipo'] ?? '')));

if (!in_array($tipo, [Personal::TIPO_DOCTOR, Personal::TIPO_ENFERMERO], true)) {
    Api::error('Tipo de personal inválido');
}

// Se comprueba la existencia antes de actualizar: si se guarda sin cambios,
// rowCount() del UPDATE sería 0 y no serviría para detectar un id inexistente.
if (!$personal->existe($tipo, $id)) {
    Api::error('No se encontró al miembro del personal', 404);
}

// Consultorio actual del médico, para no revalidar la ocupación si no cambia.
$consultorioActual = null;
if ($tipo === Personal::TIPO_DOCTOR) {
    $actual = (new Doctor())->obtenerPorId($id);
    $consultorioActual = $actual['id_consultorio'] ?? null;
}

[$error, $datos] = PersonalValidator::validar($entrada, $id, $consultorioActual !== null ? (int) $consultorioActual : null);
if ($error !== null) {
    Api::error($error);
}

if ($datos['tipo'] === Personal::TIPO_DOCTOR) {
    $personal->actualizarDoctor($id, $datos['nombre'], $datos['apellido'], (int) $datos['id_especialidad'], $datos['id_consultorio']);
} else {
    $personal->actualizarEnfermero($id, $datos['nombre'], $datos['apellido'], (string) $datos['turno'], $datos['id_consultorio']);
}

Bitacora::registrar('modificar_personal', $datos['tipo'], $id, $datos['nombre'] . ' ' . $datos['apellido']);

Api::exito('Datos actualizados con éxito');
