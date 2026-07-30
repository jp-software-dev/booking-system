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

// Colchón adicional de memoria para dompdf, complementario al límite duro de
// filas de abajo (no sustituye a ese límite: con volumen real, dompdf agota
// memoria mucho antes de acercarse a este techo).
ini_set('memory_limit', '512M');

// Límite duro de filas por exportación: dompdf es intrínsecamente pesado por
// fila (Cellmap/Style/FrameReflower) y con miles de filas agota memoria sin
// importar qué tan liviano sea el HTML. En vez de intentar renderizar todo,
// se pide acotar los filtros.
const LIMITE_FILAS_PDF = 1000;

require_once '../vendor/autoload.php';
require_once '../config/Database.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// VALIDADOR DE ACCESO: Restringe la generación del reporte a usuarios administradores.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Acceso denegado.");
}

$db = Database::getInstance();

// --- FILTROS DINÁMICOS ---
// admin_historial.php envía id_cita/paciente/doctor/estado y admin.php envía
// search_id/search_nombre/filtro_doctor/filtro_estado (nombres distintos para
// el mismo propósito). Se aceptan ambos conjuntos para que el botón "PDF"
// de las dos pantallas respete siempre los filtros activos.
$busqId     = trim($_GET['id_cita']  ?? $_GET['search_id']     ?? '');
$busqPac    = trim($_GET['paciente'] ?? $_GET['search_nombre'] ?? '');
$busqDoc    = trim($_GET['doctor']   ?? $_GET['filtro_doctor'] ?? '');
$busqEstado = trim($_GET['estado']   ?? $_GET['filtro_estado'] ?? '');
$origen     = trim($_GET['origen'] ?? '');

$where = [];
$params = [];
$filtrosAplicados = [];

// admin_historial.php solo muestra citas Completada/Cancelada (es su alcance
// de pantalla, no un filtro que el usuario elige) — el PDF generado desde ahí
// debe respetar ese mismo alcance aunque no se haya seleccionado un estado.
if ($origen === 'historial') {
    $where[] = "c.estado_cita IN ('Completada', 'Cancelada')";
}

if ($busqId !== '') {
    $where[]  = "c.id_cita = ?";
    $params[] = (int)$busqId;
    $filtrosAplicados[] = "Cita #" . (int)$busqId;
}
if ($busqPac !== '') {
    $where[]  = "(p.nombre LIKE ? OR p.apellido_paterno LIKE ?)";
    $params[] = "%$busqPac%";
    $params[] = "%$busqPac%";
    $filtrosAplicados[] = "Paciente = " . $busqPac;
}
if ($busqDoc !== '') {
    $where[]  = "c.id_doctor = ?";
    $params[] = (int)$busqDoc;
    $stmtDoc = $db->prepare("SELECT nombre, apellido_paterno FROM doctores WHERE id_doctor = ?");
    $stmtDoc->execute([(int)$busqDoc]);
    $doc = $stmtDoc->fetch(PDO::FETCH_ASSOC);
    $filtrosAplicados[] = "Médico = " . ($doc ? 'Dr. ' . $doc['nombre'] . ' ' . $doc['apellido_paterno'] : "#$busqDoc");
}
if ($busqEstado !== '') {
    $where[]  = "c.estado_cita = ?";
    $params[] = $busqEstado;
    $filtrosAplicados[] = "Estado = " . $busqEstado;
}

$whereSql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
$subtitulo = count($filtrosAplicados) > 0
    ? "Reporte filtrado: " . implode(", ", $filtrosAplicados)
    : "Reporte Global de Historial Médico";

// LÍMITE DURO DE FILAS: se cuenta antes de traer los datos completos, para no
// pagar el costo de la consulta ancha si de todas formas se va a rechazar.
$countStmt = $db->prepare("SELECT COUNT(*) FROM citas c JOIN pacientes p ON c.id_paciente = p.id_paciente JOIN doctores d ON c.id_doctor = d.id_doctor $whereSql");
$countStmt->execute($params);
$totalFilas = (int)$countStmt->fetchColumn();

if ($totalFilas > LIMITE_FILAS_PDF) {
    http_response_code(413);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Reporte demasiado grande</title>
    <style>body{font-family:Helvetica,Arial,sans-serif;max-width:640px;margin:60px auto;color:#333;text-align:center;}
    h1{color:#dc3545;font-size:20px;}p{color:#555;line-height:1.5;}
    a{display:inline-block;margin-top:16px;color:#1a73e8;text-decoration:none;font-weight:bold;}</style></head><body>
    <h1>El reporte es demasiado grande para exportar</h1>
    <p>Hay <strong>' . number_format($totalFilas) . '</strong> citas que coinciden con estos filtros, y el límite por exportación en PDF es de <strong>' . number_format(LIMITE_FILAS_PDF) . '</strong> filas.</p>
    <p>Aplica un filtro (paciente, médico, estado o ID de cita) para reducir el resultado antes de exportar.</p>
    <a href="javascript:history.back()">&laquo; Volver y ajustar filtros</a>
    </body></html>';
    exit;
}

// CONSULTA DE DATOS: Recupera la información detallada de citas, pacientes y doctores para el informe,
// respetando los filtros activos (si no se aplicó ninguno, trae todas las citas, igual que antes).
$query = "SELECT c.id_cita, p.nombre AS paciente, p.curp, d.nombre AS doctor, d.apellido_paterno AS doctor_ap,
                 c.fecha_cita, c.hora_inicio, c.motivo_consulta, c.estado_cita
          FROM citas c
          JOIN pacientes p ON c.id_paciente = p.id_paciente
          JOIN doctores d ON c.id_doctor = d.id_doctor
          $whereSql
          ORDER BY c.fecha_cita DESC, c.hora_inicio DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
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
        <p>' . htmlspecialchars($subtitulo) . '</p>
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

// CONFIGURACIÓN DOMPDF: Habilita el motor de renderizado. El contenido remoto queda deshabilitado
// porque todo el contenido del reporte proviene de la base de datos, no de recursos externos.
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);

// PROCESAMIENTO: Inicializa Dompdf, carga el HTML y establece el tamaño de página A4 horizontal.
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

// DESCARGA AUTOMÁTICA: Envía el archivo generado al navegador para forzar su descarga.
$dompdf->stream("Historial_Medico_MediAgenda.pdf", ["Attachment" => true]);
?>