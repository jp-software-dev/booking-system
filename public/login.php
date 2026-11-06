<?php

/**
 * VISTA: INICIO DE SESIÓN
 *
 * Formulario único para administración y médicos (usuario) y pacientes (correo).
 * Envía las credenciales a api/auth.php.
 *
 * En modo demo, junto al formulario aparecen las tres cuentas de prueba como
 * compartimentos del pastillero (neutros: el color de la tapa se reserva a la hora del día): su botón rellena
 * el formulario y entra con un solo clic.
 *
 * Acceso: público. Si ya hay sesión, redirige a la página de inicio del rol.
 */

require_once __DIR__ . '/../config/config.php';

use App\Demo\ModoDemo;
use App\Http\Sesion;

if (Sesion::estaAutenticado()) {
    Sesion::redirigir(Sesion::paginaInicio());
}

$esDemo = ModoDemo::activo();
$cuentasDemo = [
    ['rol' => 'Administración', 'usuario' => ModoDemo::ADMIN_USUARIO, 'icono' => 'bi-clipboard2-pulse',
     'descripcion' => 'Citas de toda la clínica, métricas, equipo médico y bitácora.'],
    ['rol' => 'Médico', 'usuario' => ModoDemo::DOCTOR_USUARIO, 'icono' => 'bi-heart-pulse',
     'descripcion' => 'Su semana de consultas, recetas y apego de sus pacientes.'],
    ['rol' => 'Paciente', 'usuario' => ModoDemo::PACIENTE_EMAIL, 'icono' => 'bi-capsule',
     'descripcion' => 'Su pastillero semanal, recordatorios, citas y recetas.'],
];

$tituloPagina = 'Iniciar sesión';
require __DIR__ . '/../src/views/layout/header.php';
?>
<div class="auth-bg flex-grow-1 py-5">
    <div class="container">
        <div class="row g-4 justify-content-center align-items-stretch">
            <!-- === FORMULARIO === -->
            <div class="<?= $esDemo ? 'col-lg-5' : 'col-md-7 col-lg-5' ?>">
                <div class="card auth-card h-100 p-4 p-md-5">
                    <h1 class="ma-titulo mb-1">Bienvenido</h1>
                    <p class="ma-subtitulo mb-4">Entra con tu usuario o con el correo de tu cuenta.</p>

                    <div id="loginAlert"></div>

                    <form id="formLogin" autocomplete="off" novalidate>
                        <!-- Honeypot anti-bot: campo oculto para humanos. Si llega con valor, api/auth.php rechaza la petición. -->
                        <div class="hp-field" aria-hidden="true">
                            <label for="sitio_web">Sitio web</label>
                            <input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="mb-3">
                            <label for="identificadorInput" class="form-label">Usuario o correo</label>
                            <input type="text" id="identificadorInput" name="usuario" class="form-control form-control-lg" autocomplete="username" required>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label for="passInput" class="form-label mb-0">Contraseña</label>
                                <a href="recuperar.php" class="small fw-bold text-decoration-none">¿La olvidaste?</a>
                            </div>
                            <div class="input-group input-group-lg">
                                <input type="password" id="passInput" name="password" class="form-control" autocomplete="current-password" required>
                                <button class="btn btn-light border" type="button" id="togglePassword" aria-label="Mostrar u ocultar contraseña"><i class="bi bi-eye" id="eyeIcon" aria-hidden="true"></i></button>
                            </div>
                        </div>

                        <button type="submit" id="btnIngresar" class="btn btn-primary btn-lg w-100 mb-3">Entrar</button>
                        <p class="text-center small text-secondary mb-0">¿No tienes cuenta? <a href="registro.php" class="fw-bold text-decoration-none">Regístrate</a></p>
                    </form>
                </div>
            </div>

            <?php if ($esDemo): ?>
                <!-- === CUENTAS DE PRUEBA (solo en modo demo) === -->
                <div class="col-lg-5">
                    <section class="card h-100 p-4 p-md-5" aria-labelledby="tituloCuentasDemo">
                        <h2 class="fs-4 fw-bold mb-1" id="tituloCuentasDemo">Cuentas de prueba</h2>
                        <p class="text-secondary mb-4">Elige un rol y entra con un clic. Contraseña de las tres: <code class="ma-cifras"><?= e(ModoDemo::PASSWORD) ?></code></p>

                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($cuentasDemo as $cuenta): ?>
                                <div class="compartimento flex-row align-items-center gap-3 p-3 pt-4" style="min-height: 0;">
                                    <span class="tapa" style="height: 10px;"></span>
                                    <i class="bi <?= e($cuenta['icono']) ?> fs-3 text-secondary" aria-hidden="true"></i>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="fw-bold"><?= e($cuenta['rol']) ?> · <code><?= e($cuenta['usuario']) ?></code></div>
                                        <div class="small text-secondary"><?= e($cuenta['descripcion']) ?></div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-light flex-none" data-cuenta-demo="<?= e($cuenta['usuario']) ?>"
                                            aria-label="Entrar como <?= e(mb_strtolower($cuenta['rol'])) ?>">Entrar</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>
            <?php endif; ?>
        </div>
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

// === CUENTAS DE PRUEBA (modo demo): rellenan el formulario y entran ===
document.querySelectorAll('[data-cuenta-demo]').forEach((boton) => {
    boton.addEventListener('click', () => {
        document.getElementById('identificadorInput').value = boton.dataset.cuentaDemo;
        document.getElementById('passInput').value = <?= json_encode($esDemo ? ModoDemo::PASSWORD : '') ?>;
        document.getElementById('formLogin').requestSubmit();
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
