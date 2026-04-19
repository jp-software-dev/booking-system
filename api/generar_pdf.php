<?php
/**
 * GENERADOR DE REPORTE PDF
 *
 * Script que transforma el historial de citas en un documento PDF profesional
 * para su descarga por parte del administrador. Utiliza la librería Dompdf.
 *
 * @requires session_start
 * @requires vendor/autoload.php
 * @requires config/Database.php
 * @requires admin role
 * @output application/pdf (download)
 */

session_start();

date_default_timezone_set('America/Mexico_City');

require_once '../vendor/autoload.php';
require_once '../config/Database.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// VALIDADOR DE ACCESO: Restringe la generación del reporte a usuarios administradores.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Acceso denegado.");
}

$db = Database::getInstance();

// CONSULTA DE DATOS: Recupera la información detallada de citas, pacientes y doctores para el informe.
$query = "SELECT c.id_cita, p.nombre AS paciente, p.curp, d.nombre AS doctor, d.apellido_paterno AS doctor_ap,
                 c.fecha_cita, c.hora_inicio, c.motivo_consulta, c.estado_cita
          FROM citas c
          JOIN pacientes p ON c.id_paciente = p.id_paciente
          JOIN doctores d ON c.id_doctor = d.id_doctor
          ORDER BY c.fecha_cita DESC, c.hora_inicio DESC";
$stmt = $db->query($query);
$citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// METADATOS: Captura la fecha y hora exacta de emisión del reporte.
$fecha_reporte = date('d/m/Y H:i');

// ESTRUCTURA HTML: Define la plantilla visual con estilos CSS internos para el PDF.
$html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Historial Médico</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #1a73e8; padding-bottom: 10px; }
        .header h1 { color: #1a73e8; margin: 0; font-size: 24px; }
        .header p { margin: 5px 0 0 0; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background-color: #f1f3f4; color: #333; font-weight: bold; text-align: left; padding: 10px; border: 1px solid #ddd; }
        td { padding: 10px; border: 1px solid #ddd; }
        .status-confirmada { color: green; font-weight: bold; }
        .status-cancelada { color: red; font-weight: bold; }
        .status-pendiente { color: orange; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>MediAgenda Elite</h1>
        <p>Reporte Global de Historial Médico</p>
        <p>Generado el: ' . $fecha_reporte . '</p>
    </div>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Paciente</th>
                <th>CURP</th>
                <th>Doctor</th>
                <th>Fecha y Hora</th>
                <th>Motivo</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>';

// CICLO DE DATOS: Recorre los registros e inserta cada fila en la tabla, aplicando protección XSS.
foreach ($citas as $cita) {
    $estado_clase = strtolower($cita['estado_cita']);
    $html .= '<tr>
                <td>#' . $cita['id_cita'] . '</td>
                <td>' . htmlspecialchars($cita['paciente']) . '</td>
                <td>' . htmlspecialchars($cita['curp'] ?? 'N/A') . '</td>
                <td>Dr. ' . htmlspecialchars($cita['doctor'] . ' ' . $cita['doctor_ap']) . '</td>
                <td>' . date('d/m/Y', strtotime($cita['fecha_cita'])) . ' ' . date('h:i A', strtotime($cita['hora_inicio'])) . '</td>
                <td>' . htmlspecialchars($cita['motivo_consulta'] ?? 'Sin motivo') . '</td>
                <td class="status-' . $estado_clase . '">' . $cita['estado_cita'] . '</td>
              </tr>';
}

$html .= '</tbody>
    </table>
</body>
</html>';

// CONFIGURACIÓN DOMPDF: Habilita el motor de renderizado y el soporte para contenido remoto.
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

// PROCESAMIENTO: Inicializa Dompdf, carga el HTML y establece el tamaño de página A4 horizontal.
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

// DESCARGA AUTOMÁTICA: Envía el archivo generado al navegador para forzar su descarga.
$dompdf->stream("Historial_Medico_MediAgenda.pdf", ["Attachment" => true]);
?>