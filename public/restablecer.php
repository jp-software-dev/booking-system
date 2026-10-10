<?php

/**
 * VISTA: ELEGIR NUEVA CONTRASEÑA
 *
 * Se llega desde el enlace del correo de recuperación (?token=...). Si el token
 * es válido muestra el formulario; si no, un aviso para solicitar otro enlace.
 *
 * Acceso: público (el token del correo es la autorización).
 */

require_once __DIR__ . '/../config/config.php';

use App\Models\TokenPassword;

// === VALIDACIÓN DEL TOKEN ===
// Se hace antes de imprimir cualquier HTML para poder redirigir si falta el token.
$token = is_string($_GET['token'] ?? null) ? trim($_GET['token']) : '';

if ($token === '') {
    header('Location: recuperar.php');
    exit;
}

$tokenValido = (new TokenPassword())->emailDeRestablecimiento($token) !== null;

require __DIR__ . '/../src/views/layout/header.php';
?>
<div class="d-flex align-items-center justify-content-center auth-bg py-5" style="min-height: 85vh;">
    <?php if (!$tokenValido): ?>
        <div class="card auth-card p-5 w-100 text-center" style="max-width: 450px;">
            <h4 class="fw-bold text-danger">Enlace inválido</h4>
            <p class="text-secondary">El enlace venció o ya fue utilizado.</p>
            <a href="recuperar.php" class="btn btn-outline-danger mt-2">Solicitar uno nuevo</a>
        </div>
    <?php else: ?>
        <div class="card auth-card p-5 w-100" style="max-width: 450px;">
            <div class="text-center mb-4"><h3 class="fw-bold text-dark-blue">Nueva Contraseña</h3></div>
            <div id="restablecerAlert"></div>
            <form id="formRestablecer">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="mb-3">
                    <label for="password" class="form-label fw-bold text-secondary">Contraseña nueva</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-lock"></i></span>
                        <input type="password" id="password" name="password" class="form-control border-start-0 ps-0" minlength="8" maxlength="200" autocomplete="new-password" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="confirm_password" class="form-label fw-bold text-secondary">Confirmar contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" id="confirm_password" class="form-control border-start-0 ps-0" minlength="8" maxlength="200" autocomplete="new-password" required>
                    </div>
                </div>
                <button type="submit" id="btnRestablecer" class="btn btn-primary w-100 btn-lg fw-bold">Guardar contraseña</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php if ($tokenValido): ?>
<script>
document.getElementById('formRestablecer').addEventListener('submit', async (evento) => {
    evento.preventDefault();

    const password = document.getElementById('password').value;
    if (password.length < 8) {
        mostrarAlerta('restablecerAlert', 'danger', 'La contraseña debe tener al menos 8 caracteres');
        return;
    }
    if (password !== document.getElementById('confirm_password').value) {
        mostrarAlerta('restablecerAlert', 'danger', 'Las contraseñas no coinciden');
        return;
    }

    const restaurar = bloquearBoton(document.getElementById('btnRestablecer'), 'Procesando...');
    const respuesta = await apiClient.post('restablecer.php', Object.fromEntries(new FormData(evento.target)));

    if (respuesta?.status === 'success') {
        mostrarAlerta('restablecerAlert', 'success', respuesta.message);
        setTimeout(() => { window.location.href = 'login.php'; }, 2000);
        return;
    }

    mostrarAlerta('restablecerAlert', 'danger', mensajeDeError(respuesta));
    restaurar();
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/../src/views/layout/footer.php'; ?>
