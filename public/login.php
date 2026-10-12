<?php

/**
 * VISTA: INICIO DE SESIÓN
 *
 * Formulario único para administradores (usuario) y pacientes (correo).
 * Envía las credenciales a api/auth.php.
 *
 * En modo demo muestra las cuentas de prueba y botones para rellenar el formulario.
 *
 * Acceso: público. Si ya hay sesión, redirige a la página de inicio del rol.
 */

require_once __DIR__ . '/../config/config.php';

use App\Demo\ModoDemo;
use App\Http\Sesion;

if (Sesion::estaAutenticado()) {
    Sesion::redirigir(Sesion::paginaInicio());
}

require __DIR__ . '/../src/views/layout/header.php';
?>
<div class="d-flex align-items-center justify-content-center auth-bg py-5" style="min-height: 85vh;">
    <div class="card auth-card p-5 w-100" style="max-width: 460px;">
        <div class="text-center mb-4"><h3 class="fw-bold text-dark-blue">Iniciar Sesión</h3></div>

        <?php if (ModoDemo::activo()): ?>
            <!-- === CUENTAS DE PRUEBA (solo en modo demo) === -->
            <div class="alert alert-info small mb-4">
                <div class="fw-bold mb-2"><i class="bi bi-person-badge me-1"></i> Cuentas de prueba</div>
                <div>Administrador: <code><?= e(ModoDemo::ADMIN_USUARIO) ?></code></div>
                <div>Paciente: <code><?= e(ModoDemo::PACIENTE_EMAIL) ?></code></div>
                <div class="mb-2">Contraseña de ambas: <code><?= e(ModoDemo::PASSWORD) ?></code></div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-cuenta-demo="<?= e(ModoDemo::ADMIN_USUARIO) ?>">Entrar como administrador</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-cuenta-demo="<?= e(ModoDemo::PACIENTE_EMAIL) ?>">Entrar como paciente</button>
                </div>
            </div>
        <?php endif; ?>

        <div id="loginAlert"></div>

        <form id="formLogin" autocomplete="off" novalidate>
            <!-- Honeypot anti-bot: campo oculto para humanos. Si llega con valor, api/auth.php rechaza la petición. -->
            <div class="hp-field" aria-hidden="true">
                <label for="sitio_web">Sitio web</label>
                <input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off">
            </div>

            <div class="mb-4">
                <label for="identificadorInput" class="form-label fw-bold text-secondary">Usuario o Correo</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-primary"><i class="bi bi-person-fill"></i></span>
                    <input type="text" id="identificadorInput" name="usuario" class="form-control border-start-0 ps-0" autocomplete="username" required>
                </div>
            </div>

            <div class="mb-5">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="passInput" class="form-label fw-bold text-secondary mb-0">Contraseña</label>
                    <a href="recuperar.php" class="text-decoration-none fw-medium" style="font-size: 13px; color: var(--primary);">¿Olvidaste tu contraseña?</a>
                </div>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-primary"><i class="bi bi-lock-fill"></i></span>
                    <input type="password" id="passInput" name="password" class="form-control border-start-0 border-end-0 ps-0" autocomplete="current-password" required>
                    <button class="btn btn-outline-secondary border-start-0 bg-white" type="button" id="togglePassword" aria-label="Mostrar u ocultar contraseña"><i class="bi bi-eye" id="eyeIcon"></i></button>
                </div>
            </div>

            <button type="submit" id="btnIngresar" class="btn btn-primary w-100 btn-lg fw-bold shadow-sm mb-4">INGRESAR</button>
            <div class="text-center"><span class="text-secondary small">¿No tienes cuenta?</span><a href="registro.php" class="text-decoration-none fw-bold small ms-1">Regístrate aquí</a></div>
        </form>
    </div>
</div>

<script>
// === MOSTRAR / OCULTAR CONTRASEÑA ===
document.getElementById('togglePassword').addEventListener('click', () => {
    const campo = document.getElementById('passInput');
    const icono = document.getElementById('eyeIcon');
    campo.type = campo.type === 'password' ? 'text' : 'password';
    icono.classList.toggle('bi-eye');
    icono.classList.toggle('bi-eye-slash');
});

// === CUENTAS DE PRUEBA (modo demo): rellenan usuario y contraseña ===
document.querySelectorAll('[data-cuenta-demo]').forEach((boton) => {
    boton.addEventListener('click', () => {
        document.getElementById('identificadorInput').value = boton.dataset.cuentaDemo;
        document.getElementById('passInput').value = <?= json_encode(ModoDemo::activo() ? ModoDemo::PASSWORD : '') ?>;
        document.getElementById('btnIngresar').focus();
    });
});

// === ENVÍO DEL FORMULARIO ===
document.getElementById('formLogin').addEventListener('submit', async (evento) => {
    evento.preventDefault();
    limpiarAlerta('loginAlert');

    const restaurar = bloquearBoton(document.getElementById('btnIngresar'), 'Verificando...');
    const datos = Object.fromEntries(new FormData(evento.target));
    const respuesta = await apiClient.post('auth.php', datos);

    if (respuesta?.status === 'success') {
        window.location.href = respuesta.redirect;
        return;
    }

    mostrarAlerta('loginAlert', 'danger', mensajeDeError(respuesta));
    restaurar();
});
</script>

<?php require __DIR__ . '/../src/views/layout/footer.php'; ?>
