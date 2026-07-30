<?php
// Vista de registro con estilos integrados para garantizar el funcionamiento estricto del sistema de pestañas ocultas (multi-step)
require_once __DIR__ . '/../config/config.php';
if (isset($_SESSION['user_id'])) { header("Location: " . ($_SESSION['role'] === 'admin' ? "admin.php" : "agenda.php")); exit(); }
require_once __DIR__ . '/../src/views/layout/header.php';
?>
<style>
.step-container { display: none; animation: slideIn 0.3s ease-out forwards; }
.step-container.active { display: block; }
@keyframes slideIn { from { transform: translateX(15px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
.step-indicator { width: 32px; height: 32px; border-radius: 50%; background: #e5e7eb; color: #6b7280; display: flex; align-items: center; justify-content: center; font-weight: bold; }
.step-indicator.active { background: var(--primary); color: white; }
.step-line { flex: 1; height: 2px; background: #e5e7eb; margin: 0 10px; }
.step-line.active { background: var(--primary); }
</style>

<div class="d-flex align-items-center justify-content-center auth-bg py-5" style="min-height: 85vh;">
    <div class="card auth-card p-4 p-md-5" style="max-width: 480px; width: 100%;">
        <h4 class="text-center fw-bold text-dark-blue mb-4">Crear Cuenta</h4>
        
        <div class="d-flex align-items-center justify-content-between mb-4 px-2">
            <div class="step-indicator active" id="ind-1">1</div><div class="step-line" id="line-1"></div>
            <div class="step-indicator" id="ind-2">2</div><div class="step-line" id="line-2"></div>
            <div class="step-indicator" id="ind-3">3</div><div class="step-line" id="line-3"></div>
            <div class="step-indicator" id="ind-4">4</div>
        </div>

        <div id="registroAlert"></div>

        <form id="registroForm" novalidate autocomplete="off">
            <!-- PUNTO DE INTEGRACIÓN: Se añade el token CSRF al formulario.
                 Esto asume que tu CsrfHelper tiene un método estático `getToken()` para recuperar el token actual.
                 La clase está disponible porque se carga a través de header.php. -->
            <?php
            // Compatibilidad: si Helpers\CsrfHelper::getToken no existe, usar token de sesión si está disponible.
            $csrfToken = '';
            // Comprueba existencia de clase y método de forma segura para evitar errores P1013
            if (class_exists('Helpers\\CsrfHelper') && method_exists('Helpers\\CsrfHelper', 'getToken')) {
                $csrfToken = call_user_func(['Helpers\\CsrfHelper', 'getToken']);
            } elseif (!empty($_SESSION['csrf_token'])) {
                $csrfToken = $_SESSION['csrf_token'];
            }
            ?>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <!-- Honeypot anti-bot: campo trampa oculto a humanos vía CSS. Un bot que rellena
                 todos los campos de un formulario caerá aquí; auth_registro.php lo rechaza en silencio. -->
            <div class="hp-field" aria-hidden="true">
                <label for="sitio_web">Sitio web</label>
                <input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off">
            </div>
            <div class="step-container active" id="step-1">
                <h6 class="fw-bold text-secondary mb-3">Nombre completo</h6>
                <div class="form-floating mb-3"><input type="text" name="nombre" id="nombre" class="form-control" required><label>Nombre(s)</label></div>
                <div class="form-floating mb-3"><input type="text" name="apellido_paterno" id="apellido_paterno" class="form-control" required><label>Apellido Paterno</label></div>
                <div class="form-floating mb-4"><input type="text" name="apellido_materno" id="apellido_materno" class="form-control" required><label>Apellido Materno</label></div>
                <button type="button" class="btn btn-primary w-100 fw-bold py-2" onclick="nextStep(1)">Siguiente</button>
            </div>

            <div class="step-container" id="step-2">
                <h6 class="fw-bold text-secondary mb-3">Información de contacto</h6>
                <div class="form-floating mb-3"><input type="email" name="email" id="email" class="form-control" required><label>Correo Electrónico</label></div>
                <div class="form-floating mb-4"><input type="text" name="telefono" id="telefono" class="form-control" maxlength="10" required><label>Teléfono Móvil</label></div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light w-50 fw-bold border" onclick="prevStep(2)">Atrás</button>
                    <button type="button" class="btn btn-primary w-50 fw-bold" onclick="nextStep(2)">Siguiente</button>
                </div>
            </div>

            <div class="step-container" id="step-3">
                <h6 class="fw-bold text-secondary mb-3">Información personal</h6>
                <div class="form-floating mb-3"><input type="date" name="fecha_nacimiento" id="fecha_nacimiento" class="form-control" required><label>Fecha de Nacimiento</label></div>
                <div class="form-floating mb-3">
                    <select name="genero" id="genero" class="form-select" required><option value=""></option><option value="Masculino">Masculino</option><option value="Femenino">Femenino</option><option value="Otro">Prefiero no decir</option></select><label>Género</label>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light w-50 fw-bold border" onclick="prevStep(3)">Atrás</button>
                    <button type="button" class="btn btn-primary w-50 fw-bold" onclick="nextStep(3)">Siguiente</button>
                </div>
            </div>

            <div class="step-container" id="step-4">
                <h6 class="fw-bold text-secondary mb-3">Seguridad</h6>
                <div class="form-floating mb-3"><input type="password" name="password" id="password" class="form-control" minlength="8" required><label>Contraseña</label></div>
                <div class="progress mb-3" style="height: 4px;"><div id="passwordStrength" class="progress-bar" style="width: 0%;"></div></div>
                <div class="form-floating mb-4"><input type="password" name="confirm_password" id="confirm_password" class="form-control" minlength="8" required><label>Confirmar Contraseña</label></div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light w-50 fw-bold border" onclick="prevStep(4)">Atrás</button>
                    <button type="submit" id="btnRegistro" class="btn btn-primary w-50 fw-bold">CREAR CUENTA</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function showError(input, msg) { let err = input.parentElement.nextElementSibling; if(!err || !err.classList.contains('text-danger')) { input.parentElement.insertAdjacentHTML('afterend', `<div class="text-danger small mt-1 error-msg">${msg}</div>`); input.classList.add('border-danger'); } else { err.innerText = msg; } }
function clearError(input) { let err = input.parentElement.nextElementSibling; if(err && err.classList.contains('text-danger')) { err.remove(); input.classList.remove('border-danger'); } }
function validateStep(step) {
    let valid = true;
    if(step === 1) { ['nombre', 'apellido_paterno', 'apellido_materno'].forEach(id => { const el = document.getElementById(id); if(!el.value.trim()) { showError(el, 'Campo requerido'); valid = false; } else { clearError(el); } }); }
    else if (step === 2) { const em = document.getElementById('email'); if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em.value)) { showError(em, 'Correo inválido'); valid = false; } else clearError(em); const tel = document.getElementById('telefono'); if(!/^\d{10}$/.test(tel.value)) { showError(tel, 'Debe tener 10 dígitos numéricos'); valid = false; } else clearError(tel); }
    else if (step === 3) { ['fecha_nacimiento', 'genero'].forEach(id => { const el = document.getElementById(id); if(!el.value) { showError(el, 'Requerido'); valid = false; } else clearError(el); }); }
    return valid;
}
function nextStep(current) {
    if(!validateStep(current)) return;
    document.getElementById(`step-${current}`).classList.remove('active');
    document.getElementById(`step-${current+1}`).classList.add('active');
    document.getElementById(`ind-${current+1}`).classList.add('active');
    document.getElementById(`line-${current}`).classList.add('active');
    if (current + 1 === 4) { clearError(document.getElementById('password')); clearError(document.getElementById('confirm_password')); }
}
function prevStep(current) { document.getElementById(`step-${current}`).classList.remove('active'); document.getElementById(`step-${current-1}`).classList.add('active'); document.getElementById(`ind-${current}`).classList.remove('active'); document.getElementById(`line-${current-1}`).classList.remove('active'); }

document.getElementById('telefono').addEventListener('input', function() { this.value = this.value.replace(/\D/g, ''); });
document.getElementById('password').addEventListener('input', function() { let s = 0, v = this.value; if(v.length >= 8) s+=25; if(/[A-Z]/.test(v)) s+=25; if(/\d/.test(v)) s+=25; if(/\W/.test(v)) s+=25; const bar = document.getElementById('passwordStrength'); bar.style.width = s+'%'; bar.className = `progress-bar bg-${s<50?'danger':(s<75?'warning':'success')}`; });
document.getElementById('registroForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    if (!document.getElementById('step-4').classList.contains('active')) return;
    const p1 = document.getElementById('password').value, p2 = document.getElementById('confirm_password').value;
    if(p1.length < 8 || p1 !== p2) { showError(document.getElementById('confirm_password'), 'Contraseñas no coinciden o son cortas'); return; }
    const btn = document.getElementById('btnRegistro'); btn.disabled = true; btn.innerHTML = 'Procesando...';
    try {
        const res = await fetch('../api/auth_registro.php', { method: 'POST', body: new FormData(this) });

        if (!res.ok) {
            const errorData = await res.json().catch(() => ({ message: 'Error desconocido del servidor.' }));
            const message = errorData.message || `Error ${res.status}: ${res.statusText}`;
            document.getElementById('registroAlert').innerHTML = `<div class="alert alert-danger">${message}</div>`;
            btn.disabled = false; btn.innerHTML = 'CREAR CUENTA';
            return;
        }

        const data = await res.json();
        if(data.status === 'success') window.location.href = data.redirect; else { document.getElementById('registroAlert').innerHTML = `<div class="alert alert-danger">${data.message}</div>`; btn.disabled = false; btn.innerHTML = 'CREAR CUENTA'; }
    } catch(err) {
        document.getElementById('registroAlert').innerHTML = `<div class="alert alert-danger">Ocurrió un error de red o de comunicación. Por favor, intenta de nuevo.</div>`;
        btn.disabled = false; btn.innerHTML = 'CREAR CUENTA';
    }
});
</script>
<?php require_once __DIR__ . '/../src/views/layout/footer.php'; ?>