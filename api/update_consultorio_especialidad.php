<?php

/**
 * ENDPOINT: ESPECIALIDAD PREDETERMINADA DE UN CONSULTORIO (ADMIN)
 *
 * Define o quita la especialidad del consultorio. A partir de ese momento solo
 * pueden asignarse médicos de esa especialidad.
 *
 * Método:    POST (JSON)
 * Acceso:    administrador, con token CSRF
 * Entrada:   { id_consultorio, id_especialidad (vacío = quitar) }
 * Respuesta: { status, message }
 */

require_once __DIR__ . '/../config/config.php';

use App\Helpers\Bitacora;
use App\Http\Api;
use App\Http\Sesion;
use App\Models\Consultorio;
use App\Models\Especialidad;

Api::iniciar('POST');
Api::exigirSesion(Sesion::ROL_ADMIN);
$entrada = Api::leerJson();
Api::exigirCsrf($entrada);

$idConsultorio = (int) ($entrada['id_consultorio'] ?? 0);
$idEspecialidad = !empty($entrada['id_especialidad']) ? (int) $entrada['id_especialidad'] : null;

if ($idConsultorio <= 0) {
    Api::error('Consultorio no especificado');
}

$consultorios = new Consultorio();

if ($consultorios->obtenerPorId($idConsultorio) === null) {
    Api::error('El consultorio no existe', 404);
}

if ($idEspecialidad !== null && !(new Especialidad())->existe($idEspecialidad)) {
    Api::error('Especialidad no válida');
}

$consultorios->actualizarEspecialidad($idConsultorio, $idEspecialidad);

Bitacora::registrar(
    'actualizar_especialidad_consultorio',
    'consultorio',
    $idConsultorio,
    $idEspecialidad !== null ? "Especialidad #{$idEspecialidad}" : 'Especialidad removida'
);

Api::exito('Especialidad actualizada con éxito');
