<?php

/**
 * ENDPOINT: ACCESO DE UN MÉDICO A LA PLATAFORMA (ADMIN)
 *
 * Crea la cuenta de un médico, le asigna una contraseña nueva o le quita el
 * acceso. Quitar el acceso no borra al médico ni su historial.
 *
 * Método:    POST (JSON)
 * Acceso:    administrador, con token CSRF
 * Entrada:   { id_doctor, accion: crear|password|quitar, usuario (crear), password (crear y password) }
 * Respuesta: { status, message }
 *
 * En modo demo la cuenta del médico de prueba no se puede modificar ni quitar.
 */

require_once __DIR__ . '/../config/config.php';

use App\Demo\ModoDemo;
use App\Helpers\Bitacora;
use App\Http\Api;
use App\Http\Sesion;
use App\Models\CuentaDoctor;
use App\Models\Doctor;

Api::iniciar('POST');
Api::exigirSesion(Sesion::ROL_ADMIN);
$entrada = Api::leerJson();
Api::exigirCsrf($entrada);

$idDoctor = filter_var($entrada['id_doctor'] ?? null, FILTER_VALIDATE_INT);
$doctor = $idDoctor !== false ? (new Doctor())->obtenerPorId((int) $idDoctor) : null;
if ($doctor === null) {
    Api::error('Médico no encontrado', 404);
}

$idDoctor = (int) $idDoctor;
$accion = (string) ($entrada['accion'] ?? '');
$cuentas = new CuentaDoctor();
$usuarioActual = $cuentas->usuarioDe($idDoctor);
$nombreDoctor = $doctor['nombre'] . ' ' . $doctor['apellido_paterno'];

/** Valida la contraseña recibida y devuelve su hash. */
$hashDePassword = static function () use ($entrada): string {
    $password = (string) ($entrada['password'] ?? '');
    if (strlen($password) < 8 || strlen($password) > 200) {
        Api::error('La contraseña debe tener al menos 8 caracteres');
    }

    return password_hash($password, PASSWORD_DEFAULT);
};

// La cuenta del médico de prueba es compartida en la demo pública.
if (ModoDemo::activo() && $usuarioActual === ModoDemo::DOCTOR_USUARIO && $accion !== 'crear') {
    Api::error(ModoDemo::MENSAJE_BLOQUEO, 403);
}

switch ($accion) {
    case 'crear':
        if ($usuarioActual !== null) {
            Api::error("{$nombreDoctor} ya tiene acceso con el usuario {$usuarioActual}", 409);
        }
        if ($doctor['estado'] !== 'Activo') {
            Api::error('Solo los médicos activos pueden tener acceso');
        }

        $usuario = strtolower(trim((string) ($entrada['usuario'] ?? '')));
        if (preg_match('/^[a-z0-9._-]{4,30}$/', $usuario) !== 1) {
            Api::error('El usuario debe tener de 4 a 30 caracteres: letras, números, punto, guion o guion bajo');
        }
        if ($cuentas->usuarioOcupado($usuario)) {
            Api::error('Ese usuario ya está en uso', 409);
        }

        $cuentas->crear($idDoctor, $usuario, 'Dr(a). ' . $nombreDoctor, $hashDePassword());
        Bitacora::registrar('crear_acceso_doctor', 'doctor', $idDoctor, "{$nombreDoctor} ({$usuario})");
        Api::exito("Acceso creado: {$nombreDoctor} ya puede entrar con el usuario {$usuario}");

    case 'password':
        if ($usuarioActual === null) {
            Api::error('Este médico todavía no tiene acceso', 404);
        }
        $cuentas->cambiarPassword($idDoctor, $hashDePassword());
        Bitacora::registrar('cambiar_password_doctor', 'doctor', $idDoctor, $nombreDoctor);
        Api::exito("Contraseña actualizada para {$nombreDoctor}");

    case 'quitar':
        if ($usuarioActual === null) {
            Api::error('Este médico no tiene acceso', 404);
        }
        $cuentas->eliminar($idDoctor);
        Bitacora::registrar('quitar_acceso_doctor', 'doctor', $idDoctor, "{$nombreDoctor} ({$usuarioActual})");
        Api::exito("Se quitó el acceso de {$nombreDoctor}");

    default:
        Api::error('Acción no válida');
}
