<?php
require_once __DIR__ . '/../config/config.php';
if (isset($_SESSION['user_id'])) { header("Location: " . ($_SESSION['role'] === 'admin' ? "admin" : "agenda")); exit(); }
require_once __DIR__ . '/../src/views/layout/header.php';
?>
<div class="d-flex align-items-center justify-content-center auth-bg py-5" style="min-height: 85vh;">
    <div class="card auth-card p-5 w-100" style="max-width: 460px;">
        <div class="text-center mb-4"><h3 class="fw-bold text-dark-blue">Iniciar Sesión</h3></div>
        <div id="loginAlert"></div>
        <form id="formLogin" autocomplete="off">
            <div class="mb-4">
                <label for="identificadorInput" class="form-label fw-bold text-secondary">Usuario o Correo</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-primary"><i class="bi bi-person-fill"></i></span>
                    <input type="text" id="identificadorInput" class="form-control border-start-0 ps-0" required>
                </div>
            </div>
            <div class="mb-5">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="passInput" class="form-label fw-bold text-secondary mb-0">Contraseña</label>
                    <a href="recuperar.php" class="text-decoration-none fw-medium" style="font-size: 13px; color: var(--primary);">¿Olvidaste tu contraseña?</a>
                </div>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-primary"><i class="bi bi-lock-fill"></i></span>
                    <input type="password" id="passInput" class="form-control border-start-0 border-end-0 ps-0" required>
                    <button class="btn btn-outline-secondary border-start-0 bg-white" type="button" id="togglePassword"><i class="bi bi-eye" id="eyeIcon"></i></button>
                </div>
            </div>
            <button type="submit" id="btnIngresar" class="btn btn-primary w-100 btn-lg fw-bold shadow-sm mb-4">INGRESAR</button>
            <div class="text-center"><span class="text-secondary small">¿No tienes cuenta?</span><a href="registro" class="text-decoration-none fw-bold small ms-1">Regístrate aquí</a></div>
        </form>
    </div>
</div>
<script>
document.getElementById('togglePassword').addEventListener('click', function() {
    const password = document.getElementById('passInput'), eyeIcon = document.getElementById('eyeIcon');
    password.setAttribute('type', password.getAttribute('type') === 'password' ? 'text' : 'password');
    eyeIcon.classList.toggle('bi-eye'); eyeIcon.classList.toggle('bi-eye-slash');
});
document.getElementById('formLogin').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('btnIngresar'); btn.disabled = true; btn.innerHTML = 'Verificando...';
    const res = await fetch('../api/auth.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ usuario: document.getElementById('identificadorInput').value, email: document.getElementById('identificadorInput').value, password: document.getElementById('passInput').value }) });
    const data = await res.json();
    if (data.success) window.location.href = data.redirect;
    else { document.getElementById('loginAlert').innerHTML = `<div class="alert alert-danger py-2 small text-center">${data.message || 'Credenciales incorrectas'}</div>`; btn.disabled = false; btn.innerHTML = 'INGRESAR'; }
});
</script>
<?php require_once __DIR__ . '/../src/views/layout/footer.php'; ?>