<?php
// VISTA DE LOGIN ADMINISTRATIVO: Interfaz de acceso restringido para el personal encargado de la gestión global de citas.
session_start();

// PROTECCIÓN DE RUTA: Si ya existe una sesión de administrador activa, redirige automáticamente al panel para evitar logueos redundantes.
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header("Location: admin.php");
    exit();
}

// DEPENDENCIAS: Carga la estructura de la cabecera (head, navbar) para mantener la identidad visual del proyecto.
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
// ELEMENTOS DE INTERFAZ: Selecciona los nodos del DOM necesarios para manipular la visibilidad de la contraseña y el envío del formulario.
const togglePassword = document.querySelector('#togglePassword');
const password = document.querySelector('#passInput');
const eyeIcon = document.querySelector('#eyeIcon');

// VISIBILIDAD DE CREDENCIALES: Evento que alterna el atributo 'type' entre texto y password para permitir al admin verificar su clave antes de entrar.
togglePassword.addEventListener('click', () => {
    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
    password.setAttribute('type', type);
    eyeIcon.classList.toggle('bi-eye');
    eyeIcon.classList.toggle('bi-eye-slash');
});

// AUTENTICACIÓN ASÍNCRONA: Captura el envío del formulario para procesar las credenciales mediante la Fetch API sin recargar el navegador.
document.getElementById('formLogin').addEventListener('submit', async (e) => {
    
    // PREVENCIÓN: Detiene la acción por defecto del navegador para delegar la comunicación con el servidor al script de JS.
    e.preventDefault();
    
    // ESTADO DE CARGA: Deshabilita el botón e inserta un spinner para informar visualmente que el servidor está validando el acceso.
    const btn = document.getElementById('btnIngresar');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verificando...';

    // PETICIÓN API: Envía el usuario y la contraseña en formato JSON al endpoint de autenticación especializado en administradores.
    const res = await fetch('../api/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            usuario: document.getElementById('userInput').value, 
            password: password.value 
        })
    });
    
    // LECTURA DE RESPUESTA: Convierte el flujo de datos JSON del servidor en un objeto literal de JavaScript para evaluar el resultado.
    const data = await res.json();

    // REDIRECCIÓN SEGURA: Si la API confirma las credenciales y el rol de administrador, redirige al dashboard principal del sistema.
    if (data.success && data.role === 'admin') {
        window.location.href = data.redirect || 'admin.php';
    } else {
        // MANEJO DE ERROR: Muestra un mensaje de alerta rojo en caso de credenciales incorrectas y restaura la funcionalidad del botón.
        document.getElementById('loginAlert').innerHTML = `<div class="alert alert-danger py-2 small text-center">${data.message || 'Error de autenticación'}</div>`;
        btn.disabled = false;
        btn.innerHTML = 'Ingresar al Sistema';
    }
});
</script>
<?php 
// PIE DE PÁGINA: Renderiza el layout del footer para cerrar las etiquetas del cuerpo y cargar scripts de Bootstrap.
require_once '../src/views/layout/footer.php'; 
?>