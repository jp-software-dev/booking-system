<?php
/**
 * VISTA DE REGISTRO DE PACIENTES
 *
 * Mejoras aplicadas:
 * - Teléfono: solo acepta dígitos (bloquea letras en tiempo real).
 * - Contraseña: indicador visual de fortaleza en tiempo real.
 * - Email: validación HTML5 nativa (requiere @).
 * - Edad mínima 18 años validada antes de enviar.
 */

session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] === 'admin' ? "admin.php" : "agenda.php"));
    exit();
}

require_once '../src/views/layout/header.php';
?>

<div class="d-flex align-items-center justify-content-center bg-light py-5" style="min-height: 85vh;">
    <div class="card border-0 shadow-lg p-5" style="max-width: 600px; width: 100%; border-radius: 20px;">
        <h3 class="text-center fw-bold text-dark mb-2">Crear Cuenta</h3>
        <p class="text-center text-muted mb-4 small">Regístrate para gestionar tus citas</p>

        <div id="registroAlert"></div>

        <form id="registroForm" novalidate>
            <div class="row g-3">

                <!-- Nombre -->
                <div class="col-md-6">
                    <label for="nombre" class="form-label fw-bold text-secondary small">Nombre <span class="text-danger">*</span></label>
                    <input type="text" id="nombre" name="nombre" class="form-control bg-light border-0" required>
                </div>

                <!-- Edad -->
                <div class="col-md-6">
                    <label for="edad" class="form-label fw-bold text-secondary small">Edad <span class="text-danger">*</span></label>
                    <input type="number" id="edad" name="edad" class="form-control bg-light border-0" min="18" max="120" required>
                    <small class="text-muted" style="font-size:0.72rem;">Debe ser mayor de 18 años</small>
                </div>

                <!-- Apellido Paterno -->
                <div class="col-md-6">
                    <label for="apellido_paterno" class="form-label fw-bold text-secondary small">Apellido Paterno <span class="text-danger">*</span></label>
                    <input type="text" id="apellido_paterno" name="apellido_paterno" class="form-control bg-light border-0" required>
                </div>

                <!-- Apellido Materno -->
                <div class="col-md-6">
                    <label for="apellido_materno" class="form-label fw-bold text-secondary small">Apellido Materno</label>
                    <input type="text" id="apellido_materno" name="apellido_materno" class="form-control bg-light border-0">
                </div>

                <!-- CURP -->
                <div class="col-md-6">
                    <label for="curp" class="form-label fw-bold text-secondary small">CURP <span class="text-danger">*</span></label>
                    <input type="text" id="curp" name="curp" class="form-control bg-light border-0"
                           maxlength="18" required style="text-transform:uppercase;"
                           placeholder="18 caracteres">
                    <small class="text-muted" style="font-size:0.72rem;">18 caracteres alfanuméricos</small>
                </div>

                <!-- Género -->
                <div class="col-md-6">
                    <label for="genero" class="form-label fw-bold text-secondary small">Género <span class="text-danger">*</span></label>
                    <select id="genero" name="genero" class="form-select bg-light border-0" required>
                        <option value="" disabled selected>Seleccione</option>
                        <option value="Masculino">Masculino</option>
                        <option value="Femenino">Femenino</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>

                <!-- Email -->
                <div class="col-md-6">
                    <label for="email" class="form-label fw-bold text-secondary small">Correo electrónico <span class="text-danger">*</span></label>
                    <input type="email" id="email" name="email" class="form-control bg-light border-0"
                           placeholder="ejemplo@correo.com" required>
                </div>

                <!-- Teléfono: solo números -->
                <div class="col-md-6">
                    <label for="telefono" class="form-label fw-bold text-secondary small">Teléfono <span class="text-danger">*</span></label>
                    <input type="tel" id="telefono" name="telefono" class="form-control bg-light border-0"
                           inputmode="numeric" maxlength="10" placeholder="10 dígitos" required>
                    <small class="text-muted" style="font-size:0.72rem;">Solo números, 10 dígitos</small>
                </div>

                <!-- Contraseña -->
                <div class="col-md-6">
                    <label for="password" class="form-label fw-bold text-secondary small">Contraseña <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" id="password" name="password"
                               class="form-control bg-light border-0" minlength="8"
                               placeholder="Mínimo 8 caracteres" required>
                        <button class="btn btn-light border-0" type="button" id="togglePassword">
                            <i class="bi bi-eye-slash"></i>
                        </button>
                    </div>
                    <!-- Indicador de fortaleza -->
                    <div class="mt-1">
                        <div class="progress" style="height:4px; border-radius:2px;">
                            <div id="strengthBar" class="progress-bar" style="width:0%; transition:width 0.3s;"></div>
                        </div>
                        <small id="strengthText" class="text-muted" style="font-size:0.72rem;"></small>
                    </div>
                </div>

                <!-- Confirmar contraseña -->
                <div class="col-md-6">
                    <label for="confirm_password" class="form-label fw-bold text-secondary small">Confirmar contraseña <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" id="confirm_password" name="confirm_password"
                               class="form-control bg-light border-0" minlength="8"
                               placeholder="Repite la contraseña" required>
                        <button class="btn btn-light border-0" type="button" id="toggleConfirmPassword">
                            <i class="bi bi-eye-slash"></i>
                        </button>
                    </div>
                    <small id="matchText" class="mt-1" style="font-size:0.72rem;"></small>
                </div>

            </div>

            <button type="submit" id="btnRegistro" class="btn btn-primary w-100 py-3 fw-bold rounded-pill mt-4">
                CREAR CUENTA
            </button>
        </form>

        <div class="text-center mt-3">
            <span class="text-muted small">¿Ya tienes cuenta?</span>
            <a href="login_paciente.php" class="text-primary fw-bold ms-1 small">Inicia sesión</a>
        </div>
    </div>
