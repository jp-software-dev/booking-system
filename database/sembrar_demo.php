<?php

/**
 * SCRIPT: LLENAR LA BASE CON DATOS DE DEMOSTRACIÓN
 *
 * Vacía la base de datos configurada en el .env y la llena con datos ficticios
 * (ver App\Demo\SembradorDemo). Funciona igual con MySQL y con PostgreSQL.
 *
 * Uso (desde la raíz del proyecto):
 *   php database/sembrar_demo.php --confirmar
 *
 * Protecciones para no borrar una base real por error:
 *   - Solo corre desde la línea de comandos.
 *   - Exige DEMO_MODE=true en el .env.
 *   - Exige el parámetro --confirmar.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/config.php';

use App\Core\Database;
use App\Core\Env;
use App\Demo\ModoDemo;
use App\Demo\SembradorDemo;

if (!ModoDemo::activo()) {
    fwrite(STDERR, "Cancelado: el .env debe tener DEMO_MODE=true. (Protección para no borrar una base real.)\n");
    exit(1);
}

if (!in_array('--confirmar', $argv, true)) {
    fwrite(STDERR, sprintf(
        "Esto BORRA todos los datos de la base '%s' (%s) y la llena con datos ficticios.\n"
        . "Para continuar ejecuta: php database/sembrar_demo.php --confirmar\n",
        Env::get('DB_NAME', ''),
        Database::driver()
    ));
    exit(1);
}

$inicio = microtime(true);
$resumen = (new SembradorDemo(Database::getInstance()))->sembrar();

echo "Datos de demostración creados en " . round(microtime(true) - $inicio, 1) . " s:\n";
foreach ($resumen as $tipo => $cantidad) {
    echo "  - {$tipo}: {$cantidad}\n";
}
echo "\nCuentas de prueba:\n";
echo "  Administrador: " . ModoDemo::ADMIN_USUARIO . " / " . ModoDemo::PASSWORD . "\n";
echo "  Paciente:      " . ModoDemo::PACIENTE_EMAIL . " / " . ModoDemo::PASSWORD . "\n";
