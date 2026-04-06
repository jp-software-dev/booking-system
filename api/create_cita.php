<?php
session_start();
require_once '../config/Database.php';
require_once '../src/Helpers/ValidationHelper.php';
require_once '../src/Services/EmailService.php';

use Helpers\ValidationHelper;
use Services\EmailService;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['status' => 'error', 'message' => 'Datos inválidos']);
    exit;
}

$id_doctor = $input['id_doctor'] ?? 0;
$fecha = $input['fecha_cita'] ?? '';
$hora = $input['hora_inicio'] ?? ''; 
$motivo = $input['motivo'] ?? 'Consulta general';
$email = trim($input['email'] ?? '');

if (!$id_doctor || !$fecha || !$hora || !$email) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

// Validaciones centralizadas
if (!ValidationHelper::validateDate($fecha)) {
    echo json_encode(['status' => 'error', 'message' => 'Fecha inválida']);
    exit;
}
if (!ValidationHelper::validateTime($hora)) {
    echo json_encode(['status' => 'error', 'message' => 'Hora inválida (use HH:MM)']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Correo electrónico no válido']);
    exit;
}

$db = Database::getInstance();
try {
    $db->beginTransaction();

    $id_paciente = null;
    $paciente_nombre = '';

    if ($_SESSION['role'] === 'admin') {
        $stmt = $db->prepare("SELECT id_paciente, nombre FROM pacientes WHERE email = ?");
        $stmt->execute([$email]);
        $pacienteExistente = $stmt->fetch();
        if ($pacienteExistente) {
            $id_paciente = $pacienteExistente['id_paciente'];
            $paciente_nombre = $pacienteExistente['nombre'];
        } else {
            $curp_temp = 'ADMIN' . time() . rand(100, 999);
            $insert = $db->prepare("INSERT INTO pacientes (curp, nombre, apellido_paterno, apellido_materno, edad, genero, email) 
                                    VALUES (?, 'Admin', 'Temp', '', 0, 'Otro', ?)");
            $insert->execute([$curp_temp, $email]);
            $id_paciente = $db->lastInsertId();
            $paciente_nombre = 'Admin Temp';
        }
    } else {
        $id_paciente = $_SESSION['user_id'];
        $stmtNom = $db->prepare("SELECT nombre FROM pacientes WHERE id_paciente = ?");
        $stmtNom->execute([$id_paciente]);
        $pac = $stmtNom->fetch();
        $paciente_nombre = $pac['nombre'] ?? 'Paciente';

        $checkEmail = $db->prepare("SELECT id_paciente FROM pacientes WHERE email = ? AND id_paciente != ?");
        $checkEmail->execute([$email, $id_paciente]);
        if ($checkEmail->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'El correo ya está registrado por otro paciente']);
            exit;
        }
        $updatePaciente = $db->prepare("UPDATE pacientes SET email = ? WHERE id_paciente = ?");
        $updatePaciente->execute([$email, $id_paciente]);
    }

    // Verificar disponibilidad
    $checkDisponible = $db->prepare("SELECT id_cita FROM citas WHERE id_doctor = ? AND fecha_cita = ? AND hora_inicio = ? AND estado_cita != 'Cancelada'");
    $checkDisponible->execute([$id_doctor, $fecha, $hora]);
    if ($checkDisponible->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'El horario ya no está disponible']);
        exit;
    }

    // Insertar cita
    $query = "INSERT INTO citas (id_paciente, id_doctor, fecha_cita, hora_inicio, motivo_consulta, estado_cita) 
              VALUES (?, ?, ?, ?, ?, 'Pendiente')";
    $stmt = $db->prepare($query);
    $stmt->execute([$id_paciente, $id_doctor, $fecha, $hora, $motivo]);
    $id_cita = $db->lastInsertId();

    $db->commit();

    // Obtener datos del doctor para el correo
    $docStmt = $db->prepare("SELECT nombre, apellido_paterno FROM doctores WHERE id_doctor = ?");
    $docStmt->execute([$id_doctor]);
    $doctor = $docStmt->fetch();
    $doctor_nombre = $doctor['nombre'] . ' ' . $doctor['apellido_paterno'];

    // Preparar datos para el servicio de correo
    $datosCita = [
        'paciente_nombre' => $paciente_nombre,
        'paciente_email'  => $email,
        'doctor_nombre'   => $doctor_nombre,
        'fecha'           => $fecha,
        'hora'            => $hora,
        'motivo'          => $motivo
    ];

    // Enviar correos usando el servicio
    EmailService::enviarConfirmacionCita($email, $datosCita);
    EmailService::notificarAdminNuevaCita($datosCita);

    echo json_encode(['status' => 'success', 'message' => 'Cita creada exitosamente', 'id_cita' => $id_cita]);

} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log("Error en create_cita.php: " . $e->getMessage());
    
    if ($e->getCode() == 23000) {
        echo json_encode(['status' => 'error', 'message' => 'El horario seleccionado acaba de ser ocupado. Intenta con otro.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error de base de datos.']);
    }
} catch (Throwable $e) { // 👇 AQUÍ ESTÁ LA CORRECCIÓN: Throwable atrapa errores fatales de código 👇
    if ($db->inTransaction()) $db->rollBack();
    error_log("Error crítico en create_cita.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno procesando la solicitud.']);
}