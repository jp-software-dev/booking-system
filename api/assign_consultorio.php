<?php

/**
 * ENDPOINT: ASIGNAR MÉDICO A UN CONSULTORIO (ADMIN)
 *
 * Asigna un médico activo al consultorio (reemplazando al anterior, si lo hay)
 * o lo deja libre si no se envía médico.
 *
 * Método:    POST (JSON)
 * Acceso:    administrador, con token CSRF
 * Entrada:   { id_consultorio, id_doctor (vacío = liberar) }
 * Respuesta: { status, message }
 */

require_once __DIR__ . '/../config/config.php';

use App\Core\Database;
use App\Helpers\Bitacora;
use App\Http\Api;
use App\Http\Sesion;
use App\Models\Consultorio;
use App\Models\Doctor;

Api::iniciar('POST');
Api::exigirSesion(Sesion::ROL_ADMIN);
$entrada = Api::leerJson();
Api::exigirCsrf($entrada);

$idConsultorio = (int) ($entrada['id_consultorio'] ?? 0);
$idDoctor = !empty($entrada['id_doctor']) ? (int) $entrada['id_doctor'] : null;

if ($idConsultorio <= 0) {
    Api::error('Consultorio no especificado');
}

$db = Database::getInstance();
$consultorios = new Consultorio($db);

if ($consultorios->obtenerPorId($idConsultorio) === null) {
    Api::error('El consultorio no existe', 404);
}

// === VALIDACIÓN DEL MÉDICO ===
if ($idDoctor !== null) {
    $doctor = (new Doctor($db))->obtenerPorId($idDoctor);
    if ($doctor === null || $doctor['estado'] !== Doctor::ESTADO_ACTIVO) {
        Api::error('El médico seleccionado no está activo');
    }

    $error = $consultorios->validarAsignacion($idConsultorio, (int) $doctor['id_especialidad'], $idDoctor, true);
    if ($error !== null) {
        Api::error($error);
    }
}

// === ASIGNACIÓN (DESASIGNAR + ASIGNAR EN UNA TRANSACCIÓN) ===
$db->beginTransaction();
try {
    $consultorios->asignarDoctor($idConsultorio, $idDoctor);
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}

Bitacora::registrar(
    'reasignar_consultorio',
    'consultorio',
    $idConsultorio,
    $idDoctor !== null ? "Doctor #{$idDoctor} asignado" : 'Consultorio liberado'
);

Api::exito('Operación realizada con éxito');
