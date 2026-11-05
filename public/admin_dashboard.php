<?php

/**
 * VISTA: DASHBOARD DE MÉTRICAS (ADMIN)
 *
 * Indicadores y gráficas (Chart.js) de la operación. Los datos se piden a
 * api/get_totales.php según el rango de fechas elegido.
 *
 * Acceso: administrador.
 */

require_once __DIR__ . '/../config/config.php';

use App\Http\Sesion;

Sesion::exigirRol(Sesion::ROL_ADMIN);

$tituloPagina = 'Métricas';
require __DIR__ . '/../src/views/layout/header.php';
?>

<style>
/* Cada gráfica vive en una caja de altura fija: sin ella, el lienzo crece con su propio contenedor. */
.ma-grafica { position: relative; height: 260px; }

/* Selector de rango de fechas: segmentos que pasan a otra línea en pantallas angostas. */
.rango-pill-group { display: flex; flex-wrap: wrap; gap: 4px; background: var(--ma-superficie-2); border: 1px solid var(--ma-linea); padding: 4px; border-radius: 14px; }
.rango-pill-group .rango-btn { border: none; border-radius: 10px; color: var(--ma-tinta-2); background: transparent; padding: 6px 12px; }
.rango-pill-group .rango-btn:hover { background: #fff; color: var(--ma-accion); }
.rango-pill-group .rango-btn.active { background: var(--ma-accion); color: #fff; }
</style>

<div class="container-xxl ma-pagina px-lg-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="ma-titulo">Métricas de la clínica</h1>
            <p class="text-secondary mb-0">Métricas operativas del sistema.</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="rango-pill-group" role="group" aria-label="Rango de fechas">
                <button type="button" class="btn btn-sm btn-outline-primary rango-btn" data-rango="7d">7 días</button>
                <button type="button" class="btn btn-sm btn-outline-primary rango-btn" data-rango="15d">15 días</button>
                <button type="button" class="btn btn-sm btn-outline-primary rango-btn active" data-rango="30d">30 días</button>
                <button type="button" class="btn btn-sm btn-outline-primary rango-btn" data-rango="6m">6 meses</button>
                <button type="button" class="btn btn-sm btn-outline-primary rango-btn" data-rango="12m">12 meses</button>
                <button type="button" class="btn btn-sm btn-outline-primary rango-btn" data-rango="custom">Personalizado</button>
            </div>
            <div id="customRangoInputs" class="d-none align-items-center gap-2 flex-wrap">
                <input type="date" id="fechaDesde" class="form-control form-control-sm">
                <span class="text-secondary small">a</span>
                <input type="date" id="fechaHasta" class="form-control form-control-sm">
                <button type="button" id="btnAplicarCustom" class="btn btn-sm btn-primary">Aplicar</button>
            </div>
        </div>
    </div>

    <div id="dashboardAlert"></div>
    <div id="alertDoctoresSinConsultorio"></div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                <span class="text-secondary small fw-bold">Citas en total</span>
                <span class="fs-3 fw-bold text-dark-blue" id="kpiTotal">—</span>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                <span class="text-secondary small fw-bold">Completadas</span>
                <span class="fs-3 fw-bold text-success" id="kpiCompletadas">—</span>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                <span class="text-secondary small fw-bold">Canceladas</span>
                <span class="fs-3 fw-bold text-danger" id="kpiCanceladas">—</span>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                <span class="text-secondary small fw-bold">Tasa de cancelación</span>
                <span class="fs-3 fw-bold text-dark-blue" id="kpiTasa">—</span>
            </div>
        </div>
        <div class="col-12 col-lg">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                <span class="text-secondary small fw-bold">Pacientes activos</span>
                <span class="fs-3 fw-bold text-dark-blue" id="kpiPacientesActivos">—</span>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                <h2 class="fs-6 fw-bold text-dark-blue mb-3">Pacientes nuevos por mes (últimos 12 meses)</h2>
                <div class="ma-grafica"><canvas id="chartPacientesNuevos"></canvas></div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                <h2 class="fs-6 fw-bold text-dark-blue mb-3">Ocupación por médico (rango seleccionado)</h2>
                <div class="ma-grafica"><canvas id="chartOcupacion"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                <h2 class="fs-6 fw-bold text-dark-blue mb-3">Citas por estado</h2>
                <div class="ma-grafica"><canvas id="chartEstado"></canvas></div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                <h2 class="fs-6 fw-bold text-dark-blue mb-3">Citas por especialidad</h2>
                <div class="ma-grafica"><canvas id="chartEspecialidad"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                <h2 class="fs-6 fw-bold text-dark-blue mb-3">Médicos con más citas</h2>
                <div class="ma-grafica"><canvas id="chartDoctores"></canvas></div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                <h2 class="fs-6 fw-bold text-dark-blue mb-3">Tendencia mensual (últimos 12 meses)</h2>
                <div class="ma-grafica"><canvas id="chartTendencia"></canvas></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
<script>
// Plugin ligero: dibuja el total centrado dentro de la dona (número grande + etiqueta).
// Se registra solo en la instancia de chartEstado (no globalmente) para no afectar otras gráficas.
const centerTextPlugin = {
    id: 'centerText',
    beforeDraw(chart) {
        const { ctx, chartArea: { left, right, top, bottom } } = chart;
        const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
        const cx = (left + right) / 2, cy = (top + bottom) / 2;
        ctx.save();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillStyle = '#1e293b';
        ctx.font = '700 28px system-ui, sans-serif';
        ctx.fillText(total, cx, cy - 10);
        ctx.fillStyle = '#94a3b8';
        ctx.font = '600 12px system-ui, sans-serif';
        ctx.fillText('CITAS', cx, cy + 14);
        ctx.restore();
    }
};

function gradienteAzul(ctx) {
    const g = ctx.createLinearGradient(0, 0, 0, 240);
    g.addColorStop(0, 'rgba(26,115,232,0.35)');
    g.addColorStop(1, 'rgba(26,115,232,0)');
    return g;
}

const AZUL_OSCURO = '#1a56db', AZUL_CLARO = '#5b9bf7';
function tonosAzules(n) {
    return Array.from({ length: n }, (_, i) => i % 2 === 0 ? AZUL_OSCURO : AZUL_CLARO);
}

let chartEstado, chartEspecialidad, chartDoctores, chartTendencia, chartPacientesNuevos, chartOcupacion;
let rangoActual = '30d';

/** Muestra un error sobre las gráficas (como texto, no como HTML). */
function mostrarErrorDashboard(mensaje) {
    mostrarAlerta('dashboardAlert', 'danger', mensaje);
}

/** Destruye las gráficas anteriores antes de dibujar las del nuevo rango. */
function destruirCharts() {
    [chartEstado, chartEspecialidad, chartDoctores, chartTendencia, chartPacientesNuevos, chartOcupacion].forEach(c => c && c.destroy());
}

/** Pide las métricas del rango y redibuja indicadores y gráficas. */
async function cargarDashboard(params) {
    limpiarAlerta('dashboardAlert');
    const data = await apiClient.get('get_totales.php?' + new URLSearchParams(params).toString());

    if (!data || data.status !== 'success') {
        mostrarErrorDashboard(data ? data.message : 'No se pudieron cargar las métricas');
        return;
    }

    const m = data.data;

    document.getElementById('kpiTotal').textContent = m.kpis.total;
    document.getElementById('kpiCompletadas').textContent = m.kpis.completadas;
    document.getElementById('kpiCanceladas').textContent = m.kpis.canceladas;
    document.getElementById('kpiTasa').textContent = m.kpis.tasa_cancelacion + '%';
    document.getElementById('kpiPacientesActivos').textContent = m.pacientes_activos;

    // Aviso de médicos sin consultorio. Los nombres se insertan como texto para evitar inyección de HTML.
    if (m.doctores_sin_consultorio.length > 0) {
        const nombres = m.doctores_sin_consultorio.map((d) => `Dr. ${d.nombre} ${d.apellido_paterno}`).join(', ');
        mostrarAlerta('alertDoctoresSinConsultorio', 'warning', `${m.doctores_sin_consultorio.length} doctor(es) sin consultorio asignado: ${nombres}`);
    } else {
        limpiarAlerta('alertDoctoresSinConsultorio');
    }

    destruirCharts();

    chartEstado = new Chart(document.getElementById('chartEstado'), {
        type: 'doughnut',
        plugins: [centerTextPlugin],
        data: {
            labels: m.por_estado.map(r => r.estado_cita),
            datasets: [{
                data: m.por_estado.map(r => r.cantidad),
                backgroundColor: ['#f6c343', '#4dabf7', '#845ef7', '#40c057', '#fa5252'],
                borderWidth: 2, borderColor: '#fff', hoverOffset: 6
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '72%',
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 16 } },
                datalabels: {
                    color: '#fff', font: { weight: '700', size: 11 },
                    formatter: (value, ctx) => {
                        const total = ctx.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                        return total > 0 ? Math.round(value / total * 100) + '%' : '';
                    },
                    display: (ctx) => ctx.dataset.data[ctx.dataIndex] > 0
                }
            }
        }
    });

    chartEspecialidad = new Chart(document.getElementById('chartEspecialidad'), {
        type: 'bar',
        data: {
            labels: m.por_especialidad.map(r => r.nombre_especialidad),
            datasets: [{ label: 'Citas', data: m.por_especialidad.map(r => r.cantidad), backgroundColor: tonosAzules(m.por_especialidad.length), borderRadius: 8, maxBarThickness: 42 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, datalabels: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(0,0,0,0.05)' } }
            }
        }
    });

    chartDoctores = new Chart(document.getElementById('chartDoctores'), {
        type: 'bar',
        data: {
            labels: m.top_doctores.map(r => 'Dr. ' + r.nombre + ' ' + r.apellido_paterno),
            datasets: [{ label: 'Citas atendidas', data: m.top_doctores.map(r => r.cantidad), backgroundColor: tonosAzules(m.top_doctores.length), borderRadius: 8, maxBarThickness: 28 }]
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, datalabels: { display: false } },
            scales: {
                x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(0,0,0,0.05)' } },
                y: { grid: { display: false } }
            }
        }
    });

    chartTendencia = new Chart(document.getElementById('chartTendencia'), {
        type: 'line',
        data: {
            labels: m.tendencia_mensual.map(r => r.periodo),
            datasets: [{
                label: 'Citas por mes', data: m.tendencia_mensual.map(r => r.cantidad),
                borderColor: '#1a56db', backgroundColor: (ctx) => gradienteAzul(ctx.chart.ctx),
                fill: true, tension: 0.4, borderWidth: 2.5,
                pointRadius: 0, pointHoverRadius: 5, pointHoverBackgroundColor: '#1a56db', pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false, interaction: { intersect: false, mode: 'index' },
            plugins: { legend: { display: false }, datalabels: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(0,0,0,0.05)' } }
            }
        }
    });

    chartPacientesNuevos = new Chart(document.getElementById('chartPacientesNuevos'), {
        type: 'bar',
        data: {
            labels: m.pacientes_nuevos_mensual.map(r => r.periodo),
            datasets: [{ label: 'Pacientes nuevos', data: m.pacientes_nuevos_mensual.map(r => r.cantidad), backgroundColor: '#845ef7', borderRadius: 8, maxBarThickness: 32 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, datalabels: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(0,0,0,0.05)' } }
            }
        }
    });

    chartOcupacion = new Chart(document.getElementById('chartOcupacion'), {
        type: 'bar',
        data: {
            labels: m.ocupacion_doctores.map(r => 'Dr. ' + r.nombre + ' ' + r.apellido_paterno),
            datasets: [{ label: 'Tasa de ocupación (%)', data: m.ocupacion_doctores.map(r => r.tasa_ocupacion), backgroundColor: '#f6c343', borderRadius: 8, maxBarThickness: 28 }]
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, datalabels: { display: false } },
            scales: {
                x: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' }, grid: { color: 'rgba(0,0,0,0.05)' } },
                y: { grid: { display: false } }
            }
        }
    });
}

document.querySelectorAll('.rango-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const rango = btn.dataset.rango;
        document.querySelectorAll('.rango-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('customRangoInputs').classList.toggle('d-none', rango !== 'custom');
        document.getElementById('customRangoInputs').classList.toggle('d-flex', rango === 'custom');
        if (rango !== 'custom') {
            rangoActual = rango;
            cargarDashboard({ rango });
        }
    });
});

document.getElementById('btnAplicarCustom').addEventListener('click', () => {
    const desde = document.getElementById('fechaDesde').value;
    const hasta = document.getElementById('fechaHasta').value;
    if (!desde || !hasta) { mostrarErrorDashboard('Selecciona ambas fechas'); return; }
    cargarDashboard({ rango: 'custom', desde, hasta });
});

cargarDashboard({ rango: rangoActual });
</script>

<?php require __DIR__ . '/../src/views/layout/footer.php'; ?>
