<?php

/**
 * VISTA: REGISTRO DE PACIENTES
 *
 * Formulario en 4 pasos (nombre, contacto, datos personales y contraseña).
 * Cada paso se valida en el navegador antes de avanzar; la validación
 * definitiva la hace api/auth_registro.php.
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
<style>
    /* Pasos del formulario: solo el paso activo es visible. */
    .step-container { display: none; animation: entrarPaso 0.3s ease-out forwards; }
    .step-container.active { display: block; }
    @keyframes entrarPaso { from { transform: translateX(15px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    .step-indicator { width: 32px; height: 32px; border-radius: 50%; background: #e5e7eb; color: #6b7280; display: flex; align-items: center; justify-content: center; font-weight: bold; }
    .step-indicator.active { background: var(--primary); color: #ffffff; }
    .step-line { flex: 1; height: 2px; background: #e5e7eb; margin: 0 10px; }
    .step-line.active { background: var(--primary); }
</style>

<div class="d-flex align-items-center justify-content-center auth-bg py-5" style="min-height: 85vh;">
    <div class="card auth-card p-4 p-md-5" style="max-width: 480px; width: 100%;">
        <h4 class="text-center fw-bold text-dark-blue mb-4">Crear Cuenta</h4>

        <!-- Indicador de avance -->
        <div class="d-flex align-items-center justify-content-between mb-4 px-2">
            <div class="step-indicator active" id="ind-1">1</div><div class="step-line" id="line-1"></div>
            <div class="step-indicator" id="ind-2">2</div><div class="step-line" id="line-2"></div>
            <div class="step-indicator" id="ind-3">3</div><div class="step-line" id="line-3"></div>
            <div class="step-indicator" id="ind-4">4</div>
        </div>

        <div id="registroAlert"></div>

        <form id="registroForm" novalidate autocomplete="off">
            <!-- Honeypot anti-bot: campo oculto para humanos. Si llega con valor, api/auth_registro.php rechaza la petición. -->
            <div class="hp-field" aria-hidden="true">
                <label for="sitio_web">Sitio web</label>
                <input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off">
            </div>

            <!-- Paso 1: nombre -->
            <div class="step-container active" id="step-1">
                <h6 class="fw-bold text-secondary mb-3">Nombre completo</h6>
                <div class="form-floating mb-3"><input type="text" name="nombre" id="nombre" class="form-control" maxlength="50" placeholder="Nombre(s)" required><label for="nombre">Nombre(s)</label></div>
                <div class="form-floating mb-3"><input type="text" name="apellido_paterno" id="apellido_paterno" class="form-control" maxlength="50" placeholder="Apellido paterno" required><label for="apellido_paterno">Apellido Paterno</label></div>
                <div class="form-floating mb-4"><input type="text" name="apellido_materno" id="apellido_materno" class="form-control" maxlength="50" placeholder="Apellido materno"><label for="apellido_materno">Apellido Materno (opcional)</label></div>
                <button type="button" class="btn btn-primary w-100 fw-bold py-2" data-siguiente="1">Siguiente</button>
            </div>

            <!-- Paso 2: contacto -->
            <div class="step-container" id="step-2">
                <h6 class="fw-bold text-secondary mb-3">Información de contacto</h6>
                <div class="form-floating mb-3"><input type="email" name="email" id="email" class="form-control" maxlength="100" placeholder="Correo" required><label for="email">Correo Electrónico</label></div>
                <div class="form-floating mb-4"><input type="text" name="telefono" id="telefono" class="form-control" maxlength="10" inputmode="numeric" placeholder="Teléfono" required><label for="telefono">Teléfono Móvil (10 dígitos)</label></div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light w-50 fw-bold border" data-anterior="2">Atrás</button>
                    <button type="button" class="btn btn-primary w-50 fw-bold" data-siguiente="2">Siguiente</button>
                </div>
            </div>

            <!-- Paso 3: datos personales -->
            <div class="step-container" id="step-3">
                <h6 class="fw-bold text-secondary mb-3">Información personal</h6>
                <div class="form-floating mb-3"><input type="date" name="fecha_nacimiento" id="fecha_nacimiento" class="form-control" max="<?= e(date('Y-m-d')) ?>" required><label for="fecha_nacimiento">Fecha de Nacimiento</label></div>
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
                    <button type="button" class="btn btn-light w-50 fw-bold border" data-anterior="3">Atrás</button>
                    <button type="button" class="btn btn-primary w-50 fw-bold" data-siguiente="3">Siguiente</button>
                </div>
            </div>

            <!-- Paso 4: contraseña -->
            <div class="step-container" id="step-4">
                <h6 class="fw-bold text-secondary mb-3">Seguridad</h6>
                <div class="form-floating mb-3"><input type="password" name="password" id="password" class="form-control" minlength="8" maxlength="200" placeholder="Contraseña" autocomplete="new-password" required><label for="password">Contraseña (mínimo 8 caracteres)</label></div>
                <div class="progress mb-3" style="height: 4px;"><div id="passwordStrength" class="progress-bar" style="width: 0%;"></div></div>
                <div class="form-floating mb-4"><input type="password" name="confirm_password" id="confirm_password" class="form-control" minlength="8" maxlength="200" placeholder="Confirmar" autocomplete="new-password" required><label for="confirm_password">Confirmar Contraseña</label></div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light w-50 fw-bold border" data-anterior="4">Atrás</button>
                    <button type="submit" id="btnRegistro" class="btn btn-primary w-50 fw-bold">CREAR CUENTA</button>
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
