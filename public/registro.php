<?php

/**
 * VISTA: REGISTRO DE PACIENTES
 *
 * Formulario en 4 pasos (nombre, contacto, datos personales y contraseña).
 * Cada paso se valida en el navegador antes de avanzar; la validación
 * definitiva la hace api/auth_registro.php.
 *
 * En modo demo avisa que los datos son visibles para cualquier visitante (las
 * credenciales del administrador de prueba son públicas) y que no se envían correos.
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
<style>
    /* Pasos del formulario: solo el paso activo es visible y entra deslizándose. */
    .step-container { display: none; }
    .step-container.active { display: block; animation: entrarPaso var(--ma-medio) var(--ma-curva) both; }
    @keyframes entrarPaso { from { transform: translateX(14px); opacity: 0; } }
    /* Indicador de avance: cada paso es un compartimento cuya tapa se llena al llegar a él. */
    .step-indicator { position: relative; width: 38px; height: 38px; border-radius: 12px; background: var(--ma-superficie-2); color: var(--ma-tinta-3); display: grid; place-items: center; font-weight: 800; overflow: hidden; transition: background-color var(--ma-medio) var(--ma-curva), color var(--ma-medio) var(--ma-curva); }
    .step-indicator::before { content: ""; position: absolute; inset: 0 0 auto 0; height: 7px; background: var(--ma-linea-fuerte); transition: background-color var(--ma-medio) var(--ma-curva); }
    .step-indicator.active { background: var(--ma-accion-suave); color: var(--ma-accion-fuerte); }
    .step-indicator.active::before { background: var(--ma-accion); }
    .step-line { flex: 1; height: 3px; border-radius: 999px; background: var(--ma-linea); margin: 0 8px; transition: background-color var(--ma-medio) var(--ma-curva); }
    .step-line.active { background: var(--ma-accion); }
    .paso-titulo { font-size: 1rem; font-weight: 800; margin-bottom: 1rem; }
</style>

