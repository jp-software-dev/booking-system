<?php

/**
 * ENDPOINT: EMITIR RECETA (DOCTOR)
 *
 * El médico emite una receta en una de sus citas. Cada medicamento se guarda
 * como un tratamiento con sus tomas calculadas, así que al recetar quedan
 * programados los recordatorios del paciente.
 *
 * Método:    POST (JSON)
 * Acceso:    doctor, con token CSRF; solo en citas propias que no estén canceladas
 * Entrada:   { id_cita, diagnostico, indicaciones,
 *              medicamentos: [{ medicamento, dosis, frecuencia_horas, duracion_dias, fecha_inicio, notas }] }
 * Respuesta: { status, message, id_receta, folio }
 */

require_once __DIR__ . '/../config/config.php';

use App\Core\Database;
use App\Helpers\Bitacora;
use App\Helpers\ValidadorTratamiento;
use App\Http\Api;
use App\Http\Sesion;
use App\Models\Cita;
use App\Models\Receta;
use App\Models\Tratamiento;

/** Medicamentos máximos por receta. */
const MAX_MEDICAMENTOS = 10;

Api::iniciar('POST');
Api::exigirSesion(Sesion::ROL_DOCTOR);
$entrada = Api::leerJson();
Api::exigirCsrf($entrada);

$idDoctor = Sesion::doctorId();
if ($idDoctor === null) {
    Api::error('Tu cuenta no está ligada a un médico', 403);
}

// === CITA ===
$idCita = filter_var($entrada['id_cita'] ?? null, FILTER_VALIDATE_INT);
$cita = $idCita !== false ? (new Cita())->obtenerDeDoctor((int) $idCita, $idDoctor) : null;
if ($cita === null) {
    Api::error('Cita no encontrada', 404);
}
if ($cita['estado_cita'] === 'Cancelada') {
    Api::error('No se puede recetar en una cita cancelada', 409);
}

// === DATOS DE LA RECETA ===
$diagnostico = trim((string) ($entrada['diagnostico'] ?? ''));
$indicaciones = trim((string) ($entrada['indicaciones'] ?? ''));

if (mb_strlen($diagnostico) < 3 || mb_strlen($diagnostico) > 255) {
    Api::error('Escribe el diagnóstico (3 a 255 caracteres)');
}
if (mb_strlen($indicaciones) > 2000) {
    Api::error('Las indicaciones no pueden pasar de 2000 caracteres');
}

$listaEntrada = is_array($entrada['medicamentos'] ?? null) ? array_values($entrada['medicamentos']) : [];
if ($listaEntrada === [] || count($listaEntrada) > MAX_MEDICAMENTOS) {
    Api::error('La receta debe tener de 1 a ' . MAX_MEDICAMENTOS . ' medicamentos');
}

$medicamentos = [];
foreach ($listaEntrada as $item) {
    [$datos, $error] = ValidadorTratamiento::validar(is_array($item) ? $item : []);
    if ($error !== null) {
        Api::error($error);
    }
    $medicamentos[] = $datos;
}

// === ALTA (UNA SOLA TRANSACCIÓN) ===
$db = Database::getInstance();
$db->beginTransaction();

try {
    $idPaciente = (int) $cita['id_paciente'];
    $idReceta = (new Receta($db))->crear($idPaciente, $idDoctor, (int) $cita['id_cita'], $diagnostico, $indicaciones !== '' ? $indicaciones : null);

    $tratamientos = new Tratamiento($db);
    foreach ($medicamentos as $datos) {
        $tratamientos->crear([
            'id_paciente' => $idPaciente,
            'id_receta'   => $idReceta,
            'origen'      => Tratamiento::ORIGEN_DOCTOR,
        ] + $datos);
    }

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $e;
}

$paciente = $cita['paciente_nombre'] . ' ' . $cita['paciente_apellido'];
Bitacora::registrar('emitir_receta', 'receta', $idReceta, sprintf('%s · %d medicamento(s)', $paciente, count($medicamentos)));

Api::exito('Receta emitida. Los recordatorios ya están programados para ' . $cita['paciente_nombre'] . '.', [
    'id_receta' => $idReceta,
    'folio'     => Receta::folio($idReceta),
]);
