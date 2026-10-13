<?php
/**
 * LAYOUT: ENCABEZADO Y NAVEGACIÓN
 *
 * Abre el documento HTML, carga los estilos y dibuja la barra de navegación de
 * escritorio y el menú lateral de móvil. Cada página lo incluye después de
 * config/config.php y de haber preparado sus datos.
 *
 * Variables opcionales que la página puede definir antes de incluirlo:
 * @var list<string> $estilosExtra Hojas de estilo adicionales dentro de public/assets
 *                                 (ej. ['css/calendar.css']).
 */

use App\Helpers\CsrfHelper;
use App\Http\Sesion;

$paginaActual = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
$csrfToken = CsrfHelper::generateToken();
$autenticado = Sesion::estaAutenticado();
$esAdmin = Sesion::esAdmin();

// === OPCIONES DE MENÚ ===
// 'barra' => true indica que la opción también aparece en la barra superior de escritorio.
$menuPublico = [
    ['url' => 'index.php',    'icono' => 'bi-house-door-fill', 'texto' => 'Inicio'],
    ['url' => 'contacto.php', 'icono' => 'bi-telephone-fill',  'texto' => 'Contacto'],
];

$menuUsuario = $esAdmin
    ? [
        ['url' => 'admin.php',              'icono' => 'bi-speedometer2',    'texto' => 'Panel de Citas'],
        ['url' => 'admin_dashboard.php',    'icono' => 'bi-bar-chart-fill',  'texto' => 'Dashboard'],
        ['url' => 'agenda.php',             'icono' => 'bi-calendar-plus',   'texto' => 'Agendar Cita'],
        ['url' => 'admin_historial.php',    'icono' => 'bi-clock-history',   'texto' => 'Historial de Citas'],
        ['url' => 'admin_consultorios.php', 'icono' => 'bi-door-open',       'texto' => 'Consultorios'],
        ['url' => 'admin_equipo.php',       'icono' => 'bi-people',          'texto' => 'Equipo Médico'],
        ['url' => 'admin_pacientes.php',    'icono' => 'bi-people-fill',     'texto' => 'Pacientes'],
        ['url' => 'admin_bitacora.php',     'icono' => 'bi-journal-text',    'texto' => 'Bitácora'],
    ]
    : [
        ['url' => 'agenda.php',    'icono' => 'bi-calendar-plus', 'texto' => 'Reservar Cita',      'barra' => true],
        ['url' => 'historial.php', 'icono' => 'bi-clock-history', 'texto' => 'Historial Médico',   'barra' => true],
        ['url' => 'cuenta.php',    'icono' => 'bi-gear-fill',     'texto' => 'Administrar Cuenta'],
    ];

// Opciones visibles en la barra superior de escritorio.
$menuBarra = $menuPublico;
if ($autenticado) {
    $menuBarra = array_merge($menuBarra, array_filter($menuUsuario, static fn (array $op): bool => !empty($op['barra'])));
}

/**
 * Indica si la opción de menú corresponde a la página actual.
 */
$esActiva = static fn (array $opcion): bool => $opcion['url'] === $paginaActual;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <title><?= e(APP_NAME) ?> | Gestión Hospitalaria</title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/favicon.svg')) ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= e(asset('css/styles.css')) ?>">
    <?php foreach ($estilosExtra ?? [] as $hoja): ?>
        <link rel="stylesheet" href="<?= e(asset($hoja)) ?>">
    <?php endforeach; ?>
    <!-- Se carga en el <head> (y no en el pie) para que los scripts de cada página
         puedan usar apiClient desde el primer momento. Es un archivo pequeño. -->
    <script src="<?= e(asset('js/api_client.js')) ?>"></script>
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<?php if (\App\Demo\ModoDemo::activo()): ?>
    <!-- === AVISO DE MODO DEMOSTRACIÓN === -->
    <div class="bg-warning-subtle text-warning-emphasis text-center small py-2 px-3 border-bottom">
        <i class="bi bi-info-circle-fill me-1"></i>
        <strong>Demo:</strong> todos los datos son ficticios y se reinician cada día.
    </div>
<?php endif; ?>

