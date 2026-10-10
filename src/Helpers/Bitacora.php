<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Database;
use App\Http\Sesion;
use PDO;
use Throwable;

/**
 * BITÁCORA DE AUDITORÍA
 *
 * Registra acciones sensibles (inicios de sesión, bajas, cambios de estado,
 * reasignaciones) en la tabla `bitacora`.
 *
 * Nunca lanza excepciones hacia quien la llama: si el registro falla se anota
 * en el log de errores, pero la operación principal continúa.
 *
 * Nota: bitacora.id_usuario guarda id_usuario cuando el rol es 'admin' e
 * id_paciente cuando es 'paciente'. Por eso no tiene llave foránea.
 */
final class Bitacora
{
    /**
     * Registra una acción a nombre del usuario de la sesión actual.
     *
     * Si no hay sesión activa no se registra nada.
     *
     * @param string      $accion    Clave de la acción (ej. 'eliminar_cita').
     * @param string      $entidad   Tipo de registro afectado (ej. 'cita', 'paciente').
     * @param int         $idEntidad Id del registro afectado.
     * @param string|null $detalle   Información adicional legible.
     */
    public static function registrar(string $accion, string $entidad, int $idEntidad, ?string $detalle = null): void
    {
        $idUsuario = Sesion::usuarioId();
        $rol = Sesion::rol();

        if ($idUsuario === null || $rol === null) {
            return;
        }

        self::registrarComo($idUsuario, $rol, $accion, $entidad, $idEntidad, $detalle);
    }

    /**
     * Registra una acción a nombre de un usuario explícito.
     *
     * Se usa cuando la acción ocurre sin sesión, por ejemplo al confirmar un
     * cambio de contraseña desde el enlace del correo.
     *
     * @param int         $idUsuario Id del actor (id_usuario o id_paciente según el rol).
     * @param string      $rol       Rol del actor.
     * @param string      $accion    Clave de la acción.
     * @param string      $entidad   Tipo de registro afectado.
     * @param int         $idEntidad Id del registro afectado.
     * @param string|null $detalle   Información adicional legible.
     */
    public static function registrarComo(
        int $idUsuario,
        string $rol,
        string $accion,
        string $entidad,
        int $idEntidad,
        ?string $detalle = null
    ): void {
        try {
            Database::getInstance()
                ->prepare('INSERT INTO bitacora (id_usuario, rol, accion, entidad, id_entidad, detalle) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$idUsuario, $rol, $accion, $entidad, $idEntidad, $detalle]);
        } catch (Throwable $e) {
            error_log('Error en Bitacora::registrarComo: ' . $e->getMessage());
        }
    }

    /**
     * Devuelve las fechas de los últimos inicios de sesión de un usuario
     * (historial de accesos que ve el paciente en su cuenta).
     *
     * @param int    $idUsuario Id del usuario.
     * @param string $rol       Rol del usuario.
     * @param int    $limite    Cantidad máxima de registros.
     * @return list<string> Fechas en formato Y-m-d H:i:s, de la más reciente a la más antigua.
     */
    public static function ultimosAccesos(int $idUsuario, string $rol, int $limite = 10): array
    {
        $stmt = Database::getInstance()->prepare(
            "SELECT fecha FROM bitacora
             WHERE accion = 'login_exitoso' AND id_usuario = ? AND rol = ?
             ORDER BY fecha DESC LIMIT ?"
        );
        $stmt->bindValue(1, $idUsuario, PDO::PARAM_INT);
        $stmt->bindValue(2, $rol);
        $stmt->bindValue(3, $limite, PDO::PARAM_INT);
        $stmt->execute();

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
