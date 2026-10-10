<?php

/**
 * ENDPOINT: CIERRE DE SESIÓN
 *
 * Recibe el formulario "Cerrar sesión" del menú y redirige a la página de inicio.
 *
 * Método: POST (formulario HTML)
 * Acceso: cualquier usuario autenticado, con token CSRF
 *
 * Se exige POST + CSRF para que un enlace o imagen de otro sitio no pueda
 * cerrar la sesión del usuario sin su intención.
 */

require_once __DIR__ . '/../config/config.php';

use App\Helpers\CsrfHelper;
use App\Http\Sesion;

$esPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$tokenValido = CsrfHelper::validateToken(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null);

if ($esPost && $tokenValido) {
    Sesion::cerrar();
}

Sesion::redirigir('../public/index.php');
