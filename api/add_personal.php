<?php

/**
 * ENDPOINT: ALTA DE PERSONAL (ADMIN)
 *
 * Registra un médico o un enfermero desde admin_equipo.php.
 *
 * Método:    POST (JSON)
 * Acceso:    administrador, con token CSRF
 * Entrada:   { tipo: doctor|enfermero, nombre, apellido_paterno,
 *              id_especialidad (médico), turno (enfermero), id_consultorio (opcional) }
 * Respuesta: { status, message }
 */

require_once __DIR__ . '/../config/config.php';

use App\Helpers\Bitacora;
use App\Helpers\PersonalValidator;
use App\Http\Api;
use App\Http\Sesion;
use App\Models\Personal;

Api::iniciar('POST');
Api::exigirSesion(Sesion::ROL_ADMIN);
$entrada = Api::leerJson();
Api::exigirCsrf($entrada);

[$error, $datos] = PersonalValidator::validar($entrada);
if ($error !== null) {
    Api::error($error);
}

$personal = new Personal();

$id = $datos['tipo'] === Personal::TIPO_DOCTOR
    ? $personal->agregarDoctor($datos['nombre'], $datos['apellido'], (int) $datos['id_especialidad'], $datos['id_consultorio'])
    : $personal->agregarEnfermero($datos['nombre'], $datos['apellido'], (string) $datos['turno'], $datos['id_consultorio']);

Bitacora::registrar('dar_de_alta_personal', $datos['tipo'], $id, $datos['nombre'] . ' ' . $datos['apellido']);

Api::exito('Personal agregado con éxito');