<div class="d-flex align-items-center justify-content-center auth-bg flex-grow-1 py-5">
    <div class="card auth-card p-4 p-md-5" style="max-width: 500px; width: 100%;">
        <h1 class="ma-titulo mb-1">Crea tu cuenta</h1>
        <p class="ma-subtitulo mb-4">Cuatro pasos y podrás agendar tus citas y llevar tus medicamentos.</p>

        <!-- Indicador de avance -->
        <div class="d-flex align-items-center justify-content-between mb-4 px-2">
            <div class="step-indicator active" id="ind-1">1</div><div class="step-line" id="line-1"></div>
            <div class="step-indicator" id="ind-2">2</div><div class="step-line" id="line-2"></div>
            <div class="step-indicator" id="ind-3">3</div><div class="step-line" id="line-3"></div>
            <div class="step-indicator" id="ind-4">4</div>
        </div>

        <?php if (ModoDemo::activo()): ?>
            <!-- === AVISO DE PRIVACIDAD (solo en modo demo) === -->
            <div class="alert alert-warning small mb-4">
                <i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>
                <strong>Usa datos ficticios.</strong> En la demo cualquier visitante puede entrar como
                administrador y ver los datos que registres. No se envían correos y todo se borra cada día.
            </div>
        <?php endif; ?>

        <div id="registroAlert"></div>

        <form id="registroForm" novalidate autocomplete="off">
            <!-- Honeypot anti-bot: campo oculto para humanos. Si llega con valor, api/auth_registro.php rechaza la petición. -->
            <div class="hp-field" aria-hidden="true">
                <label for="sitio_web">Sitio web</label>
                <input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off">
            </div>

            <!-- Paso 1: nombre -->
            <div class="step-container active" id="step-1">
                <h2 class="paso-titulo">Tu nombre</h2>
                <div class="form-floating mb-3"><input type="text" name="nombre" id="nombre" class="form-control" maxlength="50" placeholder="Nombre(s)" required><label for="nombre">Nombre(s)</label></div>
                <div class="form-floating mb-3"><input type="text" name="apellido_paterno" id="apellido_paterno" class="form-control" maxlength="50" placeholder="Apellido paterno" required><label for="apellido_paterno">Apellido paterno</label></div>
                <div class="form-floating mb-4"><input type="text" name="apellido_materno" id="apellido_materno" class="form-control" maxlength="50" placeholder="Apellido materno"><label for="apellido_materno">Apellido materno (opcional)</label></div>
                <button type="button" class="btn btn-primary w-100" data-siguiente="1">Siguiente</button>
            </div>

            <!-- Paso 2: contacto -->
            <div class="step-container" id="step-2">
                <h2 class="paso-titulo">¿Cómo te contactamos?</h2>
                <div class="form-floating mb-3"><input type="email" name="email" id="email" class="form-control" maxlength="100" placeholder="Correo" required><label for="email">Correo electrónico</label></div>
                <div class="form-floating mb-4"><input type="text" name="telefono" id="telefono" class="form-control" maxlength="10" inputmode="numeric" placeholder="Teléfono" required><label for="telefono">Celular (10 dígitos)</label></div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light w-50" data-anterior="2">Atrás</button>
                    <button type="button" class="btn btn-primary w-50" data-siguiente="2">Siguiente</button>
                </div>
            </div>

            <!-- Paso 3: datos personales -->
            <div class="step-container" id="step-3">
                <h2 class="paso-titulo">Sobre ti</h2>
                <div class="form-floating mb-3"><input type="date" name="fecha_nacimiento" id="fecha_nacimiento" class="form-control" max="<?= e(date('Y-m-d')) ?>" required><label for="fecha_nacimiento">Fecha de nacimiento</label></div>
                <div class="form-floating mb-3">
                    <select name="genero" id="genero" class="form-select" required>
                        <option value=""></option>
                        <option value="Masculino">Masculino</option>
                        <option value="Femenino">Femenino</option>
                        <option value="Otro">Prefiero no decir</option>
                    </select>
                    <label for="genero">Género</label>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light w-50" data-anterior="3">Atrás</button>
                    <button type="button" class="btn btn-primary w-50" data-siguiente="3">Siguiente</button>
                </div>
            </div>

            <!-- Paso 4: contraseña -->
            <div class="step-container" id="step-4">
                <h2 class="paso-titulo">Tu contraseña</h2>
                <div class="form-floating mb-3"><input type="password" name="password" id="password" class="form-control" minlength="8" maxlength="200" placeholder="Contraseña" autocomplete="new-password" required><label for="password">Contraseña (mínimo 8 caracteres)</label></div>
                <div class="progress mb-3" style="height: 4px;" aria-hidden="true"><div id="passwordStrength" class="progress-bar" style="width: 0%;"></div></div>
                <div class="form-floating mb-4"><input type="password" name="confirm_password" id="confirm_password" class="form-control" minlength="8" maxlength="200" placeholder="Confirmar" autocomplete="new-password" required><label for="confirm_password">Confirma la contraseña</label></div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light w-50" data-anterior="4">Atrás</button>
                    <button type="submit" id="btnRegistro" class="btn btn-primary w-50">Crear cuenta</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// === MENSAJES DE ERROR POR CAMPO ===

/** Muestra un mensaje de error debajo del campo. */
function mostrarErrorCampo(campo, mensaje) {
    limpiarErrorCampo(campo);
    const error = document.createElement('div');
    error.className = 'text-danger small mt-1 error-campo';
    error.textContent = mensaje;
    campo.closest('.form-floating').after(error);
    campo.classList.add('is-invalid');
}

/** Quita el mensaje de error del campo, si lo hay. */
function limpiarErrorCampo(campo) {
    const contenedor = campo.closest('.form-floating');
    if (contenedor.nextElementSibling?.classList.contains('error-campo')) {
        contenedor.nextElementSibling.remove();
    }
    campo.classList.remove('is-invalid');
}

