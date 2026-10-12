<?php

/**
 * PUNTO DE ENTRADA EN VERCEL
 *
 * Vercel ejecuta PHP como una función (runtime vercel-php) y no lee los .htaccess.
 * vercel.json envía aquí todas las peticiones (salvo /public/assets, que Vercel
 * sirve como archivos estáticos) y este archivo decide qué ejecutar:
 *
 *   /                       -> redirige a /public/index.php
 *   /public/<pagina>[.php]  -> public/<pagina>.php (con o sin extensión, como en Apache)
 *   /api/<endpoint>.php     -> api/<endpoint>.php
 *   cualquier otra ruta     -> 404
 *
 * Seguridad: solo se aceptan nombres con letras minúsculas y guiones bajos que
 * existan como archivo dentro de public/ o api/. Así nunca se ejecuta ni se
 * muestra el contenido de config/, src/, vendor/ ni database/.
 *
 * Fuera de Vercel (por ejemplo, en Apache/XAMPP) responde siempre 404: ahí cada
 * archivo se sirve directamente y este punto de entrada no se usa.
 */

declare(strict_types=1);

if (getenv('VERCEL') !== '1') {
    http_response_code(404);
    exit;
}

$raiz = dirname(__DIR__);
$ruta = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

// === RAÍZ DEL SITIO ===
if ($ruta === '/' || $ruta === '/public' || $ruta === '/public/') {
    header('Location: /public/index.php', true, 302);
    exit;
}

// === PÁGINAS Y ENDPOINTS PERMITIDOS ===
if (preg_match('#^/(public|api)/([a-z_]+)(\.php)?$#', $ruta, $partes) === 1) {
    [, $carpeta, $nombre] = $partes;
    $esEndpointConExtension = $carpeta === 'public' || ($partes[3] ?? '') === '.php';
    $archivo = "{$raiz}/{$carpeta}/{$nombre}.php";

    if ($nombre !== 'vercel' && $esEndpointConExtension && is_file($archivo)) {
        // Las páginas usan SCRIPT_NAME para saber cuál es la página actual
        // (menú activo) y si la petición vino a /api (formato de errores).
        $_SERVER['SCRIPT_NAME'] = "/{$carpeta}/{$nombre}.php";
        $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
        $_SERVER['SCRIPT_FILENAME'] = $archivo;
        chdir(dirname($archivo));

        // La página se ejecuta en este mismo ámbito: se limpian las variables
        // del enrutador para que no se mezclen con las suyas.
        $paginaVercel = $archivo;
        unset($raiz, $ruta, $partes, $carpeta, $nombre, $archivo, $esEndpointConExtension);

        require $paginaVercel;
        exit;
    }
}

// === CUALQUIER OTRA RUTA ===
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Página no encontrada';