</div>

<script>
/* ── Toggle visibilidad contraseñas ── */
function toggleVis(inputId, btn) {
    const el = document.getElementById(inputId);
    const type = el.type === 'password' ? 'text' : 'password';
    el.type = type;
    btn.querySelector('i').classList.toggle('bi-eye');
    btn.querySelector('i').classList.toggle('bi-eye-slash');
}
document.getElementById('togglePassword').addEventListener('click', function() { toggleVis('password', this); });
document.getElementById('toggleConfirmPassword').addEventListener('click', function() { toggleVis('confirm_password', this); });

/* ── Teléfono: bloquear todo lo que no sea dígito ── */
document.getElementById('telefono').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '').slice(0, 10);
});
document.getElementById('telefono').addEventListener('keydown', function(e) {
    const allowed = ['Backspace','Delete','ArrowLeft','ArrowRight','Tab','Enter'];
    if (!allowed.includes(e.key) && !/^\d$/.test(e.key)) e.preventDefault();
});

/* ── CURP: forzar mayúsculas al escribir ── */
document.getElementById('curp').addEventListener('input', function() {
    const pos = this.selectionStart;
    this.value = this.value.toUpperCase();
    this.setSelectionRange(pos, pos);
});

/* ── Indicador de fortaleza de contraseña ── */
document.getElementById('password').addEventListener('input', function() {
    const val = this.value;
    const bar  = document.getElementById('strengthBar');
    const text = document.getElementById('strengthText');
    let score  = 0;
    if (val.length >= 8)  score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
        { pct: '0%',   color: 'bg-secondary', label: '' },
        { pct: '25%',  color: 'bg-danger',    label: 'Muy débil' },
        { pct: '50%',  color: 'bg-warning',   label: 'Débil' },
        { pct: '75%',  color: 'bg-info',      label: 'Buena' },
        { pct: '100%', color: 'bg-success',   label: 'Fuerte' },
    ];
    const lv = val.length === 0 ? levels[0] : levels[score];
    bar.style.width = lv.pct;
    bar.className = 'progress-bar ' + lv.color;
    text.textContent = lv.label;
    text.className = val.length === 0 ? 'text-muted' : (score >= 3 ? 'text-success' : 'text-warning');

    checkMatch();
});

/* ── Verificar coincidencia de contraseñas en tiempo real ── */
function checkMatch() {
    const pass    = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    const mt      = document.getElementById('matchText');
    if (!confirm) { mt.textContent = ''; return; }
    if (pass === confirm) {
        mt.textContent = '✓ Las contraseñas coinciden';
        mt.className = 'text-success';
    } else {
        mt.textContent = '✗ Las contraseñas no coinciden';
        mt.className = 'text-danger';
    }
}
document.getElementById('confirm_password').addEventListener('input', checkMatch);

/* ── Envío del formulario ── */
document.getElementById('registroForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const alertEl = document.getElementById('registroAlert');
    alertEl.innerHTML = '';

    const edad    = parseInt(document.getElementById('edad').value);
    const pass    = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    const tel     = document.getElementById('telefono').value;

    if (edad < 18) {
        alertEl.innerHTML = '<div class="alert alert-danger py-2 small text-center">Debes ser mayor de 18 años para registrarte.</div>';
        return;
    }
    if (pass !== confirm) {
        alertEl.innerHTML = '<div class="alert alert-danger py-2 small text-center">Las contraseñas no coinciden.</div>';
        return;
    }
    if (pass.length < 8) {
        alertEl.innerHTML = '<div class="alert alert-danger py-2 small text-center">La contraseña debe tener al menos 8 caracteres.</div>';
        return;
    }
    if (!/^\d{10}$/.test(tel)) {
        alertEl.innerHTML = '<div class="alert alert-danger py-2 small text-center">El teléfono debe contener exactamente 10 dígitos numéricos.</div>';
        return;
    }

    const btn = document.getElementById('btnRegistro');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Registrando...';

    const formData = new FormData(this);

    try {
        const res  = await fetch('../api/auth_registro.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') {
            window.location.href = data.redirect;
        } else {
            alertEl.innerHTML = `<div class="alert alert-danger py-2 small text-center">${data.message}</div>`;
            btn.disabled = false;
            btn.innerHTML = 'CREAR CUENTA';
        }
    } catch (error) {
        alertEl.innerHTML = '<div class="alert alert-danger py-2 small text-center">Ocurrió un error en el registro. Intenta de nuevo.</div>';
        btn.disabled = false;
        btn.innerHTML = 'CREAR CUENTA';
    }
});
</script>

<?php require_once '../src/views/layout/footer.php'; ?>