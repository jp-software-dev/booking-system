<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Database;
use PDO;
use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;

/**
 * SESIONES GUARDADAS EN LA BASE DE DATOS
 *
 * Reemplaza el almacenamiento en archivos de PHP cuando SESSION_DRIVER=database.
 * Es necesario en plataformas "serverless" como Vercel: cada petición puede
 * atenderla una instancia distinta, y los archivos de sesión de una instancia
 * no existen en las demás.
 *
 * Tabla `sesiones`: id (llave primaria), datos (texto serializado por PHP) y
 * actualizado (marca de tiempo Unix del último uso).
 *
 * Implementa validateId() para que session.use_strict_mode siga rechazando
 * identificadores de sesión que no generó el servidor.
 */
final class ManejadorSesionBD implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    /** Segundos de inactividad tras los que una sesión se considera vencida. */
    private int $vigencia;

    public function __construct()
    {
        $this->vigencia = (int) ini_get('session.gc_maxlifetime') ?: 1440;
    }

    /**
     * No requiere preparación: la conexión se abre al primer uso.
     */
    public function open(string $ruta, string $nombre): bool
    {
        return true;
    }

    /**
     * No hay recursos que liberar.
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * Devuelve los datos de una sesión vigente, o una cadena vacía si no existe.
     *
     * @param string $id Identificador de la sesión.
     */
    public function read(string $id): string
    {
        $stmt = $this->db()->prepare('SELECT datos FROM sesiones WHERE id = ? AND actualizado > ?');
        $stmt->execute([$id, time() - $this->vigencia]);
        $datos = $stmt->fetchColumn();

        return $datos === false ? '' : (string) $datos;
    }

    /**
     * Guarda (o reemplaza) los datos de la sesión.
     *
     * @param string $id    Identificador de la sesión.
     * @param string $datos Datos serializados por PHP.
     */
    public function write(string $id, string $datos): bool
    {
        // "Insertar o actualizar" se escribe distinto en cada motor.
        $sql = Database::driver() === Database::PGSQL
            ? 'INSERT INTO sesiones (id, datos, actualizado) VALUES (?, ?, ?)
               ON CONFLICT (id) DO UPDATE SET datos = EXCLUDED.datos, actualizado = EXCLUDED.actualizado'
            : 'INSERT INTO sesiones (id, datos, actualizado) VALUES (?, ?, ?)
               ON DUPLICATE KEY UPDATE datos = VALUES(datos), actualizado = VALUES(actualizado)';

        return $this->db()->prepare($sql)->execute([$id, $datos, time()]);
    }

    /**
     * Elimina la sesión (cierre de sesión o regeneración del identificador).
     *
     * @param string $id Identificador de la sesión.
     */
    public function destroy(string $id): bool
    {
        return $this->db()->prepare('DELETE FROM sesiones WHERE id = ?')->execute([$id]);
    }

    /**
     * Borra las sesiones vencidas. PHP lo invoca de forma aleatoria según
     * session.gc_probability y session.gc_divisor.
     *
     * @param int $vigenciaMaxima Segundos de inactividad permitidos.
     * @return int Cantidad de sesiones eliminadas.
     */
    public function gc(int $vigenciaMaxima): int
    {
        $stmt = $this->db()->prepare('DELETE FROM sesiones WHERE actualizado < ?');
        $stmt->execute([time() - $vigenciaMaxima]);

        return $stmt->rowCount();
    }

    /**
     * Indica si el identificador corresponde a una sesión vigente. Con
     * use_strict_mode, PHP genera un identificador nuevo cuando devuelve false.
     *
     * @param string $id Identificador recibido en la cookie.
     */
    public function validateId(string $id): bool
    {
        $stmt = $this->db()->prepare('SELECT COUNT(*) FROM sesiones WHERE id = ? AND actualizado > ?');
        $stmt->execute([$id, time() - $this->vigencia]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Renueva la marca de tiempo cuando la sesión se usó pero sus datos no cambiaron.
     *
     * @param string $id    Identificador de la sesión.
     * @param string $datos Datos serializados (sin cambios).
     */
    public function updateTimestamp(string $id, string $datos): bool
    {
        return $this->db()->prepare('UPDATE sesiones SET actualizado = ? WHERE id = ?')->execute([time(), $id]);
    }

    /**
     * Conexión compartida a la base de datos.
     */
    private function db(): PDO
    {
        return Database::getInstance();
    }
}
