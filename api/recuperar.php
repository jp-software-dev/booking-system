<?php
// ENDPOINT DE RECUPERACIÓN: Archivo API que valida el correo, genera un token temporal y envía un enlace de texto plano para restablecer la contraseña.

// INICIALIZACIÓN DE SESIÓN: Activa el manejo de sesiones en el servidor para mantener la consistencia de la aplicación.
session_start();

// DEPENDENCIAS: Carga la configuración de la base de datos y la clase ayudante Mailer para el envío de correos.
require_once '../config/Database.php';
require_once '../src/Helpers/Mailer.php';

// 👇 AQUÍ ESTÁ LA CORRECCIÓN: Declaramos el namespace de Mailer 👇
use Helpers\Mailer;

// FORMATO DE RESPUESTA: Configura la cabecera HTTP para asegurar que el frontend procese la respuesta estructurada en formato JSON.
header('Content-Type: application/json');

// VALIDADOR DE MÉTODO: Bloquea intentos de acceso directo mediante URL (GET) obligando a que los datos se envíen por POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

// EXTRACCIÓN DE DATOS: Captura y decodifica el cuerpo de la petición JSON enviada desde el formulario del frontend.
$input = json_decode(file_get_contents('php://input'), true);

// LIMPIEZA DE VARIABLE: Asigna el correo recibido eliminando posibles espacios accidentales al inicio o al final.
$email = trim($input['email'] ?? '');

// FILTRO DE FORMATO: Verifica con una función nativa de PHP que el texto ingresado tenga una estructura de correo electrónico válida.
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Correo electrónico inválido']);
    exit;
}

// CONEXIÓN DB: Obtiene la instancia activa y única de la base de datos utilizando el patrón de diseño Singleton.
$db = Database::getInstance();

// CONSULTA DE USUARIO: Busca en la base de datos para comprobar si el correo proporcionado pertenece a un paciente registrado.
$stmt = $db->prepare("SELECT id_paciente FROM pacientes WHERE email = ?");
$stmt->execute([$email]);

// PROTECCIÓN ANTI-ENUMERACIÓN: Devuelve éxito incluso si el correo no existe para evitar que los atacantes adivinen usuarios válidos.
if (!$stmt->fetch()) {
    echo json_encode(['status' => 'success', 'message' => 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.']);
    exit;
}

// GENERACIÓN DE TOKEN: Crea una clave criptográfica aleatoria de 64 caracteres de alta seguridad para el enlace de recuperación.
$token = bin2hex(random_bytes(32));

// CADUCIDAD: Define de forma estricta que el token de seguridad expirará exactamente una hora después de su creación.
$expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

// REGISTRO DE TOKEN: Almacena en la base de datos el token vinculado al correo junto con su límite de tiempo de validez.
$insert = $db->prepare("INSERT INTO password_resets (email, token, expira) VALUES (?, ?, ?)");
$insert->execute([$email, $token, $expira]);

// CONSTRUCCIÓN DE ENLACE: Arma la URL local exacta de XAMPP concatenando el token único para que el paciente pueda darle clic.
$enlace = "http://localhost/App%20-%20Gestor%20De%20Citas/public/restablecer.php?token=" . $token;

// PLANTILLA DE CORREO: Define el título y el cuerpo del mensaje en texto plano con las instrucciones para el restablecimiento.
$asunto = "Recuperación de contraseña - MediAgenda";
$mensaje = "Hola,\n\nHaz clic en el siguiente enlace para restablecer tu contraseña:\n\n$enlace\n\nEste enlace expirará en 1 hora.\n\nSi no solicitaste este cambio, ignora este mensaje.";

// DESPACHO DE CORREO: Llama a la clase estática Mailer para enviar el mensaje y captura la respuesta del servidor SMTP.
$resultadoEnvio = Mailer::enviarCorreo($email, $asunto, $mensaje);

// MANEJO DE RESULTADOS: Notifica éxito al frontend si el correo se envió correctamente, o devuelve el error técnico si el SMTP falla.
if ($resultadoEnvio === true) {
    echo json_encode(['status' => 'success', 'message' => 'Hemos enviado un enlace a tu correo electrónico.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Fallo SMTP: ' . $resultadoEnvio]);
}
?>