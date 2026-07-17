<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../src/Helpers/Mailer.php';

use Helpers\Mailer;

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Correo electrónico inválido']);
    exit;
}

try {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT id_paciente FROM pacientes WHERE email = ?");
    $stmt->execute([$email]);
    $paciente = $stmt->fetch();

    if (!$paciente) {
        echo json_encode(['status' => 'success', 'message' => 'Si el correo existe, se ha enviado un enlace para restablecer tu contraseña.']);
        exit;
    }

    $token = bin2hex(random_bytes(32));
    $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $insert = $db->prepare("INSERT INTO password_resets (email, token, expira, usado) VALUES (?, ?, ?, 0)");
    $insert->execute([$email, $token, $expira]);

    $enlace = BASE_URL . "public/restablecer.php?token=" . $token;
    $asunto = "Recuperación de contraseña - MediAgenda";
    $mensaje = "Hola,\n\nHaz clic en el siguiente enlace para restablecer tu contraseña:\n\n$enlace\n\nEste enlace expirará en 1 hora.\n\nSi no solicitaste este cambio, ignora este mensaje.";

    $resultadoEnvio = Mailer::enviarCorreo($email, $asunto, $mensaje);
    
    if ($resultadoEnvio !== true) {
        // Es importante registrar el fallo, pero no exponerlo al cliente para no revelar si un correo existe o no.
        error_log("Error al enviar el correo de recuperación para el email: " . $email);
    }
    echo json_encode(['status' => 'success', 'message' => 'Si el correo existe, se ha enviado un enlace para restablecer tu contraseña.']);
} catch (Exception $e) {
    error_log("Error en recuperar.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor']);
}