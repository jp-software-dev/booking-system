<?php

/**
 * ENDPOINT: BAJA DE PERSONAL (ADMIN)
 *
 * - Médico: baja lógica (queda 'Inactivo' y sin consultorio). Se rechaza si
 *   tiene citas futuras pendientes o confirmadas, para no dejar pacientes con
 *   citas de un médico que ya no atiende.
 * - Enfermero: borrado físico (no tiene citas asociadas).
 *
 * Método:    POST (JSON)
 * Acceso:    administrador, con token CSRF
 * Entrada:   { id, tipo: doctor|enfermero }
 * Respuesta: { status, message }
 */

require_once __DIR__ . '/../config/config.php';

use App\Helpers\Bitacora;
use App\Http\Api;
use App\Http\Sesion;
use App\Models\Cita;
use App\Models\Personal;

Api::iniciar('POST');
Api::exigirSesion(Sesion::ROL_ADMIN);
$entrada = Api::leerJson();
Api::exigirCsrf($entrada);

$id = (int) ($entrada['id'] ?? 0);
$tipo = strtolower(trim((string) ($entrada['tipo'] ?? '')));

if ($id <= 0 || !in_array($tipo, [Personal::TIPO_DOCTOR, Personal::TIPO_ENFERMERO], true)) {
    Api::error('Datos inválidos');
}

$personal = new Personal();

if ($tipo === Personal::TIPO_DOCTOR) {
    $pendientes = (new Cita())->contarFuturasActivasPorDoctor($id);
    if ($pendientes > 0) {
        Api::error(
            "El médico tiene {$pendientes} cita(s) futuras pendientes o confirmadas. Reprográmalas o cancélalas desde el Panel de Citas antes de darlo de baja.",
            409
        );
    }

    $eliminado = $personal->darDeBajaDoctor($id);
} else {
    $eliminado = $personal->eliminarEnfermero($id);
}

if (!$eliminado) {
    Api::error('No se encontró al miembro del personal', 404);
}

Bitacora::registrar('dar_de_baja_personal', $tipo, $id);

Api::exito('Baja procesada exitosamente');
