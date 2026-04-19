<?php
/**
 * ENDPOINT DE SOLICITUD DE RECUPERACIÓN DE CONTRASEÑA
 *
 * Valida el correo del paciente, genera un token temporal único y envía un enlace
 * para restablecer la contraseña.
 */

session_start();

// Mostrar errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/Database.php';
require_once '../src/Helpers/Mailer.php';

use Helpers\Mailer;

header('Content-Type: application/json');

// VALIDADOR DE MÉTODO
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

// EXTRACCIÓN DE DATOS
$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

error_log("=== RECUPERAR CONTRASEÑA ===");
error_log("Email recibido: " . $email);

// FILTRO DE FORMATO
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    error_log("Email inválido: " . $email);
    echo json_encode(['status' => 'error', 'message' => 'Correo electrónico inválido']);
    exit;
}

try {
    $db = Database::getInstance();
    error_log("Conexión a BD exitosa");
} catch (Exception $e) {
    error_log("Error conexión BD: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión a la base de datos']);
    exit;
}

// CONSULTA DE USUARIO
try {
    $stmt = $db->prepare("SELECT id_paciente FROM pacientes WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();
    
    error_log("Usuario encontrado: " . ($usuario ? 'SI' : 'NO'));
    
    // PROTECCIÓN ANTI-ENUMERACIÓN: Siempre decimos que se envió el correo
    if (!$usuario) {
        error_log("Email no registrado: " . $email);
        echo json_encode(['status' => 'success', 'message' => 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.']);
        exit;
    }
    
    // GENERACIÓN DE TOKEN
    $token = bin2hex(random_bytes(32));
    $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));
    error_log("Token generado: " . $token);
    error_log("Expira: " . $expira);
    
    // REGISTRO DE TOKEN
    $insert = $db->prepare("INSERT INTO password_resets (email, token, expira) VALUES (?, ?, ?)");
    $resultado = $insert->execute([$email, $token, $expira]);
    
    if (!$resultado) {
        error_log("Error al insertar token: " . print_r($insert->errorInfo(), true));
        echo json_encode(['status' => 'error', 'message' => 'Error al generar el enlace de recuperación']);
        exit;
    }
    
    error_log("Token guardado correctamente");
    
    // CONSTRUCCIÓN DE ENLACE
    $enlace = "http://localhost/app_gestor_citas/public/restablecer.php?token=" . $token;
    error_log("Enlace generado: " . $enlace);
    
    // PLANTILLA DE CORREO
    $asunto = "Recuperación de contraseña - MediAgenda";
    $mensaje = "Hola,\n\nHaz clic en el siguiente enlace para restablecer tu contraseña:\n\n$enlace\n\nEste enlace expirará en 1 hora.\n\nSi no solicitaste este cambio, ignora este mensaje.\n\nSaludos,\nMediAgenda";
    
    // DESPACHO DE CORREO
    error_log("Intentando enviar correo a: " . $email);
    $resultadoEnvio = Mailer::enviarCorreo($email, $asunto, $mensaje);
    
    if ($resultadoEnvio === true) {
        error_log("CORREO ENVIADO EXITOSAMENTE a " . $email);
        echo json_encode(['status' => 'success', 'message' => 'Hemos enviado un enlace a tu correo electrónico.']);
    } else {
        error_log("ERROR al enviar correo: " . $resultadoEnvio);
        echo json_encode(['status' => 'error', 'message' => 'No se pudo enviar el correo. Intenta más tarde.']);
    }
    
} catch (PDOException $e) {
    error_log("Error PDO en recuperar.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error de base de datos']);
} catch (Exception $e) {
    error_log("Error general en recuperar.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor']);
}
?>