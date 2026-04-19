<?php
/**
 * VISTA DE RESTABLECIMIENTO DE CONTRASEÑA
 *
 * Interfaz pública donde el usuario ingresa su nueva contraseña tras hacer clic
 * en el enlace de recuperación enviado a su correo electrónico. Valida el token
 * de seguridad antes de mostrar el formulario.
 *
 * @requires session_start
 * @requires config/Database.php
 * @requires src/views/layout/header.php
 * @redirect recuperar.php si el token es inválido.
 */

session_start();

require_once '../config/Database.php';
require_once '../src/views/layout/header.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    header("Location: recuperar.php");
    exit();
}

$db = Database::getInstance();

// VERIFICACIÓN DE SEGURIDAD: Consulta que el token exista, esté vigente y no haya sido usado.
$stmt = $db->prepare("SELECT email FROM password_resets WHERE token = ? AND expira > NOW() AND usado = 0");
$stmt->execute([$token]);
$reset = $stmt->fetch();

if (!$reset) {
    echo '<div class="container py-5"><div class="alert alert-danger">El enlace es inválido o ha expirado.</div>';
    echo '<a href="recuperar.php" class="btn btn-primary">Solicitar nuevo enlace</a></div>';
    require_once '../src/views/layout/footer.php';
    exit();
}

$email = $reset['email'];
?>

<div class="d-flex align-items-center justify-content-center bg-light py-5" style="min-height: 70vh;">
    <div class="card border-0 shadow-lg p-5" style="max-width: 500px; width: 100%; border-radius: 20px;">
        <h3 class="text-center fw-bold text-dark mb-4">Restablecer Contraseña</h3>
        <p class="text-center text-muted mb-4">Ingresa tu nueva contraseña para el correo <strong><?php echo htmlspecialchars($email); ?></strong></p>

        <div id="restablecerAlert"></div>

        <form id="restablecerForm">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <div class="mb-3">
                <label for="password" class="form-label fw-bold text-secondary">Nueva contraseña</label>
                <div class="input-group">
                    <input type="password" id="password" name="password" class="form-control bg-light border-0 py-2" minlength="8" required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                        <i class="bi bi-eye-slash"></i>
                    </button>
                </div>
                <small class="text-muted">Mínimo 8 caracteres</small>
            </div>
            <div class="mb-4">
                <label for="confirm_password" class="form-label fw-bold text-secondary">Confirmar contraseña</label>
                <div class="input-group">
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control bg-light border-0 py-2" minlength="8" required>
                    <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                        <i class="bi bi-eye-slash"></i>
                    </button>
                </div>
            </div>
            <button type="submit" id="btnRestablecer" class="btn btn-primary w-100 py-3 fw-bold rounded-pill">Restablecer contraseña</button>
        </form>
    </div>
</div>

<script>
document.getElementById('togglePassword').addEventListener('click', function() {
    const password = document.getElementById('password');
    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
    password.setAttribute('type', type);
    this.querySelector('i').classList.toggle('bi-eye');
    this.querySelector('i').classList.toggle('bi-eye-slash');
});

document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
    const password = document.getElementById('confirm_password');
    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
    password.setAttribute('type', type);
    this.querySelector('i').classList.toggle('bi-eye');
    this.querySelector('i').classList.toggle('bi-eye-slash');
});

document.getElementById('restablecerForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const password = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    
    if (password !== confirm) {
        alert('Las contraseñas no coinciden');
        return;
    }
    
    const btn = document.getElementById('btnRestablecer');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
    
    const alertDiv = document.getElementById('restablecerAlert');

    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    data.password = password;

    try {
        const res = await fetch('../api/restablecer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await res.json();
        
        if (result.status === 'success') {
            alertDiv.innerHTML = '<div class="alert alert-success py-2 small text-center">' + result.message + '</div>';
            setTimeout(() => window.location.href = 'login_paciente.php', 2000);
        } else {
            alertDiv.innerHTML = '<div class="alert alert-danger py-2 small text-center">' + result.message + '</div>';
            btn.disabled = false;
            btn.innerHTML = 'Restablecer contraseña';
        }
    } catch (error) {
        alertDiv.innerHTML = '<div class="alert alert-danger py-2 small text-center">Error de conexión</div>';
        btn.disabled = false;
        btn.innerHTML = 'Restablecer contraseña';
    }
});
</script>

<?php require_once '../src/views/layout/footer.php'; ?>