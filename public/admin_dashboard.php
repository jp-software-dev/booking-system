<?php
require_once __DIR__ . '/../config/config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: login.php"); exit(); }
require_once __DIR__ . '/../src/views/layout/header.php';
?>

<style>
.rango-pill-group { background: #f1f5f9; padding: 4px; border-radius: 999px; }
.rango-pill-group .rango-btn { border: none !important; border-radius: 999px !important; color: #475569; background: transparent; padding: 6px 14px; }
.rango-pill-group .rango-btn:hover { background: rgba(26,86,219,0.08); color: #1a56db; }
.rango-pill-group .rango-btn.active { background: #1a56db; color: #fff; box-shadow: 0 1px 3px rgba(26,86,219,0.35); }
</style>

<div class="container-fluid py-4 px-lg-5" style="min-height: 85vh;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold text-dark-blue mb-1">Dashboard</h2>
            <p class="text-secondary mb-0">Métricas operativas del sistema.</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="btn-group rango-pill-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-primary rango-btn" data-rango="7d">7 días</button>
                <button type="button" class="btn btn-sm btn-outline-primary rango-btn" data-rango="15d">15 días</button>
                <button type="button" class="btn btn-sm btn-outline-primary rango-btn active" data-rango="30d">30 días</button>
                <button type="button" class="btn btn-sm btn-outline-primary rango-btn" data-rango="6m">6 meses</button>
                <button type="button" class="btn btn-sm btn-outline-primary rango-btn" data-rango="12m">12 meses</button>
                <button type="button" class="btn btn-sm btn-outline-primary rango-btn" data-rango="custom">Personalizado</button>
            </div>
            <div id="customRangoInputs" class="d-none d-flex align-items-center gap-2">
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
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                <span class="text-secondary small fw-bold text-uppercase">Total Citas</span>
                <span class="fs-3 fw-bold text-dark-blue" id="kpiTotal">—</span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                <span class="text-secondary small fw-bold text-uppercase">Completadas</span>
                <span class="fs-3 fw-bold text-success" id="kpiCompletadas">—</span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                <span class="text-secondary small fw-bold text-uppercase">Canceladas</span>
                <span class="fs-3 fw-bold text-danger" id="kpiCanceladas">—</span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                <span class="text-secondary small fw-bold text-uppercase">Tasa de Cancelación</span>
                <span class="fs-3 fw-bold text-dark-blue" id="kpiTasa">—</span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                <span class="text-secondary small fw-bold text-uppercase">Pacientes Activos</span>
                <span class="fs-3 fw-bold text-dark-blue" id="kpiPacientesActivos">—</span>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                <h6 class="fw-bold text-dark-blue mb-3">Pacientes Nuevos por Mes (últimos 12 meses)</h6>
                <canvas id="chartPacientesNuevos" height="220"></canvas>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                <h6 class="fw-bold text-dark-blue mb-3">Ocupación por Doctor (rango seleccionado)</h6>
                <canvas id="chartOcupacion" height="220"></canvas>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                <h6 class="fw-bold text-dark-blue mb-3">Citas por Estado</h6>
                <canvas id="chartEstado" height="220"></canvas>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                <h6 class="fw-bold text-dark-blue mb-3">Citas por Especialidad</h6>
                <canvas id="chartEspecialidad" height="220"></canvas>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                <h6 class="fw-bold text-dark-blue mb-3">Top Doctores</h6>
                <canvas id="chartDoctores" height="240"></canvas>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                <h6 class="fw-bold text-dark-blue mb-3">Tendencia Mensual (últimos 12 meses)</h6>
                <canvas id="chartTendencia" height="240"></canvas>
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

function mostrarErrorDashboard(msg) {
    document.getElementById('dashboardAlert').innerHTML = `<div class="alert alert-danger py-2 small">${msg}</div>`;
}

function destruirCharts() {
    [chartEstado, chartEspecialidad, chartDoctores, chartTendencia, chartPacientesNuevos, chartOcupacion].forEach(c => c && c.destroy());
}

async function cargarDashboard(params) {
    document.getElementById('dashboardAlert').innerHTML = '';
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

    const alertDoctores = document.getElementById('alertDoctoresSinConsultorio');
    if (m.doctores_sin_consultorio.length > 0) {
        const nombres = m.doctores_sin_consultorio.map(d => 'Dr. ' + d.nombre + ' ' + d.apellido_paterno).join(', ');
        alertDoctores.innerHTML = `<div class="alert alert-warning py-2 small mb-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><strong>${m.doctores_sin_consultorio.length} doctor(es) sin consultorio asignado:</strong> ${nombres}</div>`;
    } else {
        alertDoctores.innerHTML = '';
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
            responsive: true, cutout: '72%',
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
            responsive: true, plugins: { legend: { display: false }, datalabels: { display: false } },
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
            indexAxis: 'y', responsive: true, plugins: { legend: { display: false }, datalabels: { display: false } },
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
            responsive: true, interaction: { intersect: false, mode: 'index' },
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
            responsive: true, plugins: { legend: { display: false }, datalabels: { display: false } },
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
            indexAxis: 'y', responsive: true, plugins: { legend: { display: false }, datalabels: { display: false } },
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

<?php require_once __DIR__ . '/../src/views/layout/footer.php'; ?>
