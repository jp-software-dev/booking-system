<?php
/**
 * VISTA DE RECUPERACIÓN DE CONTRASEÑA
 *
 * En GET: muestra el formulario para ingresar el correo.
 * El formulario envía la petición al endpoint API via fetch (POST JSON).
 */

session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] === 'admin' ? "admin.php" : "agenda.php"));
    exit();
}

require_once '../src/views/layout/header.php';
?>

<div class="d-flex align-items-center justify-content-center bg-light py-5" style="min-height: 85vh;">
    <div class="card border-0 shadow-lg p-5 w-100" style="max-width: 440px; border-radius: 20px;">

        <div class="text-center mb-4">
            <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;">
                <i class="bi bi-lock-fill text-primary fs-3"></i>
            </div>
            <h3 class="fw-bold text-dark-blue">¿Olvidaste tu contraseña?</h3>
            <p class="text-secondary small">Ingresa tu correo y te enviaremos un enlace para restablecerla.</p>
        </div>

        <div id="recuperarAlert"></div>

        <form id="formRecuperar">
            <div class="mb-4">
                <label for="emailInput" class="form-label small fw-bold text-secondary">Correo electrónico</label>
                <input type="email" id="emailInput" class="form-control bg-light border-0 py-2"
                       placeholder="ejemplo@correo.com" required autofocus>
            </div>

            <button type="submit" id="btnEnviar" class="btn btn-primary w-100 py-3 fw-bold rounded-pill mb-3">
                Enviar enlace de recuperación
            </button>

            <div class="text-center">
                <a href="login_paciente.php" class="text-muted small">
                    <i class="bi bi-arrow-left me-1"></i> Volver al inicio de sesión
                </a>
            </div>
        </form>

    </div>
</div>

<script>
document.getElementById('formRecuperar').addEventListener('submit', async function(e) {
    e.preventDefault();

    const btn     = document.getElementById('btnEnviar');
    const alertEl = document.getElementById('recuperarAlert');
    const email   = document.getElementById('emailInput').value.trim();

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
    alertEl.innerHTML = '';

    try {
        const res = await fetch('../api/recuperar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email })
        });

        const data = await res.json();

        if (data.status === 'success') {
            alertEl.innerHTML = `
                <div class="alert alert-success py-2 small text-center">
                    <i class="bi bi-check-circle me-1"></i> ${data.message}
                </div>`;
            document.getElementById('formRecuperar').reset();
        } else {
            alertEl.innerHTML = `
                <div class="alert alert-danger py-2 small text-center">
                    ${data.message || 'Ocurrió un error. Intenta de nuevo.'}
                </div>`;
        }
    } catch (err) {
        alertEl.innerHTML = `
            <div class="alert alert-danger py-2 small text-center">
                Error de conexión. Verifica tu red e intenta de nuevo.
            </div>`;
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Enviar enlace de recuperación';
    }
});
</script>

<?php require_once '../src/views/layout/footer.php'; ?>