/** Valida un campo con una condición; devuelve true si es válido. */
function validarCampo(id, esValido, mensaje) {
    const campo = document.getElementById(id);
    if (esValido(campo.value.trim())) {
        limpiarErrorCampo(campo);
        return true;
    }
    mostrarErrorCampo(campo, mensaje);
    return false;
}

// === VALIDACIÓN POR PASO ===
const reglasPorPaso = {
    1: () => [
        validarCampo('nombre', (v) => v !== '', 'Campo requerido'),
        validarCampo('apellido_paterno', (v) => v !== '', 'Campo requerido'),
    ],
    2: () => [
        validarCampo('email', (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v), 'Correo inválido'),
        validarCampo('telefono', (v) => /^\d{10}$/.test(v), 'Debe tener 10 dígitos numéricos'),
    ],
    3: () => [
        validarCampo('fecha_nacimiento', (v) => v !== '', 'Requerido'),
        validarCampo('genero', (v) => v !== '', 'Requerido'),
    ],
    4: () => [
        validarCampo('password', (v) => v.length >= 8, 'Mínimo 8 caracteres'),
        validarCampo('confirm_password', (v) => v === document.getElementById('password').value, 'Las contraseñas no coinciden'),
    ],
};

/** Valida todos los campos del paso (sin detenerse en el primero para mostrar todos los errores). */
function validarPaso(paso) {
    return reglasPorPaso[paso]().every(Boolean);
}

// === NAVEGACIÓN ENTRE PASOS ===
function irAPaso(actual, destino) {
    document.getElementById(`step-${actual}`).classList.remove('active');
    document.getElementById(`step-${destino}`).classList.add('active');

    const avanza = destino > actual;
    document.getElementById(`ind-${avanza ? destino : actual}`).classList.toggle('active', avanza);
    document.getElementById(`line-${avanza ? actual : destino}`).classList.toggle('active', avanza);
}

document.querySelectorAll('[data-siguiente]').forEach((boton) => {
    boton.addEventListener('click', () => {
        const paso = Number(boton.dataset.siguiente);
        if (validarPaso(paso)) {
            irAPaso(paso, paso + 1);
        }
    });
});

document.querySelectorAll('[data-anterior]').forEach((boton) => {
    boton.addEventListener('click', () => {
        const paso = Number(boton.dataset.anterior);
        irAPaso(paso, paso - 1);
    });
});

// === AYUDAS DE CAPTURA ===
// El teléfono solo acepta dígitos.
document.getElementById('telefono').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '');
});

// Barra de fortaleza de la contraseña: longitud, mayúsculas, números y símbolos.
document.getElementById('password').addEventListener('input', function () {
    const v = this.value;
    const puntos = [v.length >= 8, /[A-Z]/.test(v), /\d/.test(v), /\W/.test(v)].filter(Boolean).length * 25;
    const barra = document.getElementById('passwordStrength');
    barra.style.width = `${puntos}%`;
    barra.className = `progress-bar bg-${puntos < 50 ? 'danger' : (puntos < 75 ? 'warning' : 'success')}`;
});

// === ENVÍO ===
document.getElementById('registroForm').addEventListener('submit', async (evento) => {
    evento.preventDefault();
    if (!document.getElementById('step-4').classList.contains('active') || !validarPaso(4)) {
        return;
    }

    limpiarAlerta('registroAlert');
    const restaurar = bloquearBoton(document.getElementById('btnRegistro'), 'Procesando...');
    const respuesta = await apiClient.postFormulario('auth_registro.php', new FormData(evento.target));

    if (respuesta?.status === 'success') {
        window.location.href = respuesta.redirect;
        return;
    }

    mostrarAlerta('registroAlert', 'danger', mensajeDeError(respuesta));
    restaurar();
});
</script>

<?php require __DIR__ . '/../src/views/layout/footer.php'; ?>
