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
 * Funciona con MySQL/MariaDB (XAMPP) y con PostgreSQL (Supabase), según la
 * variable DB_DRIVER del .env:
 *
 *   DB_DRIVER  mysql (por defecto) o pgsql
 *   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
 *   DB_SSLMODE solo PostgreSQL: disable, prefer (por defecto) o require
 *
 * Las consultas del proyecto usan SQL compatible con ambos motores; lo que
 * cambia entre ellos (obtener el id insertado, detectar llaves duplicadas) se
 * resuelve con los métodos de esta clase.
 */
final class Database
{
    public const MYSQL = 'mysql';
    public const PGSQL = 'pgsql';

    /** SQLSTATE estándar de violación de llave única (PostgreSQL). */
    private const SQLSTATE_LLAVE_DUPLICADA = '23505';

    /** Código de error de MySQL para llave única duplicada. */
    private const MYSQL_LLAVE_DUPLICADA = 1062;

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
     * Motor de base de datos configurado: Database::MYSQL o Database::PGSQL.
     */
    public static function driver(): string
    {
        return Env::get('DB_DRIVER', self::MYSQL) === self::PGSQL ? self::PGSQL : self::MYSQL;
    }

    /**
     * Ejecuta un INSERT y devuelve el id generado.
     *
     * En PostgreSQL se usa "RETURNING": lastInsertId() depende de la sesión de
     * base de datos, y con el pooler de Supabase en modo transacción cada
     * sentencia puede ir a una conexión distinta.
     *
     * @param PDO         $db        Conexión.
     * @param string      $sql       Sentencia INSERT con parámetros posicionales.
     * @param list<mixed> $params    Valores de los parámetros.
     * @param string      $columnaId Columna de la llave primaria (ej. "id_cita").
     */
    public static function insertar(PDO $db, string $sql, array $params, string $columnaId): int
    {
        if (self::driver() === self::PGSQL) {
            $stmt = $db->prepare($sql . ' RETURNING ' . $columnaId);
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        }

        $db->prepare($sql)->execute($params);

        return (int) $db->lastInsertId();
    }

    /**
     * Indica si la excepción se debe a una llave única duplicada (por ejemplo,
     * un correo ya registrado o un horario ya ocupado), en cualquiera de los dos motores.
     *
     * @param PDOException $e Excepción de la base de datos.
     */
    public static function esLlaveDuplicada(PDOException $e): bool
    {
        $info = $e->errorInfo ?? [];

        return ($info[0] ?? null) === self::SQLSTATE_LLAVE_DUPLICADA
            || ($info[1] ?? null) === self::MYSQL_LLAVE_DUPLICADA;
    }

    /**
     * Abre la conexión con las opciones de seguridad del proyecto.
     *
     * @throws RuntimeException Si la conexión falla.
     */
    private static function conectar(): PDO
    {
        $esPostgres = self::driver() === self::PGSQL;

        $dsn = $esPostgres
            ? sprintf(
                'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
                Env::get('DB_HOST', 'localhost'),
                Env::get('DB_PORT', '5432'),
                Env::get('DB_NAME', 'postgres'),
                Env::get('DB_SSLMODE', 'prefer')
            )
            : sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                Env::get('DB_HOST', 'localhost'),
                Env::get('DB_PORT', '3306'),
                Env::get('DB_NAME', 'agendamedica_db')
            );

        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // MySQL: consultas preparadas reales en el servidor.
            // PostgreSQL: preparación emulada por PDO, porque el pooler de Supabase en
            // modo transacción no admite sentencias preparadas del lado del servidor.
            // En ambos casos los valores viajan escapados por PDO, nunca concatenados.
            PDO::ATTR_EMULATE_PREPARES   => $esPostgres,
            // Sin conexiones persistentes: podrían arrastrar transacciones o
            // variables de sesión SQL de una petición a otra.
            PDO::ATTR_PERSISTENT         => false,
        ];

        try {
            $pdo = new PDO($dsn, Env::get('DB_USER', 'root'), Env::get('DB_PASS', ''), $opciones);
        } catch (PDOException $e) {
            error_log('Error de conexión a la base de datos: ' . $e->getMessage());
            throw new RuntimeException('No fue posible conectar con la base de datos.', 0, $e);
        }

        // Zona horaria de la sesión SQL igual a la de PHP. Las consultas del proyecto
        // reciben las fechas desde PHP, así que esto solo afecta a valores por defecto
        // como CURRENT_TIMESTAMP.
        if ($esPostgres) {
            $pdo->exec('SET TIME ZONE ' . $pdo->quote(date_default_timezone_get()));
        } else {
            $desfase = (new DateTimeImmutable())->format('P');
            $pdo->exec("SET time_zone = '{$desfase}'");
        }

        return $pdo;
    }
}
