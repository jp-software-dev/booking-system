<?php
/**
 * ENDPOINT DE REGISTRO DE PACIENTES
 *
 * Procesa la creación de nuevas cuentas de paciente. Valida los datos del formulario,
 * verifica la unicidad de CURP y correo, y genera las credenciales de acceso de forma
 * segura.
 */

session_start();
require_once '../config/Database.php';
require_once '../src/Helpers/ValidationHelper.php';

use Helpers\ValidationHelper;

header('Content-Type: application/json');

// Habilitar registro de errores para depuración
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_log("=== INICIO DE REGISTRO ===");

// PROTECCIÓN DE PROTOCOLO
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("ERROR: Método no permitido - " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

error_log("Datos POST recibidos: " . print_r($_POST, true));

// SANITIZACIÓN DE ENTRADAS
$nombre = trim($_POST['nombre'] ?? '');
$apellido_paterno = trim($_POST['apellido_paterno'] ?? '');
$apellido_materno = trim($_POST['apellido_materno'] ?? '');
$email = trim($_POST['email'] ?? '');
$edad = trim($_POST['edad'] ?? '');
$genero = trim($_POST['genero'] ?? '');
$curp = strtoupper(trim($_POST['curp'] ?? ''));
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$telefono = trim($_POST['telefono'] ?? '');

error_log("Datos procesados - Nombre: $nombre, Email: $email, Edad: $edad, CURP: $curp, Telefono: $telefono");

// VALIDACIÓN DE CAMPOS OBLIGATORIOS
$campos_vacios = [];
if (empty($nombre)) $campos_vacios[] = 'nombre';
if (empty($apellido_paterno)) $campos_vacios[] = 'apellido_paterno';
if (empty($email)) $campos_vacios[] = 'email';
if (empty($edad)) $campos_vacios[] = 'edad';
if (empty($genero)) $campos_vacios[] = 'genero';
if (empty($curp)) $campos_vacios[] = 'curp';
if (empty($password)) $campos_vacios[] = 'password';
if (empty($confirm_password)) $campos_vacios[] = 'confirm_password';
if (empty($telefono)) $campos_vacios[] = 'telefono';

if (!empty($campos_vacios)) {
    error_log("CAMPOS VACÍOS: " . implode(', ', $campos_vacios));
    echo json_encode(['status' => 'error', 'message' => 'Todos los campos obligatorios deben estar llenos. Faltan: ' . implode(', ', $campos_vacios)]);
    exit;
}

// VALIDACIÓN DE EDAD
if (!is_numeric($edad) || (int)$edad < 18) {
    error_log("EDAD INVÁLIDA: $edad");
    echo json_encode(['status' => 'error', 'message' => 'Debes ser mayor de 18 años para registrarte']);
    exit;
}

// VALIDACIÓN DE CURP
if (!ValidationHelper::validateCURP($curp)) {
    error_log("CURP INVÁLIDA: $curp");
    echo json_encode(['status' => 'error', 'message' => 'Formato de CURP inválido o dígito verificador incorrecto.']);
    exit;
}

// VALIDACIÓN DE CONTRASEÑAS
if ($password !== $confirm_password) {
    error_log("CONTRASEÑAS NO COINCIDEN");
    echo json_encode(['status' => 'error', 'message' => 'Las contraseñas no coinciden']);
    exit;
}

if (strlen($password) < 8) {
    error_log("CONTRASEÑA DEMASIADO CORTA: " . strlen($password) . " caracteres");
    echo json_encode(['status' => 'error', 'message' => 'La contraseña debe tener al menos 8 caracteres']);
    exit;
}

// VALIDACIÓN DE TELÉFONO
if (!preg_match('/^[0-9]{10}$/', $telefono)) {
    error_log("TELÉFONO INVÁLIDO: $telefono");
    echo json_encode(['status' => 'error', 'message' => 'El teléfono debe contener exactamente 10 dígitos numéricos']);
    exit;
}

// VALIDACIÓN DE EMAIL
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    error_log("EMAIL INVÁLIDO: $email");
    echo json_encode(['status' => 'error', 'message' => 'El correo electrónico no es válido']);
    exit;
}

