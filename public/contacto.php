<?php
// VISTA DE CONTACTO: Interfaz dedicada a mostrar la información de ubicación, medios de comunicación y horarios de operación de la clínica.

// GESTIÓN DE SESIÓN: Reanuda la sesión para personalizar la experiencia del usuario y determinar las rutas de redirección.
session_start();

// DEPENDENCIAS: Carga la cabecera global que incluye los estilos CSS y la navegación principal del sitio.
require_once '../src/views/layout/header.php';

// RUTA DINÁMICA: Define el destino del botón de acción; si el usuario está autenticado lo envía a su agenda, de lo contrario al login.
$ruta_agendar = isset($_SESSION['user_id']) ? 'agenda.php' : 'login_paciente.php';
?>

<div class="container py-5" style="min-height: 85vh;">
    <div class="text-center mb-5 pt-4">
        <h1 class="fw-bolder text-dark-blue display-5">Contáctanos</h1>
        <p class="text-secondary lead mx-auto" style="max-width: 600px;">
            Estamos aquí para resolver tus dudas y brindarte la mejor atención médica en el momento que lo necesites.
        </p>
    </div>

    <div class="row g-4 justify-content-center align-items-stretch">
        <div class="col-lg-5 col-md-6">
            <div class="card border-0 shadow-sm h-100 rounded-4 p-4">
                <h4 class="fw-bold text-dark-blue mb-4">Detalles de Contacto</h4>
                
                <div class="d-flex align-items-center mb-4">
                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle text-primary me-4">
                        <i class="bi bi-geo-alt-fill fs-3"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-1">Ubicación</h6>
                        <p class="text-secondary mb-0 small">Zaragoza, Calimaya, Estado de México</p>
                    </div>
                </div>

                <div class="d-flex align-items-center mb-4">
                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle text-primary me-4">
                        <i class="bi bi-telephone-fill fs-3"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-1">Teléfono</h6>
                        <p class="text-secondary mb-0 small">+52 729 963 5417</p>
                    </div>
                </div>

                <div class="d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle text-primary me-4">
                        <i class="bi bi-envelope-fill fs-3"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-1">Correo Electrónico</h6>
                        <p class="text-secondary mb-0 small">contacto@agendamedica.mx</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5 col-md-6">
            <div class="card border-0 shadow-sm h-100 rounded-4 bg-primary text-white p-4 position-relative overflow-hidden">
                <i class="bi bi-hospital position-absolute opacity-10" style="bottom: -20px; right: -20px; font-size: 12rem;"></i>
                <h4 class="fw-bold mb-4 text-white"><i class="bi bi-clock-history me-2"></i> Horarios de Atención</h4>
                
                <div class="d-flex justify-content-between mb-3 border-bottom border-light border-opacity-25 pb-3">
                    <span class="fs-6 fw-medium">Lunes - Viernes</span>
                    <span class="fw-bold fs-6">07:00 AM - 08:00 PM</span>
                </div>
                <div class="d-flex justify-content-between mb-3 border-bottom border-light border-opacity-25 pb-3">
                    <span class="fs-6 fw-medium">Sábados</span>
                    <span class="fw-bold fs-6">08:00 AM - 02:00 PM</span>
                </div>
                <div class="d-flex justify-content-between mb-5">
                    <span class="fs-6 fw-medium">Domingos</span>
                    <span class="fw-bold fs-6 text-warning"><i class="bi bi-exclamation-circle-fill me-1"></i> Solo Urgencias</span>
                </div>

                <div class="mt-auto pt-4">
                    <a href="<?php 
                    // ECHO DINÁMICO: Imprime la ruta calculada al inicio para llevar al usuario al agendamiento o al portal de acceso.
                    echo $ruta_agendar; 
                    ?>" class="btn btn-light w-100 fw-bold py-3 fs-6 rounded-pill text-primary shadow-sm">
                        Agendar Cita Ahora
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// PIE DE PÁGINA: Carga el layout del footer para cerrar el contenedor principal y cargar los scripts de Bootstrap.
require_once '../src/views/layout/footer.php'; 
?>