<?php
/**
 * LAYOUT: ENCABEZADO Y NAVEGACIÓN
 *
 * Abre el documento HTML, carga los estilos y dibuja la navegación según el rol:
 *   - Escritorio: barra superior con la navegación en píldoras.
 *   - Celular: menú lateral (público, doctor y administrador) y, para el
 *     paciente, una barra inferior al alcance del pulgar.
 *
 * Cada página lo incluye después de config/config.php y de haber preparado sus datos.
 *
 * Variables opcionales que la página puede definir antes de incluirlo:
 * @var list<string> $estilosExtra Hojas de estilo adicionales dentro de public/assets
 *                                 (ej. ['css/calendar.css']).
 * @var string       $tituloPagina Título de la pestaña (por defecto, el nombre de la app).
 */

use App\Demo\ModoDemo;
use App\Helpers\CsrfHelper;
use App\Http\Sesion;

$paginaActual = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
$csrfToken = CsrfHelper::generateToken();
$autenticado = Sesion::estaAutenticado();
$rol = Sesion::rol();

// === OPCIONES DE MENÚ POR ROL ===
// 'activa' lista las páginas que marcan la opción como actual (además de su url).
$menuPublico = [
    ['url' => 'index.php',    'icono' => 'bi-house-door', 'texto' => 'Inicio'],
    ['url' => 'contacto.php', 'icono' => 'bi-telephone',  'texto' => 'Contacto'],
];

$menuPorRol = [
    Sesion::ROL_PACIENTE => [
        ['url' => 'tratamientos.php', 'icono' => 'bi-capsule',       'texto' => 'Mi semana'],
        ['url' => 'agenda.php',       'icono' => 'bi-calendar-plus', 'texto' => 'Agendar'],
        ['url' => 'historial.php',    'icono' => 'bi-clock-history', 'texto' => 'Mis citas'],
        ['url' => 'cuenta.php',       'icono' => 'bi-person',        'texto' => 'Mi cuenta'],
    ],
    Sesion::ROL_DOCTOR => [
        ['url' => 'doctor.php',         'icono' => 'bi-calendar-week',    'texto' => 'Mis citas'],
        ['url' => 'doctor_recetas.php', 'icono' => 'bi-file-earmark-medical', 'texto' => 'Recetas'],
    ],
    Sesion::ROL_ADMIN => [
        ['url' => 'admin.php',              'icono' => 'bi-calendar-check', 'texto' => 'Citas'],
        ['url' => 'admin_dashboard.php',    'icono' => 'bi-bar-chart',      'texto' => 'Métricas'],
        ['url' => 'agenda.php',             'icono' => 'bi-calendar-plus',  'texto' => 'Agendar'],
        ['url' => 'admin_historial.php',    'icono' => 'bi-clock-history',  'texto' => 'Historial'],
        ['url' => 'admin_pacientes.php',    'icono' => 'bi-people',         'texto' => 'Pacientes'],
        ['url' => 'admin_equipo.php',       'icono' => 'bi-person-badge',   'texto' => 'Equipo'],
        ['url' => 'admin_consultorios.php', 'icono' => 'bi-door-open',      'texto' => 'Consultorios'],
        ['url' => 'admin_bitacora.php',     'icono' => 'bi-journal-text',   'texto' => 'Bitácora'],
    ],
];

$menu = $autenticado ? ($menuPorRol[$rol] ?? []) : $menuPublico;
$conTabbar = $rol === Sesion::ROL_PACIENTE;

/** Indica si la opción de menú corresponde a la página actual. */
$esActiva = static fn (array $opcion): bool => $opcion['url'] === $paginaActual;

// Inicial del nombre para el avatar.
$inicial = mb_strtoupper(mb_substr(Sesion::nombre('U'), 0, 1));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <meta name="theme-color" content="#ffffff">
    <title><?= e(isset($tituloPagina) ? $tituloPagina . ' · ' . APP_NAME : APP_NAME . ' | Citas y tratamientos') ?></title>
    <!-- Favicon: ICO para navegadores antiguos, SVG nítido en cualquier tamaño y PNG para iPhone/iPad. -->
    <link rel="icon" href="<?= e(asset('images/favicon.ico')) ?>" sizes="any">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/favicon.svg')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('images/favicon-32.png')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset('images/apple-touch-icon.png')) ?>">
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito+Sans:opsz,wght@6..12,400;6..12,600;6..12,700;6..12,800&display=swap">
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
<body class="d-flex flex-column min-vh-100 bg-light<?= $conTabbar ? ' con-tabbar' : '' ?>">

