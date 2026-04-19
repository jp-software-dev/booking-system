<?php
/**
 * VISTA DE LOGIN PARA PACIENTES
 *
 * Interfaz principal para que los pacientes registrados accedan a su cuenta
 * y gestionen sus citas médicas.
 *
 * @requires session_start
 * @requires src/views/layout/header.php
 * @redirect agenda.php o admin.php si ya hay sesión activa.
 */

session_start();

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin.php");
    } else {
        header("Location: agenda.php");
    }
    exit();
}

require_once '../src/views/layout/header.php';
?>

<div class="d-flex align-items-center justify-content-center bg-light py-5" style="min-height: 85vh;">
    <div class="card border-0 shadow-lg p-5 w-100" style="max-width: 440px; border-radius: 20px;">
        
        <div class="text-center mb-4">
            <h3 class="fw-bold text-dark-blue">Portal Pacientes</h3>
            <p class="text-secondary small">Inicia sesión para gestionar tus citas</p>
        </div>
        
        <div id="loginAlert"></div>

        <form id="formLoginPaciente">
            <div class="mb-3">
                <label for="emailInput" class="form-label small fw-bold text-secondary">Correo electrónico</label>
                <input type="email" id="emailInput" class="form-control bg-light border-0 py-2" placeholder="ejemplo@correo.com" required>
            </div>
            <div class="mb-4">
                <label for="passInput" class="form-label small fw-bold text-secondary">Contraseña</label>
                <div class="input-group">
                    <input type="password" id="passInput" class="form-control bg-light border-0 py-2" placeholder="••••••••" required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                        <i class="bi bi-eye-slash"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" id="btnIngresar" class="btn btn-primary w-100 py-3 fw-bold rounded-pill mb-3">INGRESAR AL SISTEMA</button>

            <div class="text-center">
                <span class="text-secondary small">¿No tienes cuenta?</span>
                <a href="registro.php" class="text-decoration-none fw-bold text-primary ms-1">Regístrate aquí</a>
            </div>
            <div class="text-center mt-3">
                <a href="recuperar.php" class="text-muted small">¿Olvidaste tu contraseña?</a>
            </div>
        </form>

    </div>
</div>

<script>
document.getElementById('togglePassword').addEventListener('click', function() {
    const password = document.getElementById('passInput');
    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
    password.setAttribute('type', type);
    this.querySelector('i').classList.toggle('bi-eye');
    this.querySelector('i').classList.toggle('bi-eye-slash');
});

document.getElementById('formLoginPaciente').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const btn = document.getElementById('btnIngresar');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verificando...';

    const res = await fetch('../api/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            email: document.getElementById('emailInput').value, 
            password: document.getElementById('passInput').value 
        })
    });
    
    const data = await res.json();

    if (data.success && data.role === 'paciente') {
        window.location.href = data.redirect || 'agenda.php';
    } else {
        document.getElementById('loginAlert').innerHTML = `<div class="alert alert-danger py-2 small text-center">${data.message || 'Credenciales incorrectas'}</div>`;
        btn.disabled = false;
        btn.innerHTML = 'INGRESAR AL SISTEMA';
    }
});
</script>

<?php require_once '../src/views/layout/footer.php'; ?>