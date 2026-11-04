<?php

/**
 * SCRIPT: ENVIAR LOS RECORDATORIOS DE TOMAS (LÍNEA DE COMANDOS)
 *
 * Equivale a api/enviar_recordatorios.php para instalaciones sin tarea
 * programada por HTTP (XAMPP). Prográmalo cada minuto, por ejemplo con el
 * Programador de tareas de Windows:
 *
 *   php database/enviar_recordatorios.php
 *
 * En Windows, OpenSSL necesita su archivo de configuración para cifrar los
 * avisos: define la variable de entorno OPENSSL_CONF (en XAMPP:
 * C:\xampp\php\extras\ssl\openssl.cnf).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/config.php';

use App\Services\NotificadorPush;

$resumen = (new NotificadorPush())->enviarPendientes();

printf(
    "[%s] Tomas: %d · avisos: %d · enviados: %d · fallidos: %d · navegadores vencidos: %d\n",
    date('Y-m-d H:i:s'),
    $resumen['tomas'],
    $resumen['avisos'],
    $resumen['enviados'],
    $resumen['fallidos'],
    $resumen['vencidas']
);
