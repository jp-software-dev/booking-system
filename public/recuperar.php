<?php

/**
 * VISTA: ¿OLVIDASTE TU CONTRASEÑA?
 *
 * Pide el correo y solicita a api/recuperar.php el envío del enlace de
 * restablecimiento.
 *
 * Acceso: público. Si ya hay sesión, redirige a la página de inicio del rol.
 */

require_once __DIR__ . '/../config/config.php';

use App\Http\Sesion;

if (Sesion::estaAutenticado()) {
    Sesion::redirigir(Sesion::paginaInicio());
}

require __DIR__ . '/../src/views/layout/header.php';
?>
<div class="d-flex align-items-center justify-content-center auth-bg py-5" style="min-height: 85vh;">
    <div class="card auth-card p-4 p-md-5 w-100" style="max-width: 460px;">
        <div class="mb-3">
            <a href="login.php" class="text-decoration-none text-muted fw-bold small"><i class="bi bi-arrow-left me-1"></i> Volver</a>
        </div>
        <div class="text-center mb-4">
            <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px;"><i class="bi bi-lock-fill text-primary fs-3"></i></div>
            <h3 class="fw-bold text-dark-blue">¿Olvidaste tu contraseña?</h3>
            <p class="text-secondary small">Ingresa tu correo y te enviaremos un enlace para restablecerla.</p>
        </div>
        <div id="recuperarAlert"></div>
        <form id="formRecuperar">
            <div class="mb-4">
                <label for="emailInput" class="form-label small fw-bold text-secondary">Correo electrónico</label>
                <input type="email" id="emailInput" name="email" class="form-control" maxlength="100" autocomplete="email" required autofocus>
            </div>
            <button type="submit" id="btnEnviar" class="btn btn-primary w-100 py-3 fw-bold mb-3">Enviar enlace</button>
        </form>
    </div>
</div>

<script>
document.getElementById('formRecuperar').addEventListener('submit', async (evento) => {
    evento.preventDefault();
    limpiarAlerta('recuperarAlert');

    const restaurar = bloquearBoton(document.getElementById('btnEnviar'), 'Enviando...');
    const respuesta = await apiClient.post('recuperar.php', { email: document.getElementById('emailInput').value.trim() });

    if (respuesta?.status === 'success') {
        mostrarAlerta('recuperarAlert', 'success', respuesta.message);
        evento.target.reset();
    } else {
        mostrarAlerta('recuperarAlert', 'danger', mensajeDeError(respuesta));
    }
    restaurar();
});
</script>

<?php require __DIR__ . '/../src/views/layout/footer.php'; ?>
