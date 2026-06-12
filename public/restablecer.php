<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../src/views/layout/header.php';

$token = $_GET['token'] ?? '';
if (empty($token)) { header("Location: recuperar"); exit(); }

$db = Database::getInstance();
$stmt = $db->prepare("SELECT email FROM password_resets WHERE token = ? AND expira > NOW() AND usado = 0");
$stmt->execute([$token]);
if (!$stmt->fetch()) { echo '<div class="container py-5 text-center"><div class="alert alert-danger py-4 px-5"><h4>Enlace Inválido</h4><p>El enlace ha expirado o ya fue utilizado.</p><a href="recuperar" class="btn btn-outline-danger mt-2">Solicitar nuevo</a></div></div>'; require_once __DIR__ . '/../src/views/layout/footer.php'; exit; }
?>
<div class="d-flex align-items-center justify-content-center auth-bg py-5" style="min-height: 85vh;">
    <div class="card auth-card p-5 w-100" style="max-width: 450px;">
        <div class="text-center mb-4"><h3 class="fw-bold text-dark-blue">Nueva Contraseña</h3></div>
        <div id="restablecerAlert"></div>
        <form id="formRestablecer">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <div class="mb-3">
                <label class="form-label fw-bold text-secondary">Contraseña Nueva</label>
                <div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-lock"></i></span><input type="password" id="password" name="password" class="form-control border-start-0 ps-0" required minlength="8"></div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-bold text-secondary">Confirmar Contraseña</label>
                <div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-lock-fill"></i></span><input type="password" id="confirm_password" class="form-control border-start-0 ps-0" required minlength="8"></div>
            </div>
            <button type="submit" id="btnRestablecer" class="btn btn-primary w-100 btn-lg fw-bold">Guardar contraseña</button>
        </form>
    </div>
</div>
<script>
document.getElementById('formRestablecer').addEventListener('submit', async (e) => {
    e.preventDefault();
    if(document.getElementById('password').value !== document.getElementById('confirm_password').value) { document.getElementById('restablecerAlert').innerHTML = '<div class="alert alert-danger">No coinciden</div>'; return; }
    const btn = document.getElementById('btnRestablecer'); btn.disabled = true; btn.innerHTML = 'Procesando...';
    try {
        const res = await fetch('../api/restablecer.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(Object.fromEntries(new FormData(e.target))) });
        const result = await res.json();
        if (result.status === 'success') { document.getElementById('restablecerAlert').innerHTML = '<div class="alert alert-success">' + result.message + '</div>'; setTimeout(() => window.location.href = 'login', 2000); } 
        else { document.getElementById('restablecerAlert').innerHTML = '<div class="alert alert-danger">' + result.message + '</div>'; btn.disabled = false; btn.innerHTML = 'Guardar contraseña'; }
    } catch (error) { btn.disabled = false; btn.innerHTML = 'Guardar contraseña'; }
});
</script>
<?php require_once __DIR__ . '/../src/views/layout/footer.php'; ?>