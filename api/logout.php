<?php
/**
 * ENDPOINT DE CIERRE DE SESIÓN
 *
 * Destruye la sesión activa del usuario (paciente o administrador) y lo redirige
 * a la página principal del sitio.
 *
 * @requires session_start
 * @redirect public/index.php
 */

session_start();

// DESTRUCCIÓN DE DATOS: Elimina todas las variables de sesión registradas.
session_destroy();

// REDIRECCIÓN SEGURA: Envía al usuario de vuelta a la página principal.
header("Location: ../public/index.php");

exit();
?>