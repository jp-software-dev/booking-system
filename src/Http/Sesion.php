<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Env;

/**
 * MANEJO DE LA SESIÓN DEL USUARIO
 *
 * Centraliza el arranque seguro de la sesión, el inicio y cierre de sesión y
 * las consultas sobre el usuario autenticado. Las claves guardadas en
 * $_SESSION ('user_id', 'user_name', 'role') se conservan de la versión
 * anterior para no invalidar sesiones activas al desplegar.
 */
final class Sesion
{
    /** Rol de los usuarios de la tabla `usuarios` con acceso al panel. */
    public const ROL_ADMIN = 'admin';

    /** Rol de los pacientes registrados (tabla `pacientes`). */
    public const ROL_PACIENTE = 'paciente';

    /**
     * Configura la cookie de sesión de forma segura y arranca la sesión.
     *
     * Debe llamarse antes de cualquier session_start() y antes de enviar salida:
     * por eso se invoca únicamente desde config/config.php.
     *
     * @param bool $cookieSegura true para exigir HTTPS en la cookie (producción).
     */
    public static function iniciar(bool $cookieSegura): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Rechaza identificadores de sesión que no haya generado el servidor.
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        // En plataformas sin disco compartido (Vercel) las sesiones se guardan en la base de datos.
        if (Env::get('SESSION_DRIVER', 'files') === 'database') {
            session_set_save_handler(new ManejadorSesionBD(), true);
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $cookieSegura,
            'httponly' => true,  // JavaScript no puede leer la cookie.
            'samesite' => 'Lax', // No se envía en peticiones POST de otros sitios.
        ]);

        session_start();
    }

    /**
     * Registra al usuario como autenticado.
     *
     * Regenera el identificador de sesión para evitar ataques de fijación de sesión.
     *
     * @param int    $idUsuario id_usuario (admin) o id_paciente (paciente).
     * @param string $nombre    Nombre que se muestra en la barra de navegación.
     * @param string $rol       Sesion::ROL_ADMIN o Sesion::ROL_PACIENTE.
     */
    public static function autenticar(int $idUsuario, string $nombre, string $rol): void
    {
        session_regenerate_id(true);

        $_SESSION['user_id']   = $idUsuario;
        $_SESSION['user_name'] = $nombre;
        $_SESSION['role']      = $rol;
    }

    /**
     * Cierra la sesión: vacía los datos, expira la cookie y destruye la sesión.
     */
    public static function cerrar(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?: 'Lax',
            ]);
        }

        session_destroy();
    }

    /**
     * Indica si hay un usuario autenticado.
     */
    public static function estaAutenticado(): bool
    {
        return isset($_SESSION['user_id'], $_SESSION['role']);
    }

    /**
     * Devuelve el id del usuario autenticado o null si no hay sesión.
     */
    public static function usuarioId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    /**
     * Devuelve el rol del usuario autenticado o null si no hay sesión.
     */
    public static function rol(): ?string
    {
        return isset($_SESSION['role']) ? (string) $_SESSION['role'] : null;
    }

    /**
     * Devuelve el nombre del usuario autenticado, o el texto indicado si no hay sesión.
     *
     * @param string $porDefecto Texto a usar cuando no hay nombre en sesión.
     */
    public static function nombre(string $porDefecto = 'Usuario'): string
    {
        return isset($_SESSION['user_name']) ? (string) $_SESSION['user_name'] : $porDefecto;
    }

    /**
     * Actualiza el nombre mostrado (por ejemplo, tras editar el perfil).
     *
     * @param string $nombre Nuevo nombre.
     */
    public static function actualizarNombre(string $nombre): void
    {
        $_SESSION['user_name'] = $nombre;
    }

    /**
     * Indica si el usuario autenticado tiene alguno de los roles indicados.
     *
     * @param string ...$roles Roles aceptados.
     */
    public static function tieneRol(string ...$roles): bool
    {
        return self::estaAutenticado() && in_array(self::rol(), $roles, true);
    }

    /**
     * Indica si el usuario autenticado es administrador.
     */
    public static function esAdmin(): bool
    {
        return self::tieneRol(self::ROL_ADMIN);
    }

    /**
     * Protege una página: si no hay sesión con alguno de los roles indicados,
     * redirige a login.php y termina la ejecución.
     *
     * Solo para las vistas de /public. Los endpoints de /api usan Api::exigirSesion().
     *
     * @param string ...$roles Roles con acceso a la página.
     */
    public static function exigirRol(string ...$roles): void
    {
        if (!self::tieneRol(...$roles)) {
            self::redirigir('login.php');
        }
    }

    /**
     * Envía una redirección HTTP y termina la ejecución.
     *
     * @param string $url Destino (relativo a la página actual o absoluto).
     */
    public static function redirigir(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    /**
     * Página de inicio que corresponde al usuario según su rol.
     */
    public static function paginaInicio(): string
    {
        return self::esAdmin() ? 'admin.php' : 'agenda.php';
    }
}
