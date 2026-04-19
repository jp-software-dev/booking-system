<?php
/**
 * VISTA DE LOGIN ADMINISTRATIVO
 *
 * Interfaz de acceso restringido para el personal encargado de la gestión global
 * de citas y configuración del sistema.
 *
 * @requires session_start
 * @requires src/views/layout/header.php
 * @redirect admin.php si ya hay sesión de administrador activa.
 */

session_start();

if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header("Location: admin.php");
    exit();
}

require_once '../src/views/layout/header.php';
?>
<div class="d-flex justify-content-center align-items-center" style="min-height: 70vh;">
    <div class="card shadow-lg border-0 p-4" style="width: 28rem; border-radius: 25px; position: relative;">
        <a href="index.php" class="position-absolute top-0 start-0 m-3 text-secondary small">
            <i class="bi bi-arrow-left me-1"></i> Volver al sitio público
        </a>
        <div class="card-body">
            <h2 class="text-center fw-bold mb-4 text-primary">MediAgenda</h2>
            <p class="text-center text-muted mb-4 small">Acceso exclusivo para el Panel Administrativo</p>
            
            <div id="loginAlert"></div>

            <form id="formLogin">
                <div class="input-group mb-3 py-1">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-person text-muted"></i></span>
                    <input type="text" id="userInput" class="form-control border-start-0" placeholder="Usuario administrador" required>
                </div>

                <div class="input-group mb-4 py-1">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-lock text-muted"></i></span>
                    <input type="password" id="passInput" class="form-control border-start-0 border-end-0" placeholder="Contraseña" required>
                    <button class="btn btn-white border border-start-0" type="button" id="togglePassword">
                        <i class="bi bi-eye-slash" id="eyeIcon"></i>
                    </button>
                </div>

                <button type="submit" id="btnIngresar" class="btn btn-primary w-100 py-3 fw-bold shadow-sm rounded-3">
                    Ingresar al Sistema
                </button>
            </form>
        </div>
    </div>
</div>

<script>
const togglePassword = document.querySelector('#togglePassword');
const password = document.querySelector('#passInput');
const eyeIcon = document.querySelector('#eyeIcon');

togglePassword.addEventListener('click', () => {
    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
    password.setAttribute('type', type);
    eyeIcon.classList.toggle('bi-eye');
    eyeIcon.classList.toggle('bi-eye-slash');
});

document.getElementById('formLogin').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const btn = document.getElementById('btnIngresar');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verificando...';

    const res = await fetch('../api/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            usuario: document.getElementById('userInput').value, 
            password: password.value 
        })
    });
    
    const data = await res.json();

    if (data.success && data.role === 'admin') {
        window.location.href = data.redirect || 'admin.php';
    } else {
        document.getElementById('loginAlert').innerHTML = `<div class="alert alert-danger py-2 small text-center">${data.message || 'Error de autenticación'}</div>`;
        btn.disabled = false;
        btn.innerHTML = 'Ingresar al Sistema';
    }
});
</script>
<?php require_once '../src/views/layout/footer.php'; ?>