<?php

/**
 * ENDPOINT: BAJA DE CUENTA (PACIENTE)
 *
 * El paciente elimina su propia cuenta. Se aplica borrado lógico con
 * anonimización (ver Paciente::anonimizar()) y se cancelan sus citas futuras
 * para liberar esos horarios. Las citas pasadas se conservan para el historial
 * administrativo y las métricas, ya sin datos que identifiquen a la persona.
 *
 * Método:    POST (JSON)
 * Acceso:    paciente autenticado, con token CSRF
 * Entrada:   { confirmar: true, password }
 * Respuesta: { status, message, redirect }
 */

require_once __DIR__ . '/../config/config.php';

use App\Core\Database;
use App\Helpers\Bitacora;
use App\Http\Api;
use App\Http\Sesion;
use App\Models\Cita;
use App\Models\Paciente;

Api::iniciar('POST');
$idPaciente = Api::exigirSesion(Sesion::ROL_PACIENTE);
$entrada = Api::leerJson();
Api::exigirCsrf($entrada);

// Confirmación explícita (casilla del modal) y contraseña, por ser una acción irreversible.
if (empty($entrada['confirmar'])) {
    Api::error('Confirmación requerida');
}

$db = Database::getInstance();
$pacientes = new Paciente($db);

$hash = $pacientes->obtenerHash($idPaciente);
if ($hash === null || !password_verify((string) ($entrada['password'] ?? ''), $hash)) {
    Api::error('La contraseña es incorrecta');
}

$db->beginTransaction();

try {
    if (!$pacientes->anonimizar($idPaciente)) {
        $db->rollBack();
        Api::error('Cuenta no encontrada', 404);
    }

    $citasCanceladas = (new Cita($db))->cancelarFuturasPorPaciente($idPaciente);

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $e;
}

// La bitácora se registra antes de cerrar la sesión, porque toma de ella el usuario.
Bitacora::registrar('eliminar_cuenta_paciente', 'paciente', $idPaciente, "Autogestión: baja de cuenta. Citas futuras canceladas: {$citasCanceladas}");
Sesion::cerrar();

Api::exito('Tu cuenta ha sido eliminada.', ['redirect' => 'index.php']);
