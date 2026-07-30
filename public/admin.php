<?php
require_once __DIR__ . '/../config/config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: login.php"); exit(); }
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../src/views/layout/header.php';

$db = Database::getInstance();

// OPTIMIZACIÓN 1: Se obtiene toda la información de doctores en una sola consulta.
$lista_doctores = $db->query("SELECT id_doctor, nombre, apellido_paterno, id_consultorio FROM doctores WHERE estado = 1 ORDER BY nombre ASC")->fetchAll();
$doctores_con_consultorio = array_filter($lista_doctores, fn($doc) => $doc['id_consultorio'] !== null);

$search_id     = $_GET['search_id']     ?? '';
$search_nombre = $_GET['search_nombre'] ?? '';
$filtro_doctor = $_GET['filtro_doctor'] ?? '';
$filtro_estado = $_GET['filtro_estado'] ?? '';

$whereClauses = []; $params = [];
if (!empty($search_id))     { $whereClauses[] = "c.id_cita = ?"; $params[] = $search_id; }
if (!empty($search_nombre)) { $whereClauses[] = "(p.nombre LIKE ? OR CONCAT(p.nombre, ' ', p.apellido_paterno) LIKE ?)"; $params[] = "%$search_nombre%"; $params[] = "%$search_nombre%"; }
if (!empty($filtro_doctor)) { $whereClauses[] = "c.id_doctor = ?"; $params[] = $filtro_doctor; }
if (!empty($filtro_estado)) { $whereClauses[] = "c.estado_cita = ?"; $params[] = $filtro_estado; }

$whereSql = count($whereClauses) > 0 ? "WHERE " . implode(" AND ", $whereClauses) : "";

// --- PAGINACIÓN ---
$per_page_options = [50, 100, 200, 500];
$per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $per_page_options, true) ? (int)$_GET['per_page'] : 50;

$countStmt = $db->prepare("SELECT COUNT(*) FROM citas c JOIN pacientes p ON c.id_paciente = p.id_paciente JOIN doctores d ON c.id_doctor = d.id_doctor $whereSql");
$countStmt->execute($params);
$total_records = (int)$countStmt->fetchColumn();

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$total_pages = $total_records > 0 ? (int)ceil($total_records / $per_page) : 0;
$page = $total_pages > 0 ? min($page, $total_pages) : 1;
$offset = ($page - 1) * $per_page;

$query = "SELECT c.id_cita, p.nombre AS paciente, p.apellido_paterno AS paciente_ap, p.curp,
                 d.nombre AS doctor, d.apellido_paterno AS doctor_ap,
                 c.fecha_cita, c.hora_inicio, c.motivo_consulta, c.estado_cita,
                 co.nombre AS consultorio_nombre
          FROM citas c
          JOIN pacientes p  ON c.id_paciente    = p.id_paciente
          JOIN doctores d   ON c.id_doctor      = d.id_doctor
          LEFT JOIN consultorios co ON c.id_consultorio = co.id_consultorio
          $whereSql
          ORDER BY c.fecha_cita DESC, c.hora_inicio DESC
          LIMIT ? OFFSET ?";
$stmt = $db->prepare($query);
$idx = 1;
foreach ($params as $p) { $stmt->bindValue($idx++, $p); }
$stmt->bindValue($idx++, $per_page, PDO::PARAM_INT);
$stmt->bindValue($idx++, $offset, PDO::PARAM_INT);
$stmt->execute();
$citas = $stmt->fetchAll();

$base_params = $_GET; unset($base_params['page']);

// OPTIMIZACIÓN 2: Se calculan todas las métricas del dashboard en una sola consulta.
$metrics_stmt = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN estado_cita = 'Pendiente' THEN 1 ELSE 0 END) AS pendientes,
        SUM(CASE WHEN estado_cita = 'Confirmada' THEN 1 ELSE 0 END) AS confirmadas,
        SUM(CASE WHEN estado_cita = 'Completada' THEN 1 ELSE 0 END) AS completadas
    FROM citas
");
$metrics = $metrics_stmt->fetch(PDO::FETCH_ASSOC);

