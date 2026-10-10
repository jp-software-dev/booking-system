<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * PROTECCIÓN CSRF
 *
 * Genera un token aleatorio por sesión y lo valida en cada petición que modifica
 * datos. El token se imprime en la etiqueta <meta name="csrf-token"> del layout
 * y api_client.js lo envía en la cabecera X-CSRF-Token.
 */
final class CsrfHelper
{
    /** Clave de $_SESSION donde se guarda el token. */
    private const CLAVE_SESION = 'csrf_token';

    /**
     * Devuelve el token de la sesión actual, creándolo si todavía no existe.
     */
    public static function generateToken(): string
    {
        if (empty($_SESSION[self::CLAVE_SESION])) {
            $_SESSION[self::CLAVE_SESION] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::CLAVE_SESION];
    }

    /**
     * Compara el token recibido con el de la sesión en tiempo constante.
     *
     * @param string|null $token Token enviado por el cliente.
     */
    public static function validateToken(?string $token): bool
    {
        $tokenSesion = $_SESSION[self::CLAVE_SESION] ?? '';

        return is_string($tokenSesion)
            && $tokenSesion !== ''
            && $token !== null
            && $token !== ''
            && hash_equals($tokenSesion, $token);
    }
}
