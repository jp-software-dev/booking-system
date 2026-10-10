<?php

/**
 * ENDPOINT: QUITAR UNA CITA DEL HISTORIAL (PACIENTE)
 *
 * Oculta una cita completada o cancelada de la vista del paciente. No es un
 * borrado: la fila se conserva para el administrador y las métricas
 * (columna oculto_para_paciente = 1).
 *
 * Método:    POST (JSON)
 * Acceso:    paciente autenticado, con token CSRF
 * Entrada:   { id_cita }
 * Respuesta: { status, message }
 */

require_once __DIR__ . '/../config/config.php';

use App\Http\Api;
use App\Http\Sesion;
use App\Models\Cita;

Api::iniciar('POST');
$idPaciente = Api::exigirSesion(Sesion::ROL_PACIENTE);
$entrada = Api::leerJson();
Api::exigirCsrf($entrada);

$idCita = (int) ($entrada['id_cita'] ?? 0);
if ($idCita <= 0) {
    Api::error('Cita no especificada');
}

$error = (new Cita())->ocultarParaPaciente($idCita, $idPaciente);
if ($error !== null) {
    Api::error($error, $error === 'Cita no encontrada' ? 404 : 409);
}

Api::exito('Registro eliminado de tu historial');
