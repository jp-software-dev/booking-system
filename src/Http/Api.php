<?php

declare(strict_types=1);

namespace App\Http;

use App\Helpers\CsrfHelper;

/**
 * UTILIDADES PARA LOS ENDPOINTS JSON DE /api
 *
 * Reúne lo que antes se repetía en cada endpoint: cabecera JSON, validación del
 * método HTTP, control de sesión y rol, lectura del cuerpo JSON, validación del
 * token CSRF y el formato de respuesta.
 *
 * Todas las respuestas tienen la forma {"status": "success"|"error", "message": "..."}
 * más los campos extra de cada endpoint, y usan el código HTTP adecuado.
 */
final class Api
{
    /**
     * Prepara la respuesta como JSON y valida el método HTTP de la petición.
     *
     * Responde 405 si el método no está entre los permitidos.
     *
     * @param string ...$metodosPermitidos Métodos aceptados, por ejemplo 'POST'.
     */
    public static function iniciar(string ...$metodosPermitidos): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');

        $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($metodosPermitidos !== [] && !in_array($metodo, $metodosPermitidos, true)) {
            header('Allow: ' . implode(', ', $metodosPermitidos));
            self::error('Método no permitido', 405);
        }
    }

    /**
     * Exige una sesión activa y, si se indican, uno de los roles dados.
     *
     * Responde 401 si no hay sesión y 403 si el rol no está autorizado.
     *
     * @param string ...$roles Roles aceptados. Sin roles basta con estar autenticado.
     * @return int Id del usuario autenticado.
     */
    public static function exigirSesion(string ...$roles): int
    {
        if (!Sesion::estaAutenticado()) {
            self::error('Sesión no iniciada', 401);
        }

        if ($roles !== [] && !Sesion::tieneRol(...$roles)) {
            self::error('No autorizado', 403);
        }

        return (int) Sesion::usuarioId();
    }

    /**
     * Lee y decodifica el cuerpo JSON de la petición.
     *
     * Responde 400 si el cuerpo está vacío o no es un objeto JSON válido.
     *
     * @return array<string, mixed>
     */
    public static function leerJson(): array
    {
        $cuerpo = file_get_contents('php://input');
        $datos = json_decode($cuerpo === false ? '' : $cuerpo, true);

        if (!is_array($datos)) {
            self::error('Datos inválidos', 400);
        }

        return $datos;
    }

    /**
     * Valida el token CSRF de la petición.
     *
     * Se busca primero en la cabecera X-CSRF-Token (la envía api_client.js) y,
     * si no está, en el campo csrf_token del cuerpo. Responde 403 si no es válido.
     *
     * @param array<string, mixed> $entrada Datos de la petición (JSON o $_POST).
     */
    public static function exigirCsrf(array $entrada = []): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($entrada['csrf_token'] ?? null);

        if (!CsrfHelper::validateToken(is_string($token) ? $token : null)) {
            self::error('Token de seguridad inválido. Recarga la página.', 403);
        }
    }

    /**
     * Responde con éxito y termina la ejecución.
     *
     * @param string               $mensaje Mensaje para el usuario.
     * @param array<string, mixed> $extra   Campos adicionales de la respuesta.
     * @param int                  $codigo  Código HTTP (200 por defecto).
     */
    public static function exito(string $mensaje, array $extra = [], int $codigo = 200): never
    {
        self::json(['status' => 'success', 'message' => $mensaje] + $extra, $codigo);
    }

    /**
     * Responde con error y termina la ejecución.
     *
     * @param string               $mensaje Mensaje seguro para mostrar al usuario.
     * @param int                  $codigo  Código HTTP (400 por defecto).
     * @param array<string, mixed> $extra   Campos adicionales de la respuesta.
     */
    public static function error(string $mensaje, int $codigo = 400, array $extra = []): never
    {
        self::json(['status' => 'error', 'message' => $mensaje] + $extra, $codigo);
    }

    /**
     * Envía el resultado de una operación de un controlador.
     *
     * El resultado debe tener 'status' y 'message'; la clave opcional 'codigo'
     * indica el código HTTP y no se incluye en el cuerpo de la respuesta.
     *
     * @param array<string, mixed> $resultado Resultado devuelto por el controlador.
     */
    public static function responderResultado(array $resultado): never
    {
        $codigo = (int) ($resultado['codigo'] ?? (($resultado['status'] ?? '') === 'success' ? 200 : 400));
        unset($resultado['codigo']);

        self::json($resultado, $codigo);
    }

    /**
     * Envía cualquier dato como JSON y termina la ejecución.
     *
     * @param mixed $datos  Datos serializables a JSON.
     * @param int   $codigo Código HTTP.
     */
    public static function json(mixed $datos, int $codigo = 200): never
    {
        http_response_code($codigo);
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