$m_total = $metrics['total'] ?? 0;
$m_pend  = $metrics['pendientes'] ?? 0;
$m_conf  = $metrics['confirmadas'] ?? 0;
$m_comp  = $metrics['completadas'] ?? 0;
?>

<div class="container-fluid py-4 px-lg-5" style="min-height: 85vh;">
    <div class="d-flex flex-wrap gap-2 mb-3">
        <span class="btn btn-sm btn-dark text-white fw-bold shadow-sm" style="pointer-events:none;">TOTAL CITAS: <?php echo $m_total; ?></span>
        <span class="btn btn-sm btn-warning text-dark fw-bold shadow-sm" style="pointer-events:none;">PENDIENTES: <?php echo $m_pend; ?></span>
        <span class="btn btn-sm btn-info text-dark fw-bold shadow-sm" style="pointer-events:none;">CONFIRMADAS: <?php echo $m_conf; ?></span>
        <span class="btn btn-sm btn-success text-white fw-bold shadow-sm" style="pointer-events:none;">COMPLETADAS: <?php echo $m_comp; ?></span>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h5 class="fw-bold text-dark-blue mb-0"><i class="bi bi-list-task me-2 text-primary"></i>Gestión de Citas</h5>
            <div class="d-flex gap-2">
                <button class="btn btn-primary rounded-pill px-4 shadow-sm fw-medium" data-bs-toggle="modal" data-bs-target="#modalNuevaCitaAdmin">
                    <i class="bi bi-plus-lg me-1"></i> Nueva Cita
                </button>
                <a href="../api/generar_pdf.php?<?php echo htmlspecialchars($_SERVER['QUERY_STRING'] ?? ''); ?>" class="btn btn-outline-danger rounded-pill px-4 fw-medium">
                    <i class="bi bi-file-pdf me-1"></i> PDF
                </a>
            </div>
        </div>

        <div class="bg-light p-3 border-bottom">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-secondary mb-1">Buscar ID</label>
                    <input type="number" name="search_id" class="form-control form-control-sm" placeholder="Ej. 42" value="<?php echo htmlspecialchars($search_id); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary mb-1">Buscar Paciente</label>
                    <input type="text" name="search_nombre" class="form-control form-control-sm" placeholder="Nombre del paciente" value="<?php echo htmlspecialchars($search_nombre); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary mb-1">Médico</label>
                    <select name="filtro_doctor" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($lista_doctores as $doc): ?>
                            <option value="<?php echo $doc['id_doctor']; ?>" <?php echo $filtro_doctor == $doc['id_doctor'] ? 'selected' : ''; ?>>
                                Dr. <?php echo htmlspecialchars($doc['nombre'].' '.$doc['apellido_paterno']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-secondary mb-1">Estado</label>
                    <select name="filtro_estado" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="Pendiente"  <?php echo $filtro_estado==='Pendiente' ?'selected':''; ?>>Pendiente</option>
                        <option value="Confirmada" <?php echo $filtro_estado==='Confirmada'?'selected':''; ?>>Confirmada</option>
                        <option value="Completada" <?php echo $filtro_estado==='Completada'?'selected':''; ?>>Completada</option>
                        <option value="Cancelada"  <?php echo $filtro_estado==='Cancelada' ?'selected':''; ?>>Cancelada</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 fw-medium shadow-sm">Filtrar</button>
                    <a href="admin.php" class="btn btn-light btn-sm rounded-pill px-4 fw-bold border text-uppercase shadow-sm">LIMPIAR</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Paciente / CURP</th>
                        <th>Médico / Consultorio</th>
                        <th>Fecha y Hora</th>
                        <th>Motivo Completo de Consulta</th>
                        <th>Estado Actual</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php foreach ($citas as $cita):
                        $bg = match($cita['estado_cita']) {
                            'Pendiente'  => 'bg-warning text-dark',
                            'Confirmada' => 'bg-info text-dark',
                            'Completada' => 'bg-success text-white',
                            'Cancelada'  => 'bg-danger text-white',
                            default      => 'bg-secondary text-white'
                        };
                    ?>
                    <tr data-fecha="<?php echo date('Y-m-d', strtotime($cita['fecha_cita'])); ?>" data-hora="<?php echo date('H:i', strtotime($cita['hora_inicio'])); ?>">
                        <td data-label="ID" class="ps-4 text-muted fw-bold">#<?php echo $cita['id_cita']; ?></td>
                        <td data-label="Paciente">
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($cita['paciente'].' '.$cita['paciente_ap']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($cita['curp'] ?? 'N/A'); ?></small>
                        </td>
                        <td data-label="Médico">
                            <div class="text-dark">Dr. <?php echo htmlspecialchars($cita['doctor'].' '.$cita['doctor_ap']); ?></div>
                            <small class="text-primary fw-medium"><?php echo htmlspecialchars($cita['consultorio_nombre'] ?? 'Sin asignar'); ?></small>
                        </td>
                        <td data-label="Fecha y Hora">
                            <div class="fw-medium"><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></div>
                            <small class="text-secondary"><?php echo date('h:i A', strtotime($cita['hora_inicio'])); ?></small>
                        </td>
                        <td data-label="Motivo">
                            <div class="small text-secondary text-wrap text-break" style="max-width: 260px;">
                                <?php echo htmlspecialchars($cita['motivo_consulta']); ?>
                            </div>
                        </td>
                        <td data-label="Estado">
                            <select class="form-select form-select-sm status-badge <?php echo $bg; ?>" onchange="actualizarEstado(<?php echo $cita['id_cita']; ?>, this.value)" style="width:130px; cursor:pointer;">
                                <option value="Pendiente"  <?php echo $cita['estado_cita']==='Pendiente' ?'selected':''; ?>>Pendiente</option>
                                <option value="Confirmada" <?php echo $cita['estado_cita']==='Confirmada'?'selected':''; ?>>Confirmada</option>
                                <option value="Completada" <?php echo $cita['estado_cita']==='Completada'?'selected':''; ?>>Completada</option>
                                <option value="Cancelada"  <?php echo $cita['estado_cita']==='Cancelada' ?'selected':''; ?>>Cancelada</option>
                            </select>
                        </td>
                        <td data-label="Acciones" class="text-end pe-4">
                            <div class="btn-group">
                                <button class="btn btn-light btn-sm text-primary border" onclick="abrirModalEditar(this, <?php echo $cita['id_cita']; ?>)" title="Editar Cita">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button class="btn btn-light btn-sm text-danger border" onclick="if(confirm('¿Eliminar definitivamente la cita #<?php echo $cita['id_cita']; ?>?')) eliminarCita(<?php echo $cita['id_cita']; ?>)" title="Eliminar Cita">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($citas)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">No se encontraron citas con los filtros aplicados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php $items_count = count($citas); require __DIR__ . '/../src/views/partials/pagination.php'; ?>
</div>

<div class="modal fade" id="modalNuevaCitaAdmin" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Nueva Cita</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="alertaNuevaCita"></div>
                <div class="mb-3">
                    <label class="form-label fw-bold text-secondary small">Correo del Paciente</label>
                    <input type="email" id="nc_email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold text-secondary small">Médico Especialista</label>
                    <select id="nc_doctor" class="form-select" required>
                        <option value="">Seleccione un médico...</option>
                        <?php foreach ($doctores_con_consultorio as $doc): ?>
                            <option value="<?php echo $doc['id_doctor']; ?>">
                                Dr. <?php echo htmlspecialchars($doc['nombre'].' '.$doc['apellido_paterno']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary small">Fecha</label>
                        <input type="date" id="nc_fecha" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary small">Hora</label>
                        <select id="nc_hora" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php
                            $slots = [];
                            $start = strtotime('07:00'); $end = strtotime('23:30');
                            for ($t = $start; $t <= $end; $t += 1800) $slots[] = date('H:i', $t);
                            foreach ($slots as $s):
                            ?>
                                <option value="<?php echo $s; ?>">
                                    <?php echo date('g:i A', strtotime($s)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold text-secondary small">Motivo de la cita</label>
                    <textarea id="nc_motivo" class="form-control" rows="2" placeholder="Describa los síntomas o motivo de consulta..." required></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-4">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" id="btnCrearCitaAdmin" onclick="crearNuevaCita()">
                    <i class="bi bi-check-lg me-1"></i> Crear Cita
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarCita" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Editar Cita</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="edit_id_cita">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary small">Nueva Fecha</label>
                        <input type="date" id="edit_fecha" class="form-control" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary small">Nueva Hora</label>
                        <select id="edit_hora" class="form-select">
                            <?php foreach ($slots as $s): ?>
                                <option value="<?php echo $s; ?>"><?php echo date('g:i A', strtotime($s)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-4">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold" onclick="guardarEdicion()">Guardar Cambios</button>
            </div>
        </div>
    </div>
</div>

<script>
async function actualizarEstado(id, nuevoEstado) {
    const data = await apiClient.post('update_status.php', { id_cita: id, estado: nuevoEstado });
    if (data && data.status === 'success') location.reload();
    else alert('Error: ' + (data ? data.message : 'Fallo de actualización'));
}

async function eliminarCita(id) {
    const data = await apiClient.post('delete_cita.php', { id_cita: id });
    if (data && data.status === 'success') location.reload();
    else alert('Error al eliminar: ' + (data ? data.message : ''));
}

async function crearNuevaCita() {
    const alertEl = document.getElementById('alertaNuevaCita');
    const btn     = document.getElementById('btnCrearCitaAdmin');
    const email   = document.getElementById('nc_email').value.trim();
    const doctor  = document.getElementById('nc_doctor').value;
    const fecha   = document.getElementById('nc_fecha').value;
    const hora    = document.getElementById('nc_hora').value;
    const motivo  = document.getElementById('nc_motivo').value.trim();

    alertEl.innerHTML = '';
    if (!email || !doctor || !fecha || !hora || !motivo) {
        alertEl.innerHTML = '<div class="alert alert-warning py-2 small">Completa todos los campos.</div>';
        return;
    }

    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creando...';

    try {
        const data = await apiClient.post('create_cita.php', { email, id_doctor: doctor, fecha_cita: fecha, hora_inicio: hora, motivo });
        if (data && data.status === 'success') {
            bootstrap.Modal.getInstance(document.getElementById('modalNuevaCitaAdmin')).hide();
            location.reload();
        } else {
            alertEl.innerHTML = `<div class="alert alert-danger py-2 small">${data ? data.message : 'Error al crear la cita'}</div>`;
        }
    } catch (e) {
        alertEl.innerHTML = '<div class="alert alert-danger py-2 small">Error de conexión.</div>';
    } finally {
        btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Crear Cita';
    }
}

function abrirModalEditar(btn, id) {
    document.getElementById('edit_id_cita').value = id;

    const fila = btn.closest('tr');
    const fecha = fila?.dataset.fecha ?? '';
    const hora = fila?.dataset.hora ?? '';

    document.getElementById('edit_fecha').value = fecha;
    const selectHora = document.getElementById('edit_hora');
    selectHora.value = hora;
    if (selectHora.value !== hora) {
        // La hora de la cita no coincide con ninguna opción del select (ej. slot legado fuera de la rejilla de 30 min).
        selectHora.selectedIndex = 0;
    }

    new bootstrap.Modal(document.getElementById('modalEditarCita')).show();
}

async function guardarEdicion() {
    const id    = document.getElementById('edit_id_cita').value;
    const fecha = document.getElementById('edit_fecha').value;
    const hora  = document.getElementById('edit_hora').value;
    if (!fecha || !hora) { alert('Selecciona fecha y hora.'); return; }

    const data = await apiClient.post('update_cita_admin.php', { id_cita: id, fecha_cita: fecha, hora_inicio: hora });
    if (data && data.status === 'success') {
        bootstrap.Modal.getInstance(document.getElementById('modalEditarCita')).hide();
        location.reload();
    } else {
        alert(data ? data.message : 'Error al guardar');
    }
}
</script>

<?php require_once __DIR__ . '/../src/views/layout/footer.php'; ?>