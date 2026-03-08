<?php
// VISTA DE SOLICITUD: Interfaz para que el usuario ingrese su correo y dispare el proceso de recuperación de cuenta.
session_start();

// PROTECCIÓN DE RUTA: Si el usuario ya tiene una sesión activa, lo redirige al inicio para evitar que use funciones de recuperación innecesarias.
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// DEPENDENCIAS: Carga la cabecera visual y los estilos globales del proyecto.
require_once '../src/views/layout/header.php';
?>

<div class="d-flex align-items-center justify-content-center bg-light py-5" style="min-height: 70vh;">
    <div class="card border-0 shadow-lg p-5" style="max-width: 500px; width: 100%; border-radius: 20px; position: relative;">
        <a href="login_paciente.php" class="position-absolute top-0 start-0 m-3 text-secondary small">
            <i class="bi bi-arrow-left me-1"></i> Volver al inicio de sesión
        </a>
        <h3 class="text-center fw-bold text-dark mb-4 mt-4">Recuperar Contraseña</h3>
        <p class="text-center text-muted mb-4">Ingresa tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.</p>
        <div id="recuperarAlert"></div>
        <form id="recuperarForm">
            <div class="mb-3">
                <label for="email" class="form-label fw-bold text-secondary">Correo electrónico</label>
                <input type="email" id="email" name="email" class="form-control bg-light border-0 py-2" required>
            </div>
            <button type="submit" id="btnRecuperar" class="btn btn-primary w-100 py-3 fw-bold rounded-pill">Enviar enlace</button>
        </form>
    </div>
</div>

<script>
// PROCESAMIENTO ASÍNCRONO: Gestiona la solicitud de recuperación mediante Fetch API para ofrecer una respuesta inmediata sin recargar la página.
document.getElementById('recuperarForm').addEventListener('submit', async (e) => {
    
    // PREVENCIÓN: Detiene el envío estándar del formulario para controlar la validación y el estado visual mediante JS.
    e.preventDefault();
    
    const btn = document.getElementById('btnRecuperar');
    const email = document.getElementById('email').value;
    const alertDiv = document.getElementById('recuperarAlert');
    
    // INTERFAZ DE CARGA: Deshabilita el botón e inserta un spinner para indicar al usuario que el servidor está procesando el envío del correo.
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
    alertDiv.innerHTML = '';
    
    try {
        // PETICIÓN API: Envía el correo electrónico en formato JSON al endpoint del backend encargado de generar el token de seguridad.
        const res = await fetch('../api/recuperar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email })
        });
        
        // LECTURA DE RESPUESTA: Decodifica el resultado del servidor para determinar si el proceso fue exitoso o hubo un error de validación.
        const data = await res.json();
        
        // MANEJO DE ÉXITO: Si la respuesta es positiva, muestra un mensaje verde y limpia el campo de texto para mayor seguridad.
        if (data.status === 'success') {
            alertDiv.innerHTML = '<div class="alert alert-success py-2 small text-center">' + data.message + '</div>';
            document.getElementById('email').value = '';
        } else {
            // MANEJO DE ERROR LÓGICO: Muestra una alerta roja con el mensaje específico devuelto por el servidor (ej. formato de correo inválido).
            alertDiv.innerHTML = '<div class="alert alert-danger py-2 small text-center">' + data.message + '</div>';
        }
    } catch (error) {
        // ERROR CRÍTICO: Atrapa fallos de red o caídas del servicio SMTP, informando al usuario que verifique su conexión.
        alertDiv.innerHTML = '<div class="alert alert-danger py-2 small text-center">Error de conexión. Verifica que el servidor esté funcionando.</div>';
    } finally {
        // RESTAURACIÓN: Devuelve el botón a su estado original (habilitado) sin importar si la petición fue exitosa o fallida.
        btn.disabled = false;
        btn.innerHTML = 'Enviar enlace';
    }
});
</script>

<?php 
// PIE DE PÁGINA: Carga el layout del footer para cerrar las etiquetas HTML y cargar scripts globales.
require_once '../src/views/layout/footer.php'; 
?>