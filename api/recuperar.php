<?php
/**
 * ENDPOINT DE SOLICITUD DE RECUPERACIÓN DE CONTRASEÑA
 *
 * Valida el correo del paciente, genera un token temporal único y envía un enlace
 * de texto plano para restablecer la contraseña. Implementa protección contra
 * enumeración de usuarios.
 *
 * @requires session_start
 * @requires config/Database.php
 * @requires src/Helpers/Mailer.php
 * @requires method POST
 * @response application/json
 */

session_start();

require_once '../config/Database.php';
require_once '../src/Helpers/Mailer.php';

use Helpers\Mailer;

header('Content-Type: application/json');

// VALIDADOR DE MÉTODO: Bloquea accesos directos que no sean por POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

// EXTRACCIÓN DE DATOS: Captura y decodifica el cuerpo de la petición JSON.
$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

// FILTRO DE FORMATO: Verifica que el texto tenga estructura de correo electrónico.
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Correo electrónico inválido']);
    exit;
}

$db = Database::getInstance();

// CONSULTA DE USUARIO: Comprueba si el correo pertenece a un paciente registrado.
$stmt = $db->prepare("SELECT id_paciente FROM pacientes WHERE email = ?");
$stmt->execute([$email]);

// PROTECCIÓN ANTI-ENUMERACIÓN: Devuelve éxito incluso si el correo no existe.
if (!$stmt->fetch()) {
    echo json_encode(['status' => 'success', 'message' => 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.']);
    exit;
}

// GENERACIÓN DE TOKEN: Crea una clave criptográfica aleatoria de 64 caracteres.
$token = bin2hex(random_bytes(32));
// CADUCIDAD: El token expirará exactamente una hora después de su creación.
$expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

// REGISTRO DE TOKEN: Almacena el token en la base de datos vinculado al correo.
$insert = $db->prepare("INSERT INTO password_resets (email, token, expira) VALUES (?, ?, ?)");
$insert->execute([$email, $token, $expira]);

// CONSTRUCCIÓN DE ENLACE: Arma la URL local con el token único.
$enlace = "http://localhost/App%20-%20Gestor%20De%20Citas/public/restablecer.php?token=" . $token;

// PLANTILLA DE CORREO: Define el título y el cuerpo del mensaje en texto plano.
$asunto = "Recuperación de contraseña - MediAgenda";
$mensaje = "Hola,\n\nHaz clic en el siguiente enlace para restablecer tu contraseña:\n\n$enlace\n\nEste enlace expirará en 1 hora.\n\nSi no solicitaste este cambio, ignora este mensaje.";

// DESPACHO DE CORREO: Llama al servicio Mailer para enviar el mensaje.
$resultadoEnvio = Mailer::enviarCorreo($email, $asunto, $mensaje);

// MANEJO DE RESULTADOS: Notifica éxito o devuelve el error del SMTP.
if ($resultadoEnvio === true) {
    echo json_encode(['status' => 'success', 'message' => 'Hemos enviado un enlace a tu correo electrónico.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Fallo SMTP: ' . $resultadoEnvio]);
}
?>