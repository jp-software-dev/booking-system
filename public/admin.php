<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once '../config/Database.php';
require_once '../src/views/layout/header.php';

$db = Database::getInstance();

$stmtDocs = $db->query("SELECT id_doctor, nombre, apellido_paterno FROM doctores ORDER BY nombre ASC");
$lista_doctores = $stmtDocs->fetchAll();

$search_id = $_GET['search_id'] ?? '';
$filtro_doctor = $_GET['filtro_doctor'] ?? '';
$filtro_estado = $_GET['filtro_estado'] ?? '';

$whereClauses = [];
$params = [];

if (!empty($search_id)) {
    $whereClauses[] = "c.id_cita = ?";
    $params[] = $search_id;
}
if (!empty($filtro_doctor)) {
    $whereClauses[] = "c.id_doctor = ?";
    $params[] = $filtro_doctor;
}
if (!empty($filtro_estado)) {
    $whereClauses[] = "c.estado_cita = ?";
    $params[] = $filtro_estado;
}

$query = "SELECT c.id_cita, p.nombre AS paciente, d.nombre AS doctor, d.apellido_paterno AS doctor_ap,
                 c.fecha_cita, c.hora_inicio, c.estado_cita
          FROM citas c
          JOIN pacientes p ON c.id_paciente = p.id_paciente
          JOIN doctores d ON c.id_doctor = d.id_doctor";

if (count($whereClauses) > 0) {
    $query .= " WHERE " . implode(" AND ", $whereClauses);
}

$query .= " ORDER BY c.fecha_cita DESC, c.hora_inicio DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$citas = $stmt->fetchAll();

$total = count($citas);
$pendientes = count(array_filter($citas, fn($c) => $c['estado_cita'] === 'Pendiente'));
?>

