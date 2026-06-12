<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$server_path = null;
if (isset($_SERVER) && (isset($_SERVER['PHP_SELF']) || isset($_SERVER['SCRIPT_NAME']))) {
    $server_path = $_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME'];
}
$current_page = basename($server_path ?: 'index.php');

if (isset($_SESSION['user_id'])) {
    $ruta_agendar = 'agenda.php';
} else {
    $ruta_agendar = 'login.php';
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
<body class="d-flex flex-column min-height-100 bg-light">

<nav class="navbar navbar-expand-lg navbar-white bg-white sticky-top py-3 shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <div class="bg-primary bg-opacity-10 rounded-3 p-2 me-2 text-primary d-inline-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                <i class="bi bi-hospital-fill fs-4"></i>
            </div>
            <span class="fw-bolder text-dark-blue fs-4 tracking-tight">MediAgenda</span>
        </a>
        
        <button class="navbar-toggler border-0 p-2 shadow-none d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasMenu" aria-controls="offcanvasMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse d-none d-lg-flex" id="navbarNavDesktop">
            <ul class="navbar-nav mx-auto gap-2">
                <li class="nav-item">
                    <a class="nav-link rounded-pill px-3 fw-medium <?php echo $current_page === 'index.php' ? 'active text-primary bg-primary bg-opacity-10' : 'text-secondary'; ?>" href="index.php">Inicio</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill px-3 fw-medium <?php echo $current_page === 'contacto.php' ? 'active text-primary bg-primary bg-opacity-10' : 'text-secondary'; ?>" href="contacto.php">Contacto</a>
                </li>
                <?php if (isset($_SESSION['user_id']) && !$es_admin): ?>
                    <li class="nav-item">
                        <a class="nav-link rounded-pill px-3 fw-medium <?php echo $current_page === 'agenda.php' ? 'active text-primary bg-primary bg-opacity-10' : 'text-secondary'; ?>" href="agenda.php">Reservar Cita</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link rounded-pill px-3 fw-medium <?php echo $current_page === 'historial.php' ? 'active text-primary bg-primary bg-opacity-10' : 'text-secondary'; ?>" href="historial.php">Historial Médico</a>
                    </li>
                <?php endif; ?>
            </ul>
            
            <div class="navbar-nav align-items-center">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="dropdown">
                        <button class="btn btn-light rounded-pill px-4 dropdown-toggle fw-bold d-flex align-items-center gap-2 border shadow-sm" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle text-primary fs-5"></i>
                            <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuario'); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg mt-2 p-2 rounded-4" style="min-width: 220px;">
                            <?php if ($es_admin): ?>
                                <li><a class="dropdown-item rounded-3 py-2 fw-medium" href="admin.php"><i class="bi bi-speedometer2 me-2 text-primary"></i>Panel de Citas</a></li>
                                <li><a class="dropdown-item rounded-3 py-2 fw-medium" href="admin_historial.php"><i class="bi bi-clock-history me-2 text-primary"></i>Historial de Citas</a></li>
                                <li><a class="dropdown-item rounded-3 py-2 fw-medium" href="admin_consultorios.php"><i class="bi bi-door-open me-2 text-primary"></i>Consultorios</a></li>
                                <li><a class="dropdown-item rounded-3 py-2 fw-medium" href="admin_equipo.php"><i class="bi bi-people me-2 text-primary"></i>Equipo Médico</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item rounded-3 py-2 fw-medium" href="agenda.php"><i class="bi bi-calendar-plus me-2 text-primary"></i>Reservar Cita</a></li>
                                <li><a class="dropdown-item rounded-3 py-2 fw-medium" href="historial.php"><i class="bi bi-clock-history me-2 text-primary"></i>Historial Médico</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger rounded-3 py-2 fw-bold" href="../api/logout.php"><i class="bi bi-power me-2"></i>Cerrar Sesión</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <?php if ($current_page !== 'login.php'): ?>
                        <a href="login.php" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm">INICIAR SESIÓN</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<div class="offcanvas offcanvas-end d-lg-none" tabindex="-1" id="offcanvasMenu" aria-labelledby="offcanvasMenuLabel" style="width: 300px;">
    <div class="offcanvas-header border-bottom bg-light p-4 d-flex flex-column align-items-start">
        <div class="d-flex justify-content-between w-100 mb-4">
            <h5 class="offcanvas-title fw-bolder text-primary d-flex align-items-center" id="offcanvasMenuLabel">
                <i class="bi bi-hospital-fill me-2 fs-3"></i> MediAgenda
            </h5>
            <button type="button" class="btn-close shadow-none" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        
        <?php if (isset($_SESSION['user_id'])): ?>
        <div class="d-flex align-items-center w-100 mt-2">
            <div class="bg-primary text-white rounded-circle d-flex justify-content-center align-items-center me-3 shadow-sm" style="width: 55px; height: 55px;">
                <i class="bi bi-person-fill fs-2"></i>
            </div>
            <div>
                <h5 class="fw-bold text-dark mb-0 text-truncate" style="max-width: 170px;"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuario'); ?></h5>
                <small class="text-secondary fw-medium"><?php echo $es_admin ? 'Administrador' : 'Paciente'; ?></small>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="offcanvas-body p-3">
        <ul class="nav flex-column gap-2">
            <li class="nav-item">
                <a class="nav-link text-dark fw-bold d-flex align-items-center p-3 rounded-3 <?php echo $current_page === 'index.php' ? 'bg-primary bg-opacity-10 text-primary' : 'bg-white'; ?>" href="index.php">
                    <i class="bi bi-house-door-fill fs-5 me-3 <?php echo $current_page === 'index.php' ? 'text-primary' : 'text-secondary'; ?>"></i> Inicio
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-dark fw-bold d-flex align-items-center p-3 rounded-3 <?php echo $current_page === 'contacto.php' ? 'bg-primary bg-opacity-10 text-primary' : 'bg-white'; ?>" href="contacto.php">
                    <i class="bi bi-telephone-fill fs-5 me-3 <?php echo $current_page === 'contacto.php' ? 'text-primary' : 'text-secondary'; ?>"></i> Contacto
                </a>
            </li>
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="mt-4 mb-2 text-uppercase text-primary fw-bold small px-2" style="font-size: 0.75rem; letter-spacing: 1px;">Mis Opciones</div>
                
                <?php if ($es_admin): ?>
                    <li class="nav-item"><a class="nav-link text-dark fw-bold d-flex align-items-center p-3 rounded-3 <?php echo $current_page === 'admin.php' ? 'bg-primary bg-opacity-10 text-primary' : 'bg-white'; ?>" href="admin.php"><i class="bi bi-speedometer2 fs-5 me-3 text-secondary"></i> Panel de Citas</a></li>
                    <li class="nav-item"><a class="nav-link text-dark fw-bold d-flex align-items-center p-3 rounded-3 <?php echo $current_page === 'admin_historial.php' ? 'bg-primary bg-opacity-10 text-primary' : 'bg-white'; ?>" href="admin_historial.php"><i class="bi bi-clock-history fs-5 me-3 text-secondary"></i> Historial de Citas</a></li>
                    <li class="nav-item"><a class="nav-link text-dark fw-bold d-flex align-items-center p-3 rounded-3 <?php echo $current_page === 'admin_consultorios.php' ? 'bg-primary bg-opacity-10 text-primary' : 'bg-white'; ?>" href="admin_consultorios.php"><i class="bi bi-door-open-fill fs-5 me-3 text-secondary"></i> Consultorios</a></li>
                    <li class="nav-item"><a class="nav-link text-dark fw-bold d-flex align-items-center p-3 rounded-3 <?php echo $current_page === 'admin_equipo.php' ? 'bg-primary bg-opacity-10 text-primary' : 'bg-white'; ?>" href="admin_equipo.php"><i class="bi bi-people-fill fs-5 me-3 text-secondary"></i> Equipo Médico</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link text-dark fw-bold d-flex align-items-center p-3 rounded-3 <?php echo $current_page === 'agenda.php' ? 'bg-primary bg-opacity-10 text-primary' : 'bg-white'; ?>" href="agenda.php"><i class="bi bi-calendar-plus-fill fs-5 me-3 text-secondary"></i> Reservar Cita</a></li>
                    <li class="nav-item"><a class="nav-link text-dark fw-bold d-flex align-items-center p-3 rounded-3 <?php echo $current_page === 'historial.php' ? 'bg-primary bg-opacity-10 text-primary' : 'bg-white'; ?>" href="historial.php"><i class="bi bi-journal-medical fs-5 me-3 text-secondary"></i> Historial Médico</a></li>
                <?php endif; ?>
            <?php endif; ?>
        </ul>
        
        <?php if (!isset($_SESSION['user_id'])): ?>
            <a href="login.php" class="btn btn-primary w-100 rounded-pill py-3 mt-4 fw-bold shadow-sm">INICIAR SESIÓN</a>
        <?php endif; ?>
    </div>
    
    <?php if (isset($_SESSION['user_id'])): ?>
    <div class="offcanvas-footer p-4 border-top bg-light">
         <a href="../api/logout.php" class="btn btn-danger w-100 rounded-pill py-3 fw-bold shadow d-flex align-items-center justify-content-center">
             <i class="bi bi-power me-2 fs-5"></i> Cerrar Sesión
         </a>
    </div>
    <?php endif; ?>
</div>

<main>