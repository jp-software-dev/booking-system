<?php
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
                    <a class="nav-link" href="contacto.php">Contacto</a>
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
                        <button class="btn btn-outline-primary dropdown-toggle rounded-pill px-4 fw-bold"
                                type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-2"></i>
                            Hola, <?php echo htmlspecialchars(ucwords(strtolower($_SESSION['user_name']))); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2 p-2">
                            <li><a class="dropdown-item rounded-3" href="agenda.php">
                                <i class="bi bi-calendar-week me-2 text-primary"></i>Mi Agenda
                            </a></li>
                            <?php if ($es_admin): ?>
                                <li><a class="dropdown-item rounded-3" href="admin.php">
                                    <i class="bi bi-shield-lock me-2 text-primary"></i>Panel Admin
                                </a></li>
                                <li><a class="dropdown-item rounded-3" href="admin_historial.php">
                                    <i class="bi bi-clock-history me-2 text-primary"></i>Historial de Citas
                                </a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item rounded-3" href="historial.php">
                                    <i class="bi bi-clock-history me-2 text-primary"></i>Historial Médico
                                </a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger rounded-3" href="../api/logout.php">
                                <i class="bi bi-power me-2"></i>Cerrar Sesión
                            </a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <?php if ($current_page !== 'login_paciente.php' && $current_page !== 'login.php'): ?>
                        <a href="login_paciente.php" class="btn btn-outline-primary rounded-pill px-4 fw-bold">
                            INICIAR SESIÓN
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<main>