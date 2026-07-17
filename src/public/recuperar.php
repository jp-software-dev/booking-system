<?php
// Se reubicó el botón Volver dentro del contenedor alineado a la tarjeta para evitar desbordes visuales
require_once __DIR__ . '/../config/config.php';
if (isset($_SESSION['user_id'])) { header("Location: " . ($_SESSION['role'] === 'admin' ? "admin.php" : "agenda.php")); exit(); }
require_once __DIR__ . '/../src/views/layout/header.php';
?>
<div class="d-flex align-items-center justify-content-center auth-bg py-5" style="min-height: 85vh;">
    <div class="card auth-card p-4 p-md-5 w-100" style="max-width: 460px;">
        <div class="mb-3">
            <a href="login.php" class="text-decoration-none text-muted fw-bold small"><i class="bi bi-arrow-left me-1"></i> Volver</a>
        </div>
        <div class="text-center mb-4">
            <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;"><i class="bi bi-lock-fill text-primary fs-3"></i></div>
            <h3 class="fw-bold text-dark-blue">¿Olvidaste tu contraseña?</h3>
            <p class="text-secondary small">Ingresa tu correo y te enviaremos un enlace para restablecerla.</p>
        </div>
        <div id="recuperarAlert"></div>
        <form id="formRecuperar">
            <div class="mb-4">
                <label for="emailInput" class="form-label small fw-bold text-secondary">Correo electrónico</label>
                <input type="email" id="emailInput" class="form-control" required autofocus>
            </div>
            <button type="submit" id="btnEnviar" class="btn btn-primary w-100 py-3 fw-bold mb-3">Enviar enlace</button>
        </form>
    </div>
</div>
<script>
document.getElementById('formRecuperar').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnEnviar'), alertEl = document.getElementById('recuperarAlert');
    btn.disabled = true; btn.innerHTML = 'Enviando...'; alertEl.innerHTML = '';
    try {
        const res = await fetch('../api/recuperar.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email: document.getElementById('emailInput').value.trim() }) });
        const data = await res.json();
        if (data.status === 'success') { alertEl.innerHTML = `<div class="alert alert-success py-2 small text-center">${data.message}</div>`; document.getElementById('formRecuperar').reset(); }
        else { alertEl.innerHTML = `<div class="alert alert-danger py-2 small text-center">${data.message}</div>`; }
    } catch (err) { alertEl.innerHTML = `<div class="alert alert-danger py-2 small text-center">Error de conexión.</div>`; }
    finally { btn.disabled = false; btn.innerHTML = 'Enviar enlace'; }
});
</script>
<?php require_once __DIR__ . '/../src/views/layout/footer.php'; ?>