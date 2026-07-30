<?php
/**
 * CONFIRMACIÓN DE CAMBIO DE CONTRASEÑA POR CORREO
 *
 * Se llega aquí desde el enlace enviado por solicitar_cambio_password.php. Valida el
 * token (existe, vigente, no usado), aplica el nuevo_hash pendiente a
 * pacientes_credenciales, marca el token como usado y muestra una página simple de
 * confirmación. No depende de que exista una sesión activa: el usuario puede confirmar
 * desde otro dispositivo o navegador.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

$token = trim($_GET['token'] ?? '');
$db = Database::getInstance();

$resultado = null; // 'ok' | 'invalido'

if (empty($token)) {
    $resultado = 'invalido';
} else {
    try {
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT id, id_paciente, nuevo_hash FROM cambios_password_pendientes
                               WHERE token = ? AND expira > NOW() AND usado = 0 FOR UPDATE");
        $stmt->execute([$token]);
        $pendiente = $stmt->fetch();

        if (!$pendiente) {
            $db->rollBack();
            $resultado = 'invalido';
        } else {
            $update = $db->prepare("UPDATE pacientes_credenciales SET password_hash = ? WHERE id_paciente = ?");
            $update->execute([$pendiente['nuevo_hash'], $pendiente['id_paciente']]);

            $usar = $db->prepare("UPDATE cambios_password_pendientes SET usado = 1 WHERE id = ?");
            $usar->execute([$pendiente['id']]);

            $db->commit();
            $resultado = 'ok';

            // La bitácora se registra fuera de la transacción del cambio de contraseña: un
            // fallo aquí (p.ej. la fila de bitácora vive en un id_usuario que no corresponde
            // a un paciente autenticado, sino que viene del registro pendiente) nunca debe
            // deshacer el cambio de contraseña ya aplicado y confirmado por el usuario.
            try {
                $bitacora = $db->prepare("INSERT INTO bitacora (id_usuario, rol, accion, entidad, id_entidad) VALUES (?, 'paciente', 'cambiar_contrasena_confirmado', 'paciente', ?)");
                $bitacora->execute([$pendiente['id_paciente'], $pendiente['id_paciente']]);
            } catch (Exception $e) {
                error_log("Error al registrar bitácora en confirmar_cambio_password.php: " . $e->getMessage());
            }
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log("Error en confirmar_cambio_password.php: " . $e->getMessage());
        $resultado = 'invalido';
    }
}

require_once __DIR__ . '/../src/views/layout/header.php';
?>
<div class="d-flex align-items-center justify-content-center auth-bg py-5" style="min-height: 85vh;">
    <div class="card auth-card p-5 w-100 text-center" style="max-width: 480px;">
        <?php if ($resultado === 'ok'): ?>
            <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3 mx-auto" style="width:64px;height:64px;">
                <i class="bi bi-check-lg text-success fs-2"></i>
            </div>
            <h3 class="fw-bold text-dark-blue mb-2">Contraseña actualizada</h3>
            <p class="text-secondary mb-4">Tu contraseña fue actualizada correctamente. Ya puedes iniciar sesión con ella.</p>
            <a href="login.php" class="btn btn-primary w-100 btn-lg fw-bold">Ir a iniciar sesión</a>
        <?php else: ?>
            <div class="bg-danger bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3 mx-auto" style="width:64px;height:64px;">
                <i class="bi bi-x-lg text-danger fs-2"></i>
            </div>
            <h3 class="fw-bold text-dark-blue mb-2">Enlace inválido</h3>
            <p class="text-secondary mb-4">El enlace de confirmación es inválido, ya fue utilizado o expiró. Tu contraseña actual sigue funcionando; puedes solicitar el cambio de nuevo desde tu cuenta.</p>
            <a href="login.php" class="btn btn-outline-primary w-100 btn-lg fw-bold">Ir a iniciar sesión</a>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../src/views/layout/footer.php'; ?>
