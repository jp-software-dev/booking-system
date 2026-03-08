<?php
// ENDPOINT LOGOUT: Archivo encargado de cerrar la sesión activa del usuario (paciente o admin) y proteger sus datos al salir.

// INICIALIZACIÓN DE SESIÓN: Reanuda la sesión actual activa en el servidor para poder acceder a ella y manipularla.
session_start();

// DESTRUCCIÓN DE DATOS: Elimina por completo todas las variables de sesión registradas, cerrando efectivamente la cuenta del usuario.
session_destroy();

// REDIRECCIÓN SEGURA: Envía al usuario de regreso a la página principal (landing page o login) tras destruir su sesión.
header("Location: ../public/index.php");

// TERMINACIÓN DE SCRIPT: Detiene inmediatamente la ejecución del archivo para asegurar que no se procese ningún código adicional por seguridad.
exit();
?>