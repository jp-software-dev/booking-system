<?php

/**
 * ENDPOINT: REINICIO DIARIO DE LA DEMO
 *
 * Vacía la base y la vuelve a llenar con los datos de demostración. Lo llama
 * la tarea programada de Vercel (sección "crons" de vercel.json) una vez al día,
 * para que la demo pública siempre se vea limpia.
 *
 * Método:    GET (así llama Vercel a las tareas programadas)
 * Acceso:    solo en modo demo y con la cabecera "Authorization: Bearer <CRON_SECRET>",
 *            que Vercel envía automáticamente cuando la variable CRON_SECRET existe.
 * Respuesta: { status, message, resumen }
 */

require_once __DIR__ . '/../config/config.php';

use App\Core\Database;
use App\Core\Env;
use App\Demo\ModoDemo;
use App\Demo\SembradorDemo;
use App\Http\Api;

Api::iniciar('GET');

// Fuera del modo demo el endpoint no existe: nunca debe poder vaciar una base real.
if (!ModoDemo::activo()) {
    Api::error('No encontrado', 404);
}

$secreto = (string) Env::get('CRON_SECRET', '');
$autorizacion = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');

if ($secreto === '' || !hash_equals('Bearer ' . $secreto, $autorizacion)) {
    Api::error('No autorizado', 401);
}

$resumen = (new SembradorDemo(Database::getInstance()))->sembrar();
error_log('Demo reiniciada: ' . json_encode($resumen));

Api::exito('Datos de demostración reiniciados', ['resumen' => $resumen]);