<?php if (ModoDemo::activo()): ?>
    <!-- === AVISO DE MODO DEMOSTRACIÓN === -->
    <div class="ma-demo">
        <i class="bi bi-info-circle-fill me-1" aria-hidden="true"></i>
        <strong>Demo:</strong> todos los datos son ficticios y se reinician cada día.
    </div>
<?php endif; ?>

<!-- === ENCABEZADO === -->
<header class="ma-header">
    <div class="container ma-header-fila">
        <a class="ma-marca" href="<?= $autenticado ? e(Sesion::paginaInicio()) : 'index.php' ?>">
            <img src="<?= e(asset('images/favicon.svg')) ?>" alt="" width="34" height="34">
            <span><?= e(APP_NAME) ?></span>
        </a>

        <nav class="ma-nav d-none d-xl-flex" aria-label="Navegación principal">
            <?php foreach ($menu as $opcion): ?>
                <a href="<?= e($opcion['url']) ?>" class="<?= $esActiva($opcion) ? 'activa' : '' ?>"<?= $esActiva($opcion) ? ' aria-current="page"' : '' ?>><?= e($opcion['texto']) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="ms-auto d-flex align-items-center gap-2">
            <?php if ($autenticado): ?>
                <div class="dropdown">
                    <button class="ma-usuario" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="ma-avatar" aria-hidden="true"><?= e($inicial) ?></span>
                        <span class="d-none d-sm-flex flex-column align-items-start text-start">
                            <span class="lh-sm"><?= e(Sesion::nombre()) ?></span>
                            <span class="ma-rol"><?= e(Sesion::nombreRol()) ?></span>
                        </span>
                        <i class="bi bi-chevron-down small text-secondary" aria-hidden="true"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end mt-2" style="min-width: 230px;">
                        <?php if ($rol === Sesion::ROL_PACIENTE): ?>
                            <li><a class="dropdown-item" href="cuenta.php"><i class="bi bi-person me-2" aria-hidden="true"></i>Mi cuenta</a></li>
                            <li><hr class="dropdown-divider my-1"></li>
                        <?php endif; ?>
                        <li>
                            <form action="../api/logout.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Cerrar sesión</button>
                            </form>
                        </li>
                    </ul>
                </div>
            <?php elseif ($paginaActual !== 'login.php'): ?>
                <a href="login.php" class="btn btn-primary">Iniciar sesión</a>
            <?php endif; ?>

            <?php if (!$conTabbar): ?>
                <button class="ma-menu-boton d-xl-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#menuMovil" aria-controls="menuMovil" aria-label="Abrir menú">
                    <i class="bi bi-list fs-4" aria-hidden="true"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>
</header>

<?php if (!$conTabbar): ?>
    <!-- === MENÚ LATERAL (CELULAR Y TABLETA) === -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="menuMovil" aria-labelledby="menuMovilTitulo" style="width: 300px;">
        <div class="offcanvas-header border-bottom">
            <span class="ma-marca" id="menuMovilTitulo">
                <img src="<?= e(asset('images/favicon.svg')) ?>" alt="" width="34" height="34"><?= e(APP_NAME) ?>
            </span>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column gap-1 p-3">
            <?php foreach ($menu as $opcion): ?>
                <a class="dropdown-item d-flex align-items-center gap-3 py-3 <?= $esActiva($opcion) ? 'bg-primary-subtle text-primary-emphasis' : '' ?>" href="<?= e($opcion['url']) ?>">
                    <i class="bi <?= e($opcion['icono']) ?> fs-5" aria-hidden="true"></i><?= e($opcion['texto']) ?>
                </a>
            <?php endforeach; ?>
            <?php if (!$autenticado): ?>
                <a href="login.php" class="btn btn-primary btn-lg mt-3">Iniciar sesión</a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <!-- === BARRA INFERIOR DEL PACIENTE (CELULAR Y TABLETA) === -->
    <nav class="ma-tabbar d-xl-none" aria-label="Navegación principal">
        <?php foreach ($menu as $opcion): ?>
            <a href="<?= e($opcion['url']) ?>" class="<?= $esActiva($opcion) ? 'activa' : '' ?>"<?= $esActiva($opcion) ? ' aria-current="page"' : '' ?>>
                <i class="bi <?= e($opcion['icono']) ?>" aria-hidden="true"></i><?= e($opcion['texto']) ?>
            </a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<main class="flex-grow-1">
