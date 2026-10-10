<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;

/**
 * CONEXIÓN A LA BASE DE DATOS (PATRÓN SINGLETON)
 *
 * Crea una sola conexión PDO por petición y la comparte con todo el código.
 * Los datos de conexión se leen del .env: DB_HOST, DB_NAME, DB_USER y DB_PASS.
 */
final class Database
{
    /**
     * Conexión compartida. Vale null hasta la primera llamada a getInstance().
     */
    private static ?PDO $conexion = null;

    /**
     * Constructor privado: la clase no se instancia, se usa getInstance().
     */
    private function __construct()
    {
    }

    /**
     * Devuelve la conexión PDO, creándola la primera vez que se pide.
     *
     * @throws RuntimeException Si no se puede conectar. El detalle técnico se
     *                          registra en el log y nunca se muestra al usuario.
     */
    public static function getInstance(): PDO
    {
        if (self::$conexion === null) {
            self::$conexion = self::conectar();
        }

        return self::$conexion;
    }

    /**
     * Abre la conexión con las opciones de seguridad del proyecto y alinea la
     * zona horaria de MySQL con la de PHP.
     *
     * @throws RuntimeException Si la conexión falla.
     */
    private static function conectar(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            Env::get('DB_HOST', 'localhost'),
            Env::get('DB_NAME', 'agendamedica_db')
        );

        try {
            $pdo = new PDO($dsn, Env::get('DB_USER', 'root'), Env::get('DB_PASS', ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Consultas preparadas reales en el servidor, no emuladas por PHP.
                PDO::ATTR_EMULATE_PREPARES   => false,
                // Sin conexiones persistentes: podrían arrastrar transacciones o
                // variables de sesión SQL de una petición a otra.
                PDO::ATTR_PERSISTENT         => false,
            ]);
        } catch (PDOException $e) {
            error_log('Error de conexión a la base de datos: ' . $e->getMessage());
            throw new RuntimeException('No fue posible conectar con la base de datos.', 0, $e);
        }

        // NOW() y CURDATE() deben coincidir con date() de PHP aunque el servidor
        // de base de datos esté en otra zona horaria (por ejemplo, UTC).
        $desfase = (new DateTimeImmutable())->format('P');
        $pdo->exec("SET time_zone = '{$desfase}'");

        return $pdo;
    }
}