// CONEXIÓN DB
try {
    error_log("Intentando conectar a la base de datos...");
    $db = Database::getInstance();
    error_log("Conexión a BD exitosa");
} catch (Exception $e) {
    error_log("ERROR DE CONEXIÓN A BD: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión a la base de datos']);
    exit;
}

try {
    // VERIFICAR CURP DUPLICADA
    error_log("Verificando CURP duplicada: $curp");
    $checkCurp = $db->prepare("SELECT id_paciente FROM pacientes WHERE curp = ?");
    $checkCurp->execute([$curp]);
    if ($checkCurp->fetch()) {
        error_log("CURP YA REGISTRADA: $curp");
        echo json_encode(['status' => 'error', 'message' => 'Esta CURP ya está registrada']);
        exit;
    }
    error_log("CURP disponible");

    // VERIFICAR EMAIL DUPLICADO
    error_log("Verificando email duplicado: $email");
    $checkEmail = $db->prepare("SELECT id_paciente FROM pacientes WHERE email = ?");
    $checkEmail->execute([$email]);
    if ($checkEmail->fetch()) {
        error_log("EMAIL YA REGISTRADO: $email");
        echo json_encode(['status' => 'error', 'message' => 'El correo ya está registrado']);
        exit;
    }
    error_log("Email disponible");

    // INICIAR TRANSACCIÓN
    error_log("Iniciando transacción...");
    $db->beginTransaction();

    // Calcular fecha de nacimiento a partir de la edad
    $fecha_nacimiento = date('Y-m-d', strtotime("-{$edad} years"));
    error_log("Fecha de nacimiento calculada: $fecha_nacimiento");

    // INSERTAR PACIENTE
    error_log("Insertando paciente...");
    $stmt = $db->prepare("INSERT INTO pacientes (curp, nombre, apellido_paterno, apellido_materno, fecha_nacimiento, genero, telefono, email) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    $resultado1 = $stmt->execute([$curp, $nombre, $apellido_paterno, $apellido_materno, $fecha_nacimiento, $genero, $telefono, $email]);
    
    if (!$resultado1) {
        error_log("ERROR al insertar paciente: " . print_r($stmt->errorInfo(), true));
        throw new Exception("Error al insertar paciente");
    }
    
    $id_paciente = $db->lastInsertId();
    error_log("Paciente insertado con ID: $id_paciente");

    // INSERTAR CREDENCIALES
    error_log("Insertando credenciales...");
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt2 = $db->prepare("INSERT INTO pacientes_credenciales (id_paciente, password_hash, cuenta_verificada) VALUES (?, ?, 1)");
    
    $resultado2 = $stmt2->execute([$id_paciente, $hash]);
    
    if (!$resultado2) {
        error_log("ERROR al insertar credenciales: " . print_r($stmt2->errorInfo(), true));
        throw new Exception("Error al insertar credenciales");
    }
    error_log("Credenciales insertadas correctamente");

    // CONFIRMAR TRANSACCIÓN
    $db->commit();
    error_log("Transacción confirmada (COMMIT)");

    // INICIAR SESIÓN
    $_SESSION['user_id'] = $id_paciente;
    $_SESSION['user_name'] = $nombre;
    $_SESSION['role'] = 'paciente';
    error_log("Sesión iniciada para usuario ID: $id_paciente");

    // RESPUESTA EXITOSA
    echo json_encode(['status' => 'success', 'message' => 'Registro exitoso', 'redirect' => 'agenda.php']);
    error_log("=== REGISTRO EXITOSO ===");

} catch (PDOException $e) {
    error_log("=== ERROR PDO ===");
    error_log("Código de error: " . $e->getCode());
    error_log("Mensaje: " . $e->getMessage());
    
    if ($db->inTransaction()) {
        $db->rollBack();
        error_log("Transacción revertida (ROLLBACK)");
    }
    
    echo json_encode(['status' => 'error', 'message' => 'Error de base de datos: ' . $e->getMessage()]);
    
} catch (Exception $e) {
    error_log("=== ERROR GENERAL ===");
    error_log("Mensaje: " . $e->getMessage());
    error_log("Archivo: " . $e->getFile());
    error_log("Línea: " . $e->getLine());
    
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
        error_log("Transacción revertida (ROLLBACK)");
    }
    
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor: ' . $e->getMessage()]);
}
?>