<div class="container-fluid py-5 bg-light">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark-blue">Panel Administrativo</h2>
                <p class="text-secondary">Gestión central de la agenda médica</p>
            </div>
            <div class="d-flex gap-3">
                <div class="bg-white border rounded-pill px-4 py-2 shadow-sm">
                    <span class="text-secondary small fw-bold me-2">RESULTADOS:</span>
                    <span id="total-count" class="badge bg-primary rounded-pill fs-6"><?php echo $total; ?></span>
                </div>
                <div class="bg-white border rounded-pill px-4 py-2 shadow-sm">
                    <span class="text-secondary small fw-bold me-2">PENDIENTES:</span>
                    <span id="pending-count" class="badge bg-warning text-dark rounded-pill fs-6"><?php echo $pendientes; ?></span>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4" style="border-radius: 15px;">
            <div class="card-body p-4">
                <form method="GET" action="admin.php" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label text-secondary fw-bold small">ID / Turno</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-hash text-primary"></i></span>
                            <input type="number" name="search_id" class="form-control border-start-0" placeholder="Ej: 86" value="<?php echo htmlspecialchars($search_id, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary fw-bold small">Especialista</label>
                        <select name="filtro_doctor" class="form-select form-select-sm">
                            <option value="">Todos los doctores</option>
                            <?php foreach ($lista_doctores as $doc): ?>
                                <option value="<?php echo $doc['id_doctor']; ?>" <?php echo ($filtro_doctor == $doc['id_doctor']) ? 'selected' : ''; ?>>
                                    Dr. <?php echo htmlspecialchars($doc['nombre'] . ' ' . $doc['apellido_paterno'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary fw-bold small">Estado</label>
                        <select name="filtro_estado" class="form-select form-select-sm">
                            <option value="">Cualquier estado</option>
                            <option value="Pendiente" <?php echo ($filtro_estado == 'Pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="Confirmada" <?php echo ($filtro_estado == 'Confirmada') ? 'selected' : ''; ?>>Confirmada</option>
                            <option value="Completada" <?php echo ($filtro_estado == 'Completada') ? 'selected' : ''; ?>>Completada</option>
                            <option value="Cancelada" <?php echo ($filtro_estado == 'Cancelada') ? 'selected' : ''; ?>>Cancelada</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold rounded-pill">
                            <i class="bi bi-filter me-1"></i> Filtrar
                        </button>
                        <a href="admin.php" class="btn btn-outline-secondary btn-sm w-100 fw-bold rounded-pill">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 admin-table">
                    <thead>
                        <tr>
                            <th class="ps-4">ID / Turno</th>
                            <th>Paciente</th>
                            <th>Especialista</th>
                            <th>Fecha y Hora</th>
                            <th>Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="citas-table-body">
                        <?php foreach ($citas as $cita): 
                            $estado = $cita['estado_cita'];
                            $claseEstado = match($estado) {
                                'Confirmada' => 'bg-success text-white',
                                'Cancelada' => 'bg-danger text-white',
                                'Completada' => 'bg-primary text-white',
                                default => 'bg-warning text-dark'
                            };
                        ?>
                        <tr id="row-<?php echo $cita['id_cita']; ?>">
                            <td class="ps-4 fw-bold text-primary">#<?php echo $cita['id_cita']; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="bg-light rounded-circle p-2 me-2">
                                        <i class="bi bi-person-fill text-secondary"></i>
                                    </div>
                                    <span class="fw-medium"><?php echo htmlspecialchars(strtoupper($cita['paciente']), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </td>
                            <td class="text-secondary">Dr. <?php echo htmlspecialchars($cita['doctor'] . ' ' . $cita['doctor_ap'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <div class="small fw-bold"><i class="bi bi-calendar3 me-1 text-primary"></i> <?php echo $cita['fecha_cita']; ?></div>
                                <div class="text-muted small"><i class="bi bi-clock me-1"></i> <?php echo date('h:i A', strtotime($cita['hora_inicio'])); ?></div>
                            </td>
                            <td>
                                <select class="form-select form-select-sm w-auto border-0 shadow-sm fw-bold status-dropdown <?php echo $claseEstado; ?>" 
                                        onchange="cambiarEstado(this, <?php echo $cita['id_cita']; ?>)">
                                    <option value="Pendiente" <?php echo $estado == 'Pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                    <option value="Confirmada" <?php echo $estado == 'Confirmada' ? 'selected' : ''; ?>>Confirmada</option>
                                    <option value="Cancelada" <?php echo $estado == 'Cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                                    <option value="Completada" <?php echo $estado == 'Completada' ? 'selected' : ''; ?>>Completada</option>
                                </select>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-primary btn-sm rounded-circle me-1 shadow-sm" 
                                        style="width: 35px; height: 35px; padding: 0; display: inline-flex; align-items: center; justify-content: center;"
                                        onclick="abrirModalEditar(<?php echo $cita['id_cita']; ?>, '<?php echo htmlspecialchars($cita['fecha_cita'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($cita['hora_inicio'], ENT_QUOTES, 'UTF-8'); ?>')"
                                        title="Editar cita">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                                <button class="btn btn-danger btn-sm rounded-circle shadow-sm" 
                                        style="width: 35px; height: 35px; padding: 0; display: inline-flex; align-items: center; justify-content: center;"
                                        onclick="eliminarCita(<?php echo $cita['id_cita']; ?>)"
                                        title="Eliminar permanentemente">
                                    <i class="bi bi-trash3-fill"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($citas)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <i class="bi bi-search fs-1 text-light-gray d-block mb-2"></i>
                                <span class="text-secondary">No se encontraron citas con los filtros aplicados.</span>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarCita" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 bg-primary text-white">
                <h5 class="modal-title fw-bold">Editar cita #<span id="title_id_cita"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarCita">
                <div class="modal-body p-4">
                    <input type="hidden" name="id_cita" id="edit_id_cita">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">Nueva fecha</label>
                        <input type="date" id="edit_fecha" name="fecha_cita" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">Nueva hora</label>
                        <input type="time" id="edit_hora" name="hora_inicio" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="adminToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="toastMessage"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script>
function mostrarToast(mensaje, tipo = 'success') {
    const toastEl = document.getElementById('adminToast');
    toastEl.className = `toast align-items-center text-white border-0 bg-${tipo === 'success' ? 'dark' : 'danger'}`;
    document.getElementById('toastMessage').innerHTML = mensaje;
    new bootstrap.Toast(toastEl).show();
}

async function cambiarEstado(select, id) {
    const nuevoEstado = select.value;
    try {
        const res = await fetch('../api/update_status.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id_cita: id, estado: nuevoEstado})
        });
        const data = await res.json();
        if (data.status === 'success') {
            mostrarToast('Estado actualizado');
            location.reload(); 
        }
    } catch (e) { mostrarToast('Error de conexión', 'error'); }
}

async function eliminarCita(id) {
    if (!confirm('¿Eliminar permanentemente esta cita?')) return;
    try {
        const res = await fetch('../api/delete_cita.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id_cita: id})
        });
        const data = await res.json();
        if (data.status === 'success') location.reload();
    } catch (e) { mostrarToast('Error al eliminar', 'error'); }
}

function abrirModalEditar(id, fecha, hora) {
    document.getElementById('edit_id_cita').value = id;
    document.getElementById('title_id_cita').innerText = id;
    document.getElementById('edit_fecha').value = fecha;
    document.getElementById('edit_hora').value = hora;
    new bootstrap.Modal(document.getElementById('modalEditarCita')).show();
}

document.getElementById('formEditarCita').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    try {
        const res = await fetch('../api/update_cita_admin.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.status === 'success') location.reload();
    } catch (error) { mostrarToast('Error al guardar', 'error'); }
});
</script>

<?php require_once '../src/views/layout/footer.php'; ?>