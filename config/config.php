<?php
// === CARGADOR DE .ENV ===
// XAMPP no carga .env automáticamente. Sin esto, $_ENV['SMTP_PASS'] es null
// y PHPMailer falla silenciosamente. Este bloque DEBE ir antes de todo lo demás.
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if ($key !== '') {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// === SESIÓN ===
if (session_status() === PHP_SESSION_NONE) {
    $esProduccion = ($_ENV['APP_ENV'] ?? 'local') === 'production';

    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', $esProduccion ? 1 : 0);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// === CONSTANTES DE APLICACIÓN ===
define('BASE_URL', rtrim($_ENV['URL_BASE'] ?? 'http://localhost/app_gestor_citas', '/') . '/');
define('APP_NAME', 'MediAgenda');
date_default_timezone_set('America/Mexico_City');

// === CONSTANTES DE BASE DE DATOS ===
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'agendamedica_db');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('MAX_CITAS_ACTIVAS', 5);