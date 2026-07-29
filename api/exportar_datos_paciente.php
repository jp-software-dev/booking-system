<?php
/**
 * EXPORTACIÓN DE DATOS PERSONALES (PACIENTE) — Derecho ARCO de Acceso/Portabilidad
 *
 * Permite al paciente descargar sus datos personales + historial completo de citas
 * (todas, sin filtrar por oculto_para_paciente: el derecho de acceso cubre todo lo
 * que el sistema conserva sobre él, no solo lo que elige ver en su timeline).
 *
 * Mismo patrón de autorización que generar_pdf.php (sesión + rol, sin CSRF): es un
 * enlace GET de solo lectura sin efectos secundarios, igual que el export de admin.
 *
 * @requires session_start
 * @requires config/Database.php
 * @requires paciente role
 * @output application/pdf o application/json (download)
 */

session_start();

date_default_timezone_set('America/Mexico_City');

require_once '../config/Database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'paciente') {
    http_response_code(403);
    die("Acceso denegado.");
}

$db = Database::getInstance();
$id_paciente = $_SESSION['user_id'];

$stmtPac = $db->prepare("SELECT nombre, apellido_paterno, apellido_materno, curp, fecha_nacimiento, genero, telefono, email, recibir_notificaciones, created_at FROM pacientes WHERE id_paciente = ?");
$stmtPac->execute([$id_paciente]);
$paciente = $stmtPac->fetch(PDO::FETCH_ASSOC);

if (!$paciente) {
    http_response_code(404);
    die("Cuenta no encontrada.");
}

$stmtCitas = $db->prepare(
    "SELECT c.id_cita, c.fecha_cita, c.hora_inicio, d.nombre AS doctor, d.apellido_paterno AS doctor_ap, c.motivo_consulta, c.estado_cita, co.nombre AS consultorio_nombre
     FROM citas c
     JOIN doctores d ON c.id_doctor = d.id_doctor
     LEFT JOIN consultorios co ON c.id_consultorio = co.id_consultorio
     WHERE c.id_paciente = ?
     ORDER BY c.fecha_cita DESC, c.hora_inicio DESC"
);
$stmtCitas->execute([$id_paciente]);
$citas = $stmtCitas->fetchAll(PDO::FETCH_ASSOC);

$formato = $_GET['formato'] ?? 'pdf';

if ($formato === 'json') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="mis_datos_mediagenda.json"');
    echo json_encode([
        'datos_personales' => $paciente,
        'historial_citas' => $citas,
        'generado_el' => date('c'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$fecha_reporte = date('d/m/Y H:i');
$nombreCompleto = trim($paciente['nombre'] . ' ' . $paciente['apellido_paterno'] . ' ' . ($paciente['apellido_materno'] ?? ''));

$html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Datos Personales</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #1a73e8; padding-bottom: 10px; }
        .header h1 { color: #1a73e8; margin: 0; font-size: 24px; }
        .header p { margin: 5px 0 0 0; color: #666; }
        h2 { font-size: 15px; color: #1a73e8; margin-top: 25px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #f1f3f4; color: #333; font-weight: bold; text-align: left; padding: 8px; border: 1px solid #ddd; }
        td { padding: 8px; border: 1px solid #ddd; }
        .datos-tabla td:first-child { font-weight: bold; width: 220px; background-color: #f8f9fa; }
    </style>
</head>
<body>
    <div class="header">
        <h1>MediAgenda Elite</h1>
        <p>Exportación de datos personales (Derecho de Acceso ARCO)</p>
        <p>Generado el: ' . $fecha_reporte . '</p>
    </div>

    <h2>Datos Personales</h2>
    <table class="datos-tabla">
        <tr><td>Nombre completo</td><td>' . htmlspecialchars($nombreCompleto) . '</td></tr>
        <tr><td>CURP</td><td>' . htmlspecialchars($paciente['curp'] ?? 'N/A') . '</td></tr>
        <tr><td>Fecha de nacimiento</td><td>' . htmlspecialchars($paciente['fecha_nacimiento'] ?? 'N/A') . '</td></tr>
        <tr><td>Género</td><td>' . htmlspecialchars($paciente['genero'] ?? 'N/A') . '</td></tr>
        <tr><td>Teléfono</td><td>' . htmlspecialchars($paciente['telefono'] ?? 'N/A') . '</td></tr>
        <tr><td>Correo electrónico</td><td>' . htmlspecialchars($paciente['email']) . '</td></tr>
        <tr><td>Recibe notificaciones por correo</td><td>' . ($paciente['recibir_notificaciones'] ? 'Sí' : 'No') . '</td></tr>
        <tr><td>Cuenta creada</td><td>' . htmlspecialchars($paciente['created_at'] ?? 'N/A') . '</td></tr>
    </table>

    <h2>Historial de Citas</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha y Hora</th>
                <th>Médico</th>
                <th>Consultorio</th>
                <th>Motivo</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>';

foreach ($citas as $cita) {
    $html .= '<tr>
                <td>#' . $cita['id_cita'] . '</td>
                <td>' . date('d/m/Y', strtotime($cita['fecha_cita'])) . ' ' . date('h:i A', strtotime($cita['hora_inicio'])) . '</td>
                <td>Dr. ' . htmlspecialchars($cita['doctor'] . ' ' . $cita['doctor_ap']) . '</td>
                <td>' . htmlspecialchars($cita['consultorio_nombre'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($cita['motivo_consulta'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($cita['estado_cita']) . '</td>
              </tr>';
}

if (empty($citas)) {
    $html .= '<tr><td colspan="6" style="text-align:center;">Sin citas registradas.</td></tr>';
}

$html .= '</tbody>
    </table>
</body>
</html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream("Mis_Datos_MediAgenda.pdf", ["Attachment" => true]);
