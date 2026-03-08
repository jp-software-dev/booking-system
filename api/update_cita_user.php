<?php
// ENDPOINT REPROGRAMACIÓN PACIENTE: Archivo API que permite a un paciente modificar su propia cita y devuelve el estado a 'Pendiente' para revisión.

// INICIALIZACIÓN DE SESIÓN: Reanuda la sesión activa para poder identificar de forma segura qué paciente está haciendo la solicitud.
session_start();

// DEPENDENCIAS: Carga la configuración centralizada de la base de datos y la clase para el envío de correos (Mailer).
require_once '../config/Database.php';
require_once '../src/Helpers/Mailer.php';

// FORMATO DE RESPUESTA: Configura la cabecera HTTP para que el frontend procese la respuesta estructurada en formato JSON.
header('Content-Type: application/json');

// VALIDADOR DE MÉTODO: Protege el archivo asegurando que solo reciba peticiones por POST desde el formulario del portal del paciente.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

// CONTROL DE ACCESO: Bloquea la ejecución si el usuario no ha iniciado sesión o si no tiene estrictamente el rol de 'paciente'.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'paciente') {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

// LECTOR DE DATOS: Captura y decodifica el payload en formato JSON enviado por la petición fetch desde el frontend.
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['status' => 'error', 'message' => 'Datos inválidos']);
    exit;
}

// ASIGNACIÓN DE VARIABLES: Extrae los datos de la cita y el nuevo horario proporcionado, asignando valores por defecto para evitar errores.
$id_cita = $input['id_cita'] ?? 0;
$fecha = $input['fecha_cita'] ?? '';
$hora = $input['hora_inicio'] ?? '';
$motivo = $input['motivo'] ?? '';

// VALIDADOR DE CAMPOS: Verifica que la información obligatoria (ID, fecha y hora) esté presente antes de procesar la solicitud.
if (!$id_cita || !$fecha || !$hora) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

// VALIDADOR DE FECHA: Asegura mediante la clase DateTime que la nueva fecha cumpla estrictamente con el formato Año-Mes-Día (YYYY-MM-DD).
if (!DateTime::createFromFormat('Y-m-d', $fecha)) {
    echo json_encode(['status' => 'error', 'message' => 'Fecha inválida']);
    exit;
}

// VALIDADOR DE HORA: Confirma que la cadena de tiempo proporcionada respete el formato estricto de 24 horas (HH:MM).
if (!DateTime::createFromFormat('H:i', $hora)) {
    echo json_encode(['status' => 'error', 'message' => 'Hora inválida (use HH:MM)']);
    exit;
}

// CONEXIÓN DB: Llama a la instancia activa y única de la base de datos utilizando el patrón de diseño Singleton.
$db = Database::getInstance();

try {
    // PROTECCIÓN DE PROPIEDAD: Consulta la base de datos para garantizar que la cita que se intenta modificar realmente pertenezca al paciente autenticado.
    $check = $db->prepare("SELECT id_cita, id_doctor, estado_cita FROM citas WHERE id_cita = ? AND id_paciente = ?");
    $check->execute([$id_cita, $_SESSION['user_id']]);
    $cita = $check->fetch();
    
    if (!$cita) {
        echo json_encode(['status' => 'error', 'message' => 'Cita no encontrada o no pertenece al paciente']);
        exit;
    }

    // RESTRICCIÓN DE ESTADO: Evita que el paciente altere citas que ya forman parte del historial médico cerrado (Canceladas o Completadas).
    if ($cita['estado_cita'] === 'Cancelada' || $cita['estado_cita'] === 'Completada') {
        echo json_encode(['status' => 'error', 'message' => 'No se puede modificar una cita cancelada o completada']);
        exit;
    }

    // PREVENCIÓN DE EMPALMES: Verifica que el nuevo horario esté libre para ese doctor, excluyendo la cita actual para permitir cambiar solo el motivo.
    $checkDisponible = $db->prepare("SELECT id_cita FROM citas WHERE id_doctor = ? AND fecha_cita = ? AND hora_inicio = ? AND estado_cita != 'Cancelada' AND id_cita != ?");
    $checkDisponible->execute([$cita['id_doctor'], $fecha, $hora, $id_cita]);
    if ($checkDisponible->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'El horario seleccionado ya no está disponible']);
        exit;
    }

    // ACTUALIZACIÓN DE DATOS: Aplica los cambios en la base de datos y fuerza el estado a 'Pendiente' para que el administrador la vuelva a confirmar.
    $update = $db->prepare("UPDATE citas SET fecha_cita = ?, hora_inicio = ?, motivo_consulta = ?, estado_cita = 'Pendiente' WHERE id_cita = ?");
    $update->execute([$fecha, $hora, $motivo, $id_cita]);

    // RESPUESTA EXITOSA: Devuelve la confirmación al panel del paciente para que cierre el modal y actualice su vista de citas.
    echo json_encode(['status' => 'success', 'message' => 'Cita actualizada correctamente']);
    
} catch (PDOException $e) {
    // MANEJO DE CONCURRENCIA: Captura errores SQL específicos (como empalmes de último milisegundo) para avisar al paciente que le ganaron el horario.
    if ($e->getCode() == 23000) {
        echo json_encode(['status' => 'error', 'message' => 'El horario seleccionado acaba de ser ocupado por otro paciente. Intenta con otro.']);
    } else {
        error_log("Error en update_cita_user.php: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Error de base de datos']);
    }
} catch (Exception $e) {
    // MANEJO DE ERRORES: Registra fallos inesperados del servidor en los logs de manera silenciosa y devuelve un error genérico de seguridad.
    error_log("Error en update_cita_user.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor']);
}
?>