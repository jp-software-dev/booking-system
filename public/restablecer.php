<?php
// VISTA DE RESTABLECIMIENTO: Interfaz pública donde el usuario ingresa su nueva contraseña tras hacer clic en el enlace de su correo.

// INICIALIZACIÓN: Inicia la sesión para garantizar que el layout (header/footer) cargue correctamente sus variables si es necesario.
session_start();

// DEPENDENCIAS: Importa la conexión a la base de datos y la cabecera visual de la página.
require_once '../config/Database.php';
require_once '../src/views/layout/header.php';

// EXTRACCIÓN DE TOKEN: Captura el código de seguridad que viaja visible en la URL (método GET).
$token = $_GET['token'] ?? '';

// PROTECCIÓN DE ACCESO: Si alguien intenta entrar a esta vista directamente sin un token, es redirigido a la página de solicitar recuperación.
if (empty($token)) {
    header("Location: recuperar.php");
    exit();
}

// CONEXIÓN DB: Obtiene la instancia única activa de la base de datos (Singleton).
$db = Database::getInstance();

// VERIFICACIÓN DE SEGURIDAD: Consulta la base de datos asegurando que el token exista, siga vigente (menor a 1 hora) y no esté marcado como usado.
$stmt = $db->prepare("SELECT email FROM password_resets WHERE token = ? AND expira > NOW() AND usado = 0");
$stmt->execute([$token]);
$reset = $stmt->fetch();

// MANEJO DE TOKEN INVÁLIDO: Si el enlace expiró o es falso, detiene el renderizado del formulario, muestra un error y carga el pie de página.
if (!$reset) {
    echo '<div class="container py-5"><div class="alert alert-danger">El enlace es inválido o ha expirado.</div>';
    echo '<a href="recuperar.php" class="btn btn-primary">Solicitar nuevo enlace</a></div>';
    require_once '../src/views/layout/footer.php';
    exit();
}

// RECUPERACIÓN DE CORREO: Resguarda el email asociado a ese token válido para mostrarlo como confirmación visual en la interfaz.
$email = $reset['email'];
?>

<div class="d-flex align-items-center justify-content-center bg-light py-5" style="min-height: 70vh;">
    <div class="card border-0 shadow-lg p-5" style="max-width: 500px; width: 100%; border-radius: 20px;">
        <h3 class="text-center fw-bold text-dark mb-4">Restablecer Contraseña</h3>
        <p class="text-center text-muted mb-4">Ingresa tu nueva contraseña para el correo <strong><?php 
        // PREVENCIÓN XSS: Limpia el texto del correo antes de imprimirlo en pantalla para bloquear posibles ataques de inyección de código.
        echo htmlspecialchars($email); 
        ?></strong></p>

        <div id="restablecerAlert"></div>

        <form id="restablecerForm">
            <input type="hidden" name="token" value="<?php 
            // TOKEN OCULTO: Inserta el token validado en un campo invisible para que se envíe de vuelta al backend al enviar el formulario.
            echo htmlspecialchars($token); 
            ?>">
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
// VISIBILIDAD DE CONTRASEÑA: Evento que cambia el tipo de input entre 'password' (oculto) y 'text' (visible) al hacer clic en el ícono del ojo.
document.getElementById('togglePassword').addEventListener('click', function() {
    const password = document.getElementById('password');
    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
    password.setAttribute('type', type);
    this.querySelector('i').classList.toggle('bi-eye');
    this.querySelector('i').classList.toggle('bi-eye-slash');
});

// VISIBILIDAD DE CONFIRMACIÓN: Replica la misma lógica del botón anterior pero aplicada específicamente al campo de confirmar contraseña.
document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
    const password = document.getElementById('confirm_password');
    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
    password.setAttribute('type', type);
    this.querySelector('i').classList.toggle('bi-eye');
    this.querySelector('i').classList.toggle('bi-eye-slash');
});

// PROCESAMIENTO DE FORMULARIO: Captura el evento de envío para validarlo localmente y mandarlo al backend por medio de Fetch API.
document.getElementById('restablecerForm').addEventListener('submit', async (e) => {
    
    // PREVENCIÓN DE RECARGA: Detiene el comportamiento natural del formulario (que recargaría la página) para manejarlo por Javascript.
    e.preventDefault();
    
    const password = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    
    // VALIDACIÓN FRONTEND: Revisa inmediatamente que las dos contraseñas escritas coincidan antes de gastar recursos enviando la petición al servidor.
    if (password !== confirm) {
        alert('Las contraseñas no coinciden');
        return;
    }
    
    // INTERFAZ DE CARGA: Deshabilita el botón principal e inserta un spinner animado para evitar que el usuario mande la solicitud dos veces por desesperación.
    const btn = document.getElementById('btnRestablecer');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
    
    const alertDiv = document.getElementById('restablecerAlert');

    // CONSTRUCCIÓN DE DATOS: Agrupa automáticamente todos los inputs del formulario (incluyendo el token oculto) en un objeto estructurado.
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    data.password = password;

    try {
        // PETICIÓN ASÍNCRONA: Envía los datos empaquetados en formato JSON directamente a tu archivo API backend sin cambiar de página.
        const res = await fetch('../api/restablecer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        // LECTURA DE RESPUESTA: Espera y decodifica la respuesta del servidor desde su formato JSON original a un objeto de Javascript.
        const result = await res.json();
        
        // MANEJO DE ÉXITO: Si la API responde positivamente, muestra una alerta verde y espera 2 segundos exactos antes de redirigir al paciente a iniciar sesión.
        if (result.status === 'success') {
            alertDiv.innerHTML = '<div class="alert alert-success py-2 small text-center">' + result.message + '</div>';
            setTimeout(() => window.location.href = 'login_paciente.php', 2000);
        } else {
            // MANEJO DE ERROR LÓGICO: Si la API detecta un error (ej. token usado o expirado al último segundo), muestra alerta roja y reactiva el botón.
            alertDiv.innerHTML = '<div class="alert alert-danger py-2 small text-center">' + result.message + '</div>';
            btn.disabled = false;
            btn.innerHTML = 'Restablecer contraseña';
        }
    } catch (error) {
        // MANEJO DE FALLO DE RED: Atrapa errores críticos (como la caída del servidor local o falta de internet) y restaura la interfaz.
        alertDiv.innerHTML = '<div class="alert alert-danger py-2 small text-center">Error de conexión</div>';
        btn.disabled = false;
        btn.innerHTML = 'Restablecer contraseña';
    }
});
</script>

<?php require_once '../src/views/layout/footer.php'; ?>