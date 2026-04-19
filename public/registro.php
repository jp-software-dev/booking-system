<?php
/**
 * VISTA DE REGISTRO DE PACIENTES
 *
 * Interfaz para que los nuevos pacientes creen una cuenta en el sistema.
 * Incluye validaciones frontend de CURP, edad, formato de teléfono y coincidencia
 * de contraseñas.
 *
 * @requires session_start
 * @requires src/views/layout/header.php
 * @redirect agenda.php o admin.php si ya hay sesión activa.
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
        <h3 class="text-center fw-bold text-dark mb-4">Crear Cuenta</h3>
        <p class="text-center text-muted mb-4">Regístrate para gestionar tus citas</p>

        <form id="registroForm">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="nombre" class="form-label fw-bold text-secondary">Nombre</label>
                    <input type="text" id="nombre" name="nombre" class="form-control bg-light border-0" required>
                </div>
                <div class="col-md-6">
                    <label for="edad" class="form-label fw-bold text-secondary">Edad</label>
                    <input type="number" id="edad" name="edad" class="form-control bg-light border-0" min="18" max="120" required>
                    <small class="text-muted" style="font-size: 0.75rem;">Debe ser mayor de 18 años</small>
                </div>

                <div class="col-md-6">
                    <label for="apellido_paterno" class="form-label fw-bold text-secondary">Apellido Paterno</label>
                    <input type="text" id="apellido_paterno" name="apellido_paterno" class="form-control bg-light border-0" required>
                </div>
                <div class="col-md-6">
                    <label for="apellido_materno" class="form-label fw-bold text-secondary">Apellido Materno</label>
                    <input type="text" id="apellido_materno" name="apellido_materno" class="form-control bg-light border-0">
                </div>

                <div class="col-md-6">
                    <label for="curp" class="form-label fw-bold text-secondary">CURP</label>
                    <input type="text" id="curp" name="curp" class="form-control bg-light border-0" maxlength="18" required style="text-transform: uppercase;">
                    <small class="text-muted small" style="font-size: 0.75rem;">18 caracteres alfanuméricos</small>
                </div>
                <div class="col-md-6">
                    <label for="genero" class="form-label fw-bold text-secondary">Género</label>
                    <select id="genero" name="genero" class="form-select bg-light border-0" required>
                        <option value="" disabled selected>Seleccione</option>
                        <option value="Masculino">Masculino</option>
                        <option value="Femenino">Femenino</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="email" class="form-label fw-bold text-secondary">Correo electrónico</label>
                    <input type="email" id="email" name="email" class="form-control bg-light border-0" required>
                </div>
                <div class="col-md-6">
                    <label for="telefono" class="form-label fw-bold text-secondary">Teléfono</label>
                    <input type="tel" id="telefono" name="telefono" class="form-control bg-light border-0" pattern="[0-9]{10}" maxlength="10" required>
                    <small class="text-muted small" style="font-size: 0.75rem;">10 dígitos numéricos</small>
                </div>

                <div class="col-md-6">
                    <label for="password" class="form-label fw-bold text-secondary">Contraseña</label>
                    <div class="input-group">
                        <input type="password" id="password" name="password" class="form-control bg-light border-0" minlength="8" required>
                        <button class="btn btn-light border-0" type="button" id="togglePassword"><i class="bi bi-eye-slash"></i></button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="confirm_password" class="form-label fw-bold text-secondary">Confirmar contraseña</label>
                    <div class="input-group">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control bg-light border-0" minlength="8" required>
                        <button class="btn btn-light border-0" type="button" id="toggleConfirmPassword"><i class="bi bi-eye-slash"></i></button>
                    </div>
                </div>
            </div>

            <button type="submit" id="btnRegistro" class="btn btn-primary w-100 py-3 fw-bold rounded-pill mt-4">CREAR CUENTA</button>
        </form>

        <div class="text-center mt-3">
            <span class="text-muted small">¿Ya tienes cuenta?</span>
            <a href="login_paciente.php" class="text-primary fw-bold ms-1">Inicia sesión</a>
        </div>
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

document.getElementById('registroForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const edadInput = document.getElementById('edad').value;
    if (edadInput < 18) {
        alert('Debes ser mayor de 18 años para registrarte en el sistema.');
        return;
    }
    
    const password = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    
    if (password !== confirm) {
        alert('Las contraseñas no coinciden');
        return;
    }
    
    const btn = document.getElementById('btnRegistro');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Registrando...';
    
    const formData = new FormData(this);
    
    try {
        const res = await fetch('../api/auth_registro.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await res.json();
        if (data.status === 'success') {
            window.location.href = data.redirect;
        } else {
            alert(data.message);
            btn.disabled = false;
            btn.innerHTML = 'CREAR CUENTA';
        }
    } catch (error) {
        alert('Ocurrió un error en el registro.');
        btn.disabled = false;
        btn.innerHTML = 'CREAR CUENTA';
    }
});
</script>

<?php require_once '../src/views/layout/footer.php'; ?>