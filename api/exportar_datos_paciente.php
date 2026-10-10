<?php

/**
 * ENDPOINT: EXPORTACIÓN DE DATOS PERSONALES (DERECHO ARCO DE ACCESO)
 *
 * El paciente descarga sus datos personales y su historial completo de citas,
 * incluidas las que ocultó de su vista: el derecho de acceso cubre todo lo que
 * el sistema conserva sobre él.
 *
 * Método:    GET (enlace de descarga, sin efectos secundarios, por eso no usa CSRF)
 * Acceso:    paciente autenticado
 * Parámetro: formato = pdf (por defecto) | json
 * Respuesta: archivo PDF o JSON como descarga
 */

require_once __DIR__ . '/../config/config.php';

use App\Http\Sesion;
use App\Models\Cita;
use App\Models\Paciente;
use App\Services\PdfService;

if (!Sesion::tieneRol(Sesion::ROL_PACIENTE)) {
    http_response_code(403);
    exit('Acceso denegado.');
}

$idPaciente = (int) Sesion::usuarioId();
$paciente = (new Paciente())->obtenerPorId($idPaciente);

if ($paciente === null) {
    http_response_code(404);
    exit('Cuenta no encontrada.');
}

$citas = (new Cita())->listarPorPaciente($idPaciente, true);

// === FORMATO JSON (portabilidad) ===
if (($_GET['formato'] ?? 'pdf') === 'json') {
    unset($paciente['id_paciente'], $paciente['cuenta_eliminada']);

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="mis_datos_mediagenda.json"');
    echo json_encode([
        'datos_personales' => $paciente,
        'historial_citas'  => $citas,
        'generado_el'      => date('c'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// === FORMATO PDF ===
$html = PdfService::renderizarVista('datos_paciente', [
    'paciente'     => $paciente,
    'citas'        => $citas,
    'fechaReporte' => date('d/m/Y H:i'),
]);

PdfService::descargar($html, 'Mis_Datos_MediAgenda.pdf');
