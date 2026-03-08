<?php
// ENDPOINT REPROGRAMACIÓN: Archivo API que permite a los administradores modificar la fecha y hora de cualquier cita existente evitando empalmes.

// INICIALIZACIÓN DE SESIÓN: Reanuda la sesión activa para poder verificar los privilegios del usuario actual en el servidor.
session_start();

// DEPENDENCIAS: Carga la configuración centralizada de la base de datos para poder ejecutar las consultas SQL.
require_once '../config/Database.php';

// FORMATO DE RESPUESTA: Configura la cabecera HTTP para que el frontend procese la respuesta estructurada en formato JSON.
header('Content-Type: application/json');

// VALIDADOR DE MÉTODO: Bloquea las peticiones GET para evitar accesos directos por URL y obligar al uso del formulario.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

// CONTROL DE ACCESO: Restringe la ejecución del script exclusivamente a usuarios autenticados que posean privilegios de administrador.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

// LECTOR DE DATOS: Captura y decodifica el payload en formato JSON enviado por la petición fetch desde el panel de administración.
$input = json_decode(file_get_contents('php://input'), true);
$id_cita = $input['id_cita'] ?? 0;
$fecha = $input['fecha_cita'] ?? '';
$hora = $input['hora_inicio'] ?? '';

// VALIDADOR DE CAMPOS: Verifica que los parámetros esenciales para la reprogramación no estén vacíos ni sean nulos.
if (!$id_cita || !$fecha || !$hora) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

// VALIDADOR DE FECHA: Asegura mediante la clase DateTime que la nueva fecha cumpla estrictamente con el formato Año-Mes-Día.
if (!DateTime::createFromFormat('Y-m-d', $fecha)) {
    echo json_encode(['status' => 'error', 'message' => 'Fecha inválida']);
    exit;
}

// VALIDADOR DE HORA: Confirma que la cadena de tiempo proporcionada respete el formato estricto de 24 horas (HH:MM).
if (!DateTime::createFromFormat('H:i', $hora)) {
    echo json_encode(['status' => 'error', 'message' => 'Hora inválida (use HH:MM)']);
    exit;
}

// CONEXIÓN DB: Obtiene la instancia activa y única de la base de datos utilizando el patrón de diseño Singleton.
$db = Database::getInstance();

try {
    // EXTRACCIÓN DE DOCTOR: Consulta la base de datos para identificar a qué médico pertenece la cita que se va a modificar.
    $stmt = $db->prepare("SELECT id_doctor FROM citas WHERE id_cita = ?");
    $stmt->execute([$id_cita]);
    $cita = $stmt->fetch();
    
    // PROTECCIÓN DE INTEGRIDAD: Corta la ejecución si alguien intenta modificar un ID de cita que ya no existe o fue eliminado.
    if (!$cita) {
        echo json_encode(['status' => 'error', 'message' => 'Cita no encontrada']);
        exit;
    }
    $id_doctor = $cita['id_doctor'];

    // PREVENCIÓN DE EMPALMES: Busca si el doctor ya tiene otra cita activa en ese mismo horario, excluyendo astutamente la cita actual que estamos moviendo.
    $check = $db->prepare("SELECT id_cita FROM citas WHERE id_doctor = ? AND fecha_cita = ? AND hora_inicio = ? AND estado_cita != 'Cancelada' AND id_cita != ?");
    $check->execute([$id_doctor, $fecha, $hora, $id_cita]);
    if ($check->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'El horario seleccionado ya no está disponible']);
        exit;
    }

    // REPROGRAMACIÓN: Ejecuta la sentencia SQL definitiva para sobreescribir la fecha y hora de la cita con los nuevos valores validados.
    $update = $db->prepare("UPDATE citas SET fecha_cita = ?, hora_inicio = ? WHERE id_cita = ?");
    $update->execute([$fecha, $hora, $id_cita]);

    // RESPUESTA EXITOSA: Devuelve la confirmación al panel administrativo para que cierre el modal y actualice la tabla visualmente.
    echo json_encode(['status' => 'success', 'message' => 'Cita actualizada correctamente']);
    
} catch (Exception $e) {
    // MANEJO DE ERRORES: Captura fallos de la base de datos, los registra de forma silenciosa en los logs del servidor y alerta al administrador.
    error_log("Error en update_cita_admin.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor']);
}
?>