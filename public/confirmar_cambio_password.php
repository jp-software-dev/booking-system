<?php

/**
 * VISTA: CONFIRMACIÓN DE CAMBIO DE CONTRASEÑA
 *
 * Se llega desde el enlace que envía api/solicitar_cambio_password.php.
 *
 * - GET:  muestra un botón "Confirmar cambio" si el token es válido. No aplica
 *         nada: los antivirus y filtros de correo abren los enlaces
 *         automáticamente, y si el GET aplicara el cambio, la confirmación por
 *         correo dejaría de proteger contra quien tenga la sesión robada.
 * - POST: (botón, con token CSRF) aplica la contraseña nueva pendiente.
 *
 * No requiere sesión: el usuario puede confirmar desde otro dispositivo.
 *
 * Acceso: público (el token del correo es la autorización).
 */

require_once __DIR__ . '/../config/config.php';

use App\Core\Database;
use App\Helpers\Bitacora;
use App\Helpers\CsrfHelper;
use App\Http\Sesion;
use App\Models\Paciente;
use App\Models\TokenPassword;

$fuente = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? $_POST : $_GET;
$token = is_string($fuente['token'] ?? null) ? trim($fuente['token']) : '';

// Estado de la página: 'confirmar' (muestra el botón), 'aplicado' o 'invalido'.
$estado = 'invalido';

if ($token !== '' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    // === POST: APLICAR EL CAMBIO ===
    if (CsrfHelper::validateToken(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            $pendiente = (new TokenPassword($db))->consumirCambioPendiente($token);
            if ($pendiente !== null) {
                (new Paciente($db))->actualizarHash($pendiente['id_paciente'], $pendiente['nuevo_hash']);
                $estado = 'aplicado';
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('Error en confirmar_cambio_password.php: ' . $e->getMessage());
            $estado = 'invalido';
        }

        // Fuera de la transacción: un fallo de bitácora no debe deshacer el cambio ya aplicado.
        if ($estado === 'aplicado') {
            Bitacora::registrarComo($pendiente['id_paciente'], Sesion::ROL_PACIENTE, 'cambiar_contrasena_confirmado', 'paciente', $pendiente['id_paciente']);
        }
    }
} elseif ($token !== '' && (new TokenPassword())->cambioPendienteVigente($token)) {
    // === GET: SOLO MOSTRAR EL BOTÓN DE CONFIRMACIÓN ===
    $estado = 'confirmar';
}

require __DIR__ . '/../src/views/layout/header.php';
?>
<div class="d-flex align-items-center justify-content-center auth-bg py-5" style="min-height: 85vh;">
    <div class="card auth-card p-5 w-100 text-center" style="max-width: 480px;">
        <?php if ($estado === 'confirmar'): ?>
            <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3 mx-auto" style="width: 64px; height: 64px;">
                <i class="bi bi-shield-lock text-primary fs-2"></i>
            </div>
            <h3 class="fw-bold text-dark-blue mb-2">Confirma tu cambio de contraseña</h3>
            <p class="text-secondary mb-4">Si fuiste tú quien solicitó el cambio, confírmalo. Si no, cierra esta página: tu contraseña actual seguirá funcionando.</p>
            <form method="POST">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <button type="submit" class="btn btn-primary w-100 btn-lg fw-bold">Confirmar cambio</button>
            </form>
        <?php elseif ($estado === 'aplicado'): ?>
            <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3 mx-auto" style="width: 64px; height: 64px;">
                <i class="bi bi-check-lg text-success fs-2"></i>
            </div>
            <h3 class="fw-bold text-dark-blue mb-2">Contraseña actualizada</h3>
            <p class="text-secondary mb-4">Tu contraseña se actualizó correctamente. Ya puedes iniciar sesión con ella.</p>
            <a href="login.php" class="btn btn-primary w-100 btn-lg fw-bold">Ir a iniciar sesión</a>
        <?php else: ?>
            <div class="bg-danger bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3 mx-auto" style="width: 64px; height: 64px;">
                <i class="bi bi-x-lg text-danger fs-2"></i>
            </div>
            <h3 class="fw-bold text-dark-blue mb-2">Enlace inválido</h3>
            <p class="text-secondary mb-4">El enlace de confirmación es inválido, ya se usó o venció. Tu contraseña actual sigue funcionando; puedes solicitar el cambio de nuevo desde tu cuenta.</p>
            <a href="login.php" class="btn btn-outline-primary w-100 btn-lg fw-bold">Ir a iniciar sesión</a>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../src/views/layout/footer.php'; ?>
