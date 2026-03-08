<?php
// ENDPOINT DE REGISTRO: Archivo API que recibe los datos del formulario, valida la información, encripta la contraseña y guarda al nuevo paciente en la base de datos.
session_start();
require_once '../config/Database.php';

// FORMATO DE RESPUESTA: Define que toda la comunicación con el frontend será mediante JSON.
header('Content-Type: application/json');

// VALIDADOR DE MÉTODO: Bloquea cualquier petición que no sea POST para evitar accesos directos no autorizados por URL.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

// EXTRACCIÓN Y LIMPIEZA: Recibe las variables del formulario y elimina espacios en blanco al inicio y al final (trim).
$nombre = trim($_POST['nombre'] ?? '');
$apellido_paterno = trim($_POST['apellido_paterno'] ?? '');
$apellido_materno = trim($_POST['apellido_materno'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$edad = intval($_POST['edad'] ?? 0);
$genero = trim($_POST['genero'] ?? '');
$curp = strtoupper(trim($_POST['curp'] ?? ''));
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// VALIDADOR DE CAMPOS VACÍOS: Asegura que toda la información obligatoria esté presente antes de procesar el registro.
if (empty($nombre) || empty($apellido_paterno) || empty($email) || empty($telefono) || empty($edad) || empty($genero) || empty($curp) || empty($password) || empty($confirm_password)) {
    echo json_encode(['status' => 'error', 'message' => 'Todos los campos obligatorios deben estar llenos']);
    exit;
}

// VALIDADOR DE CURP: Verifica mediante una expresión regular que la CURP tenga exactamente 18 caracteres alfanuméricos.
if (!preg_match('/^[A-Z0-9]{18}$/', $curp)) {
    echo json_encode(['status' => 'error', 'message' => 'Formato de CURP inválido. Debe contener exactamente 18 caracteres alfanuméricos.']);
    exit;
}

// VALIDADOR DE CONTRASEÑAS: Comprueba que la contraseña ingresada y su confirmación sean exactamente iguales.
if ($password !== $confirm_password) {
    echo json_encode(['status' => 'error', 'message' => 'Las contraseñas no coinciden']);
    exit;
}

// VALIDADOR DE LONGITUD: Exige un mínimo de 8 caracteres en la contraseña por políticas de seguridad.
if (strlen($password) < 8) {
    echo json_encode(['status' => 'error', 'message' => 'La contraseña debe tener al menos 8 caracteres']);
    exit;
}

// VALIDADOR DE CORREO: Confirma mediante un filtro nativo de PHP que el email tenga una estructura válida.
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Correo electrónico no válido']);
    exit;
}

// VALIDADOR DE TELÉFONO: Exige mediante expresión regular que el número de teléfono contenga exactamente 10 dígitos.
if (!preg_match('/^[0-9]{10}$/', $telefono)) {
    echo json_encode(['status' => 'error', 'message' => 'El teléfono debe tener exactamente 10 dígitos numéricos']);
    exit;
}

// CONEXIÓN DB: Llama a la conexión única de la base de datos (patrón Singleton).
$db = Database::getInstance();

try {
    // CONTROL DE DUPLICADOS (CURP): Evita que se registre más de un paciente con la misma CURP.
    $checkCurp = $db->prepare("SELECT id_paciente FROM pacientes WHERE curp = ?");
    $checkCurp->execute([$curp]);
    if ($checkCurp->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Esta CURP ya está registrada en el sistema']);
        exit;
    }

    // CONTROL DE DUPLICADOS (EMAIL): Asegura que el correo electrónico sea único para evitar conflictos de inicio de sesión.
    $checkEmail = $db->prepare("SELECT id_paciente FROM pacientes WHERE email = ?");
    $checkEmail->execute([$email]);
    if ($checkEmail->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'El correo ya está registrado']);
        exit;
    }

    // CONTROL DE DUPLICADOS (TELÉFONO): Impide que diferentes pacientes compartan el mismo número de contacto.
    $checkTel = $db->prepare("SELECT id_paciente FROM pacientes WHERE telefono = ?");
    $checkTel->execute([$telefono]);
    if ($checkTel->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'El teléfono ya está registrado por otro paciente']);
        exit;
    }

    // TRANSACCIÓN: Inicia un proceso seguro; si algo falla más adelante, no se guardará información a medias en las tablas.
    $db->beginTransaction();

    // INSERCIÓN DE PACIENTE: Guarda los datos personales del usuario en la tabla principal y recupera el ID generado.
    $stmt = $db->prepare("INSERT INTO pacientes (curp, nombre, apellido_paterno, apellido_materno, edad, genero, telefono, email) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$curp, $nombre, $apellido_paterno, $apellido_materno, $edad, $genero, $telefono, $email]);
    $id_paciente = $db->lastInsertId();

    // SEGURIDAD Y CREDENCIALES: Convierte la contraseña a un hash irreversible y la guarda vinculada al paciente.
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt2 = $db->prepare("INSERT INTO pacientes_credenciales (id_paciente, password_hash, cuenta_verificada) VALUES (?, ?, 1)");
    $stmt2->execute([$id_paciente, $hash]);

    // CONFIRMACIÓN DE DB: Aplica y guarda definitivamente todas las inserciones realizadas durante la transacción.
    $db->commit();

    // AUTO-LOGIN: Crea las variables de sesión para que el usuario entre directo a su agenda sin tener que iniciar sesión manualmente.
    $_SESSION['user_id'] = $id_paciente;
    $_SESSION['user_name'] = $nombre;
    $_SESSION['role'] = 'paciente';

    // RESPUESTA EXITOSA: Devuelve la señal al frontend para que redirija al nuevo paciente a su panel.
    echo json_encode(['status' => 'success', 'message' => 'Registro exitoso', 'redirect' => 'agenda.php']);
} catch (Exception $e) {
    // MANEJO DE ERRORES: Si ocurre un fallo en la BD, revierte la transacción, lo registra en el log y envía un error genérico por seguridad.
    $db->rollBack();
    error_log("Error en registro: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor']);
}
?>