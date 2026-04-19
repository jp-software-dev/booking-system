<?php
/**
 * CABECERA GLOBAL (HEADER)
 *
 * Control de navegación que gestiona el estado de la sesión, identifica la página
 * actual y define rutas dinámicas para la interfaz. Renderiza el navbar principal
 * y el modal de contacto.
 *
 * @requires session_start (si no se ha iniciado)
 * @requires basename($_SERVER['PHP_SELF']) para detectar la página actual.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

$current_page = basename($_SERVER['PHP_SELF']);

if (isset($_SESSION['user_id'])) {
    $ruta_agendar = 'agenda.php';
} else {
    $ruta_agendar = 'login_paciente.php';
}

$es_admin = ($_SESSION['role'] ?? '') === 'admin';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediAgenda | Gestión Hospitalaria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <?php if ($current_page === 'agenda.php'): ?>
        <link rel="stylesheet" href="assets/css/calendar.css">
    <?php endif; ?>
</head>
<body>

<nav class="navbar navbar-expand-lg bg-white sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            <i class="bi bi-hospital-fill me-2 text-primary"></i> MEDI<span class="text-primary">AGENDA</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item"><a class="nav-link" href="index.php">Inicio</a></li>
                <li class="nav-item">
                    <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#contactoModal">Contacto</a>
                </li>
                <li class="nav-item ms-lg-3">
                    <a href="<?php echo $ruta_agendar; ?>" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                        <i class="bi bi-calendar-check me-2"></i>Agendar Cita
                    </a>
                </li>
            </ul>
            <div class="d-flex align-items-center mt-3 mt-lg-0">
                <?php if (isset($_SESSION['user_name'])): ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle rounded-pill px-4 fw-bold" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-2"></i> Hola, <?php echo htmlspecialchars(ucwords(strtolower($_SESSION['user_name']))); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2 p-2">
                            <li><a class="dropdown-item rounded-3" href="agenda.php"><i class="bi bi-calendar-week me-2 text-primary"></i>Mi Agenda</a></li>
                            <?php if ($es_admin): ?>
                                <li><a class="dropdown-item rounded-3" href="admin.php"><i class="bi bi-shield-lock me-2 text-primary"></i>Panel Admin</a></li>
                                <li><a class="dropdown-item rounded-3" href="admin_historial.php"><i class="bi bi-clock-history me-2 text-primary"></i>Historial de Citas</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item rounded-3" href="historial.php"><i class="bi bi-clock-history me-2 text-primary"></i>Historial Médico</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger rounded-3" href="../api/logout.php"><i class="bi bi-power me-2"></i>Cerrar Sesión</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <?php if ($current_page !== 'login_paciente.php' && $current_page !== 'login.php'): ?>
                        <a href="login_paciente.php" class="btn btn-outline-primary rounded-pill px-4 fw-bold">INICIAR SESIÓN</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<div class="modal fade" id="contactoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0 bg-primary text-white p-3">
                <h4 class="modal-title fw-bold"><i class="bi bi-info-circle-fill me-2"></i> Contacto y Horarios</h4>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="d-flex align-items-start mb-3">
                            <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-3">
                                <i class="bi bi-geo-alt-fill text-primary fs-4"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold text-dark mb-1">Dirección</h5>
                                <p class="text-secondary mb-0">Zaragoza, Calimaya,<br>Estado de México</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-start mb-3">
                            <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-3">
                                <i class="bi bi-telephone-fill text-primary fs-4"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold text-dark mb-1">Teléfono</h5>
                                <p class="text-secondary mb-0">+52 729 963 5417</p>
                                <p class="text-muted small">Atención 24/7</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-start">
                            <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-3">
                                <i class="bi bi-envelope-fill text-primary fs-4"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold text-dark mb-1">Correo Electrónico</h5>
                                <p class="text-secondary mb-0">contacto@agendamedica.mx</p>
                                <p class="text-muted small">Respuesta en menos de 24h</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-primary text-white p-4 rounded-4 h-100 d-flex flex-column position-relative overflow-hidden">
                            <i class="bi bi-hospital position-absolute opacity-25" style="bottom: -10px; right: -10px; font-size: 8rem;"></i>
                            <h4 class="fw-bold mb-3 position-relative z-1"><i class="bi bi-clock-history me-2"></i> Horarios de Atención</h4>
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-1 border-bottom border-light border-opacity-25 position-relative z-1">
                                <span>Lunes - Viernes</span>
                                <span class="fw-bold">07:00 AM - 08:00 PM</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-1 border-bottom border-light border-opacity-25 position-relative z-1">
                                <span>Sábados</span>
                                <span class="fw-bold">08:00 AM - 02:00 PM</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center position-relative z-1">
                                <span>Domingos</span>
                                <span class="fw-bold text-warning"><i class="bi bi-exclamation-triangle-fill me-1"></i>Solo Urgencias</span>
                            </div>
                            <div class="mt-4 text-center position-relative z-1">
                                <p class="small opacity-75 mb-0">* Los días festivos pueden tener horarios especiales.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-3">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 py-1 fw-bold" data-bs-dismiss="modal">Cerrar</button>
                <a href="<?php echo $ruta_agendar; ?>" class="btn btn-primary rounded-pill px-4 py-1 fw-bold shadow-sm">Agendar Cita</a>
            </div>
        </div>
    </div>
</div>

<main>