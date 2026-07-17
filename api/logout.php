<?php
session_start();
require_once __DIR__ . '/../src/Helpers/CsrfHelper.php';

use Helpers\CsrfHelper;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header("Location: ../public/index.php");
    exit();
}

if (!CsrfHelper::validateToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    header("Location: ../public/index.php");
    exit();
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();
header("Location: ../public/index.php");
exit();