<?php
namespace Helpers;

/**
 * HELPER DE PROTECCIÓN CSRF
 * Genera y valida un token por sesión para proteger endpoints que modifican estado.
 */
class CsrfHelper
{
    public static function generateToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateToken(?string $token): bool
    {
        return !empty($_SESSION['csrf_token']) && !empty($token) && hash_equals($_SESSION['csrf_token'], $token);
    }
}