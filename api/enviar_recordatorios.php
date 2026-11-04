<?php

/**
 * ENDPOINT: ENVIAR LOS RECORDATORIOS DE TOMAS (TAREA PROGRAMADA)
 *
 * Avisa por push de las tomas que llegaron a su hora. Debe llamarse cada
 * minuto: en la demo lo hace una tarea pg_cron de Supabase (pg_net), porque
 * las tareas de Vercel en el plan gratuito solo corren una vez al día.
 * En XAMPP se usa database/enviar_recordatorios.php desde el Programador de tareas.
 *
 * Método:    GET o POST
 * Acceso:    cabecera "Authorization: Bearer <CRON_SECRET>"
 * Respuesta: { status, message, resumen }
 */

require_once __DIR__ . '/../config/config.php';

use App\Core\Env;
use App\Http\Api;
use App\Services\NotificadorPush;

Api::iniciar('GET', 'POST');

$secreto = (string) Env::get('CRON_SECRET', '');
$autorizacion = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');

if ($secreto === '' || !hash_equals('Bearer ' . $secreto, $autorizacion)) {
    Api::error('No autorizado', 401);
}

$resumen = (new NotificadorPush())->enviarPendientes();
if ($resumen['tomas'] > 0) {
    error_log('Recordatorios enviados: ' . json_encode($resumen));
}

Api::exito('Recordatorios procesados', ['resumen' => $resumen]);
