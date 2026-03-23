<?php
session_start();
require_once '../config/Database.php';
require_once '../src/Helpers/ValidationHelper.php';

use Helpers\ValidationHelper;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

$nombre = trim($_POST['nombre'] ?? '');
$apellido_paterno = trim($_POST['apellido_paterno'] ?? '');
$apellido_materno = trim($_POST['apellido_materno'] ?? '');
$email = trim($_POST['email'] ?? '');
$edad = intval($_POST['edad'] ?? 0);
$genero = trim($_POST['genero'] ?? '');
$curp = strtoupper(trim($_POST['curp'] ?? ''));
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Teléfono ficticio para evitar error UNIQUE
$telefono = '00' . rand(10000000, 99999999);

if (empty($nombre) || empty($apellido_paterno) || empty($email) || empty($edad) || empty($genero) || empty($curp) || empty($password) || empty($confirm_password)) {
    echo json_encode(['status' => 'error', 'message' => 'Todos los campos obligatorios deben estar llenos']);
    exit;
}

// Validar CURP con algoritmo oficial
if (!ValidationHelper::validateCURP($curp)) {
    echo json_encode(['status' => 'error', 'message' => 'Formato de CURP inválido o dígito verificador incorrecto.']);
    exit;
}

if ($password !== $confirm_password) {
    echo json_encode(['status' => 'error', 'message' => 'Las contraseñas no coinciden']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['status' => 'error', 'message' => 'La contraseña debe tener al menos 8 caracteres']);
    exit;
}

$db = Database::getInstance();

try {
    $checkCurp = $db->prepare("SELECT id_paciente FROM pacientes WHERE curp = ?");
    $checkCurp->execute([$curp]);
    if ($checkCurp->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Esta CURP ya está registrada']);
        exit;
    }

    $checkEmail = $db->prepare("SELECT id_paciente FROM pacientes WHERE email = ?");
    $checkEmail->execute([$email]);
    if ($checkEmail->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'El correo ya está registrado']);
        exit;
    }

    $db->beginTransaction();

    $stmt = $db->prepare("INSERT INTO pacientes (curp, nombre, apellido_paterno, apellido_materno, edad, genero, telefono, email) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$curp, $nombre, $apellido_paterno, $apellido_materno, $edad, $genero, $telefono, $email]);
    $id_paciente = $db->lastInsertId();

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt2 = $db->prepare("INSERT INTO pacientes_credenciales (id_paciente, password_hash, cuenta_verificada) VALUES (?, ?, 1)");
    $stmt2->execute([$id_paciente, $hash]);

    $db->commit();

    $_SESSION['user_id'] = $id_paciente;
    $_SESSION['user_name'] = $nombre;
    $_SESSION['role'] = 'paciente';

    echo json_encode(['status' => 'success', 'message' => 'Registro exitoso', 'redirect' => 'agenda.php']);
} catch (Exception $e) {
    $db->rollBack();
    error_log("Error en registro: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor: ' . $e->getMessage()]);
}