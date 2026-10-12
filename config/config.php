<?php

declare(strict_types=1);

/**
 * ARRANQUE DE LA APLICACIÓN
 *
 * Es el primer archivo que incluye cada página de /public y cada endpoint de
 * /api. En este orden:
 *   1. Carga el autoload de Composer (clases App\ y dependencias).
 *   2. Lee las variables del archivo .env.
 *   3. Define las constantes globales y la zona horaria.
 *   4. Configura el manejo de errores.
 *   5. Arranca la sesión con la cookie protegida (HttpOnly, SameSite).
 *
 * Como la sesión se arranca aquí, ningún otro archivo debe llamar a session_start().
 */

use App\Core\Env;
use App\Http\Sesion;

// === 1. AUTOLOAD DE COMPOSER ===
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    exit('Faltan las dependencias del proyecto. Ejecuta "composer install" en la raíz.');
}
require_once $autoload;

// === 2. VARIABLES DE ENTORNO ===
Env::cargar(__DIR__ . '/../.env');

// === 3. CONSTANTES DE LA APLICACIÓN ===
define('APP_NAME', 'MediAgenda');
define('APP_ENV', Env::get('APP_ENV', 'local'));
define('ES_PRODUCCION', APP_ENV === 'production');

// URL pública de la raíz del proyecto, con "/" final. Se usa en los enlaces de los correos.
define('BASE_URL', rtrim((string) Env::get('URL_BASE', 'http://localhost/app_gestor_citas'), '/') . '/');

// Máximo de citas activas (pendientes, confirmadas o en curso) que un paciente puede tener a la vez.
define('MAX_CITAS_ACTIVAS', 5);

// Datos de contacto de la clínica (página de contacto). Los valores reales se
// definen en el .env, que no se sube al repositorio; aquí solo hay textos de ejemplo.
define('CONTACTO_TELEFONO', (string) Env::get('CONTACTO_TELEFONO', 'Tu número'));
define('CONTACTO_EMAIL', (string) Env::get('CONTACTO_EMAIL', 'correo@tudominio.com'));
define('CONTACTO_UBICACION', (string) Env::get('CONTACTO_UBICACION', 'Tu ciudad'));

date_default_timezone_set('America/Mexico_City');

// === 4. MANEJO DE ERRORES ===
// En producción los errores solo van al log; en local también se muestran.
error_reporting(E_ALL);
ini_set('display_errors', ES_PRODUCCION ? '0' : '1');
ini_set('log_errors', '1');

// Cualquier excepción no atrapada se registra y el usuario recibe un mensaje genérico,
// en JSON si la petición vino a /api o en texto si vino a una página.
set_exception_handler(static function (Throwable $e): void {
    error_log(sprintf('Excepción no controlada: %s en %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));

    if (!headers_sent()) {
        http_response_code(500);
    }

    $esApi = str_contains(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/api/');
    if ($esApi) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor']);
        return;
    }

    echo 'Ocurrió un error inesperado. Intenta de nuevo más tarde.';
});

// === 5. SESIÓN SEGURA ===
// La cookie solo viaja por HTTPS en producción o si la petición ya llegó por HTTPS.
// Desde la línea de comandos (scripts como database/sembrar_demo.php) no hay sesión.
if (PHP_SAPI !== 'cli') {
    $esHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    Sesion::iniciar(ES_PRODUCCION || $esHttps);
}