<!-- === BARRA DE NAVEGACIÓN (ESCRITORIO) === -->
<nav class="navbar navbar-expand-lg bg-white sticky-top py-3 shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <div class="bg-primary bg-opacity-10 rounded-3 p-2 me-2 text-primary d-inline-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                <i class="bi bi-hospital-fill fs-4"></i>
            </div>
            <span class="fw-bolder text-dark-blue fs-4"><?= e(APP_NAME) ?></span>
        </a>

        <button class="navbar-toggler border-0 p-2 shadow-none d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasMenu" aria-controls="offcanvasMenu" aria-label="Abrir menú">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse d-none d-lg-flex">
            <ul class="navbar-nav mx-auto gap-2">
                <?php foreach ($menuBarra as $opcion): ?>
                    <li class="nav-item">
                        <a class="nav-link rounded-pill px-3 fw-medium <?= $esActiva($opcion) ? 'active text-primary bg-primary bg-opacity-10' : 'text-secondary' ?>" href="<?= e($opcion['url']) ?>"><?= e($opcion['texto']) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="navbar-nav align-items-center">
                <?php if ($autenticado): ?>
                    <div class="dropdown">
                        <button class="btn btn-light rounded-pill px-4 dropdown-toggle fw-bold d-flex align-items-center gap-2 border shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle text-primary fs-5"></i>
                            <?= e(Sesion::nombre()) ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg mt-2 p-2 rounded-4" style="min-width: 220px;">
                            <?php foreach ($menuUsuario as $opcion): ?>
                                <li><a class="dropdown-item rounded-3 py-2 fw-medium" href="<?= e($opcion['url']) ?>"><i class="bi <?= e($opcion['icono']) ?> me-2 text-primary"></i><?= e($opcion['texto']) ?></a></li>
                            <?php endforeach; ?>
                            <li><hr class="dropdown-divider my-2"></li>
                            <li>
                                <form action="../api/logout.php" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                    <button type="submit" class="dropdown-item text-danger rounded-3 py-2 fw-bold border-0 bg-transparent w-100 text-start"><i class="bi bi-power me-2"></i>Cerrar Sesión</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                <?php elseif ($paginaActual !== 'login.php'): ?>
                    <a href="login.php" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm">INICIAR SESIÓN</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- === MENÚ LATERAL (MÓVIL) === -->
<div class="offcanvas offcanvas-end d-lg-none" tabindex="-1" id="offcanvasMenu" aria-labelledby="offcanvasMenuLabel" style="width: 300px;">
    <div class="offcanvas-header border-bottom bg-light p-4 d-flex flex-column align-items-start">
        <div class="d-flex justify-content-between w-100 mb-4">
            <h5 class="offcanvas-title fw-bolder text-primary d-flex align-items-center" id="offcanvasMenuLabel">
                <i class="bi bi-hospital-fill me-2 fs-3"></i> <?= e(APP_NAME) ?>
            </h5>
            <button type="button" class="btn-close shadow-none" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>

        <?php if ($autenticado): ?>
            <div class="d-flex align-items-center w-100 mt-2">
                <div class="bg-primary text-white rounded-circle d-flex justify-content-center align-items-center me-3 shadow-sm" style="width: 55px; height: 55px;">
                    <i class="bi bi-person-fill fs-2"></i>
                </div>
                <div>
                    <h5 class="fw-bold text-dark mb-0 text-truncate" style="max-width: 170px;"><?= e(Sesion::nombre()) ?></h5>
                    <small class="text-secondary fw-medium"><?= $esAdmin ? 'Administrador' : 'Paciente' ?></small>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="offcanvas-body p-3">
        <ul class="nav flex-column gap-2">
            <?php foreach ($menuPublico as $opcion): ?>
                <li class="nav-item">
                    <a class="nav-link text-dark fw-bold d-flex align-items-center p-3 rounded-3 <?= $esActiva($opcion) ? 'bg-primary bg-opacity-10 text-primary' : 'bg-white' ?>" href="<?= e($opcion['url']) ?>">
                        <i class="bi <?= e($opcion['icono']) ?> fs-5 me-3 <?= $esActiva($opcion) ? 'text-primary' : 'text-secondary' ?>"></i> <?= e($opcion['texto']) ?>
                    </a>
                </li>
            <?php endforeach; ?>

            <?php if ($autenticado): ?>
                <li class="mt-4 mb-2 text-uppercase text-primary fw-bold small px-2" style="font-size: 0.75rem; letter-spacing: 1px;">Mis Opciones</li>
                <?php foreach ($menuUsuario as $opcion): ?>
                    <li class="nav-item">
                        <a class="nav-link text-dark fw-bold d-flex align-items-center p-3 rounded-3 <?= $esActiva($opcion) ? 'bg-primary bg-opacity-10 text-primary' : 'bg-white' ?>" href="<?= e($opcion['url']) ?>">
                            <i class="bi <?= e($opcion['icono']) ?> fs-5 me-3 text-secondary"></i> <?= e($opcion['texto']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>

        <?php if (!$autenticado): ?>
            <a href="login.php" class="btn btn-primary w-100 rounded-pill py-3 mt-4 fw-bold shadow-sm">INICIAR SESIÓN</a>
        <?php endif; ?>
    </div>

    <?php if ($autenticado): ?>
        <div class="p-4 border-top bg-light">
            <form action="../api/logout.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <button type="submit" class="btn btn-danger w-100 rounded-pill py-3 fw-bold shadow d-flex align-items-center justify-content-center">
                    <i class="bi bi-power me-2 fs-5"></i> Cerrar Sesión
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

<main>
