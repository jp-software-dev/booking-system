<?php

/**
 * ENDPOINT: MÉTRICAS DEL DASHBOARD (ADMIN)
 *
 * Devuelve los indicadores y las series de las gráficas de admin_dashboard.php.
 *
 * Método:     GET
 * Acceso:     administrador
 * Parámetros: rango = 7d | 15d | 30d | 6m | 12m | custom
 *             desde, hasta (Y-m-d) solo cuando rango = custom
 * Respuesta:  { status, rango: {desde, hasta}, data: { ... } }
 */

require_once __DIR__ . '/../config/config.php';

use App\Http\Api;
use App\Http\Sesion;
use App\Models\Metrica;

Api::iniciar('GET');
Api::exigirSesion(Sesion::ROL_ADMIN);

$rango = is_string($_GET['rango'] ?? null) ? $_GET['rango'] : '30d';
if (!in_array($rango, Metrica::RANGOS, true)) {
    Api::error('Rango inválido');
}

$metrica = new Metrica();

try {
    [$desde, $hasta] = $metrica->resolverRango(
        $rango,
        is_string($_GET['desde'] ?? null) ? $_GET['desde'] : null,
        is_string($_GET['hasta'] ?? null) ? $_GET['hasta'] : null
    );
} catch (InvalidArgumentException $e) {
    Api::error($e->getMessage());
}

Api::json([
    'status' => 'success',
    'rango'  => ['desde' => $desde, 'hasta' => $hasta],
    'data'   => [
        'kpis'                     => $metrica->kpis($desde, $hasta),
        'por_estado'               => $metrica->porEstado($desde, $hasta),
        'por_especialidad'         => $metrica->porEspecialidad($desde, $hasta),
        'top_doctores'             => $metrica->topDoctores($desde, $hasta, 10),
        'tendencia_mensual'        => $metrica->tendenciaMensual(),
        'pacientes_nuevos_mensual' => $metrica->pacientesNuevosPorMes(),
        'pacientes_activos'        => $metrica->totalPacientesActivos(),
        'doctores_sin_consultorio' => $metrica->doctoresSinConsultorio(),
        'ocupacion_doctores'       => $metrica->ocupacionPorDoctor($desde, $hasta, 10),
    ],
]);
