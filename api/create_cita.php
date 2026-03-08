<?php
// Script para agendar citas, validar datos, evitar empalmes y enviar notificaciones.
session_start();
require_once '../config/Database.php';
require_once '../src/Helpers/Mailer.php';
header('Content-Type: application/json');

// Bloquea peticiones que no sean POST para proteger el acceso directo al archivo.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

// Verifica que el usuario tenga una sesión activa para permitir el registro.
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

// Captura y decodifica el cuerpo de la petición JSON enviada desde el frontend.
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

// Valida que todos los parámetros obligatorios para la cita estén presentes.
if (!$id_doctor || !$fecha || !$hora || !$email) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

// Valida que la fecha recibida cumpla estrictamente con el formato Año-Mes-Día.
if (!DateTime::createFromFormat('Y-m-d', $fecha)) {
    echo json_encode(['status' => 'error', 'message' => 'Fecha inválida']);
    exit;
}

// Valida que la hora recibida cumpla estrictamente con el formato de 24 horas.
if (!DateTime::createFromFormat('H:i', $hora)) {
    echo json_encode(['status' => 'error', 'message' => 'Hora inválida (use HH:MM)']);
    exit;
}

// Verifica mediante un filtro nativo que la cadena del correo sea una dirección válida.
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Correo electrónico no válido']);
    exit;
}

// Obtiene la instancia única de conexión a la base de datos (Singleton).
$db = Database::getInstance();
try {
    $db->beginTransaction();

    $id_paciente = null;

    if ($_SESSION['role'] === 'admin') {
        // Busca paciente existente por email o crea un registro temporal si no existe.
        $stmt = $db->prepare("SELECT id_paciente FROM pacientes WHERE email = ?");
        $stmt->execute([$email]);
        $pacienteExistente = $stmt->fetch();
        
        if ($pacienteExistente) {
            $id_paciente = $pacienteExistente['id_paciente'];
        } else {
            $curp_temp = 'ADMIN' . time() . rand(100, 999);
            // Inserta datos mínimos del paciente cuando el administrador agenda la cita.
            $insert = $db->prepare("INSERT INTO pacientes (curp, nombre, apellido_paterno, apellido_materno, edad, genero, email) 
                                    VALUES (?, 'Admin', 'Temp', '', 0, 'Otro', ?)");
            $insert->execute([$curp_temp, $email]);
            $id_paciente = $db->lastInsertId();
        }
    } else {
        // Asigna el ID del usuario actual y actualiza su correo en su perfil.
        $id_paciente = $_SESSION['user_id'];
        $checkEmail = $db->prepare("SELECT id_paciente FROM pacientes WHERE email = ? AND id_paciente != ?");
        $checkEmail->execute([$email, $id_paciente]);
        if ($checkEmail->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'El correo ya está registrado por otro paciente']);
            exit;
        }
        // Actualiza la información de contacto del paciente autenticado.
        $updatePaciente = $db->prepare("UPDATE pacientes SET email = ? WHERE id_paciente = ?");
        $updatePaciente->execute([$email, $id_paciente]);
    }

    // Consulta si el doctor ya tiene una cita ocupada en la misma fecha y hora seleccionada.
    $checkDisponible = $db->prepare("SELECT id_cita FROM citas WHERE id_doctor = ? AND fecha_cita = ? AND hora_inicio = ? AND estado_cita != 'Cancelada'");
    $checkDisponible->execute([$id_doctor, $fecha, $hora]);
    if ($checkDisponible->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'El horario ya no está disponible']);
        exit;
    }

    // Inserta el nuevo registro de la cita con estado inicial 'Pendiente'.
    $query = "INSERT INTO citas (id_paciente, id_doctor, fecha_cita, hora_inicio, motivo_consulta, estado_cita) 
              VALUES (?, ?, ?, ?, ?, 'Pendiente')";
    $stmt = $db->prepare($query);
    $stmt->execute([$id_paciente, $id_doctor, $fecha, $hora, $motivo]);
    $id_cita = $db->lastInsertId();

    $db->commit();

    // Recupera la información del paciente y doctor para personalizar las notificaciones.
    $paciente = $db->prepare("SELECT nombre, email FROM pacientes WHERE id_paciente = ?");
    $paciente->execute([$id_paciente]);
    $pac = $paciente->fetch();

    $doc = $db->prepare("SELECT nombre, apellido_paterno FROM doctores WHERE id_doctor = ?");
    $doc->execute([$id_doctor]);
    $doctor = $doc->fetch();

    // Envía una notificación por correo electrónico al paciente confirmando la solicitud.
    $asuntoPaciente = "Cita agendada - Pendiente de confirmación";
    $mensajePaciente = "Hola {$pac['nombre']},\n\nTu cita con el Dr. {$doctor['nombre']} {$doctor['apellido_paterno']} para el día {$fecha} a las {$hora} ha sido agendada exitosamente.\n\nEstá pendiente de confirmación. Te notificaremos pronto.";
    Mailer::enviarCorreo($pac['email'], $asuntoPaciente, $mensajePaciente);

    // Envía una notificación al correo administrativo del sistema para su revisión.
    $adminEmail = 'mediagenda.sistema@gmail.com'; 
    $asuntoAdmin = "Nueva cita agendada";
    $mensajeAdmin = "Se ha agendado una nueva cita:\nPaciente: {$pac['nombre']}\nCorreo: {$pac['email']}\nDoctor: Dr. {$doctor['nombre']} {$doctor['apellido_paterno']}\nFecha: $fecha\nHora: $hora\nMotivo: $motivo";
    Mailer::enviarCorreo($adminEmail, $asuntoAdmin, $mensajeAdmin);

    echo json_encode(['status' => 'success', 'message' => 'Cita creada exitosamente', 'id_cita' => $id_cita]);
} catch (PDOException $e) {
    // Revierte la transacción en caso de error en la base de datos y registra el fallo.
    if ($db->inTransaction()) $db->rollBack();
    if ($e->getCode() == 23000) {
        echo json_encode(['status' => 'error', 'message' => 'El horario seleccionado acaba de ser ocupado. Intenta con otro.']);
    } else {
        error_log("Error en create_cita.php: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Error de base de datos.']);
    }
} catch (Exception $e) {
    // Captura cualquier otro error de ejecución y registra el mensaje en el log del servidor.
    if ($db->inTransaction()) $db->rollBack();
    error_log("Error en create_cita.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno.']);
}
?>