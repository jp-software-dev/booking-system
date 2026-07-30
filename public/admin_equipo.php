<?php
require_once __DIR__ . '/../config/config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: login"); exit(); }
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../src/Models/Personal.php';
require_once __DIR__ . '/../src/views/layout/header.php';

$db = Database::getInstance();
$personalModel = new Personal($db);
$equipo = $personalModel->listarEquipoCompleto();

// Cargar catálogos para los selects del Modal
$especialidades = $db->query("SELECT * FROM especialidades")->fetchAll();
$consultorios = $db->query("SELECT * FROM consultorios")->fetchAll();
$mapaConsultorios = array_column($consultorios, 'nombre', 'id_consultorio');

$equipoDoctores = array_filter($equipo, fn($m) => $m['puesto'] === 'Doctor');
$equipoEnfermeros = array_filter($equipo, fn($m) => $m['puesto'] === 'Enfermero');
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold text-dark-blue mb-0"><i class="bi bi-people-fill text-primary me-2"></i>Equipo Médico y Enfermería</h2>
            <p class="text-secondary mb-0 mt-1">Directorio y control visual del personal del hospital.</p>
        </div>
        <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalPersonal">
            <i class="bi bi-person-plus-fill me-2"></i>Dar de alta
        </button>
    </div>
    
    <?php
    $renderFilaPersonal = function ($miembro) use ($mapaConsultorios) {
        $badgeClass = $miembro['puesto'] === 'Doctor' ? 'bg-primary' : 'bg-info text-dark';
        ?>
        <tr>
            <td class="ps-4 py-3">
                <div class="d-flex align-items-center">
                    <div class="bg-light rounded-circle p-2 me-3 text-center" style="width: 40px; height: 40px;"><i class="bi <?php echo $miembro['puesto'] === 'Doctor' ? 'bi-heart-pulse-fill text-primary' : 'bi-bandaid-fill text-info'; ?>"></i></div>
                    <span class="fw-bold text-dark"><?php echo htmlspecialchars($miembro['nombre'] . ' ' . $miembro['apellido_paterno']); ?></span>
                </div>
            </td>
            <td><span class="badge rounded-pill <?php echo $badgeClass; ?> px-3"><?php echo htmlspecialchars($miembro['puesto']); ?></span></td>
            <td class="text-secondary"><?php echo htmlspecialchars($miembro['detalle']); ?></td>
            <td class="text-center"><?php if ($miembro['id_consultorio']): ?><span class="fw-bold text-dark-blue">#<?php echo $miembro['id_consultorio']; ?></span><?php else: ?><span class="text-muted fst-italic">N/A</span><?php endif; ?></td>
            <td class="pe-4 text-end">
                <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 me-1"
                    onclick="verDetallePersonal(this)"
                    data-nombre-completo="<?php echo htmlspecialchars($miembro['nombre'] . ' ' . $miembro['apellido_paterno']); ?>"
                    data-puesto="<?php echo htmlspecialchars($miembro['puesto']); ?>"
                    data-especialidad="<?php echo htmlspecialchars($miembro['puesto'] === 'Doctor' ? ($miembro['detalle'] ?? 'N/A') : 'N/A'); ?>"
                    data-turno="<?php echo htmlspecialchars($miembro['puesto'] === 'Enfermero' ? ($miembro['detalle'] ?? 'N/A') : 'N/A'); ?>"
                    data-consultorio="<?php echo htmlspecialchars($miembro['id_consultorio'] ? ($mapaConsultorios[$miembro['id_consultorio']] ?? '#' . $miembro['id_consultorio']) : 'Sin asignar'); ?>"
                    data-estado="Activo"
                    title="Ver detalle">
                    <i class="bi bi-eye-fill"></i>
                </button>
                <button class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1"
                    onclick="abrirModalEditarPersonal(this)"
                    data-id="<?php echo $miembro['id']; ?>"
                    data-tipo="<?php echo strtolower($miembro['puesto']); ?>"
                    data-nombre="<?php echo htmlspecialchars($miembro['nombre'], ENT_QUOTES); ?>"
                    data-apellido="<?php echo htmlspecialchars($miembro['apellido_paterno'], ENT_QUOTES); ?>"
                    data-especialidad="<?php echo $miembro['id_especialidad'] ?? ''; ?>"
                    data-turno="<?php echo htmlspecialchars($miembro['turno'] ?? '', ENT_QUOTES); ?>"
                    data-consultorio="<?php echo $miembro['id_consultorio'] ?? ''; ?>"
                    title="Editar">
                    <i class="bi bi-pencil-square me-1"></i>Editar
                </button>
                <button class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="darDeBaja(<?php echo $miembro['id']; ?>, '<?php echo $miembro['puesto']; ?>')">
                    <i class="bi bi-person-x-fill me-1"></i>Baja
                </button>
            </td>
        </tr>
        <?php
    };
    ?>

    <h5 class="fw-bold text-dark-blue mb-3"><i class="bi bi-heart-pulse-fill text-primary me-2"></i>Equipo de Doctores</h5>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 text-secondary fw-bold">Nombre Completo</th>
                        <th class="text-secondary fw-bold">Puesto</th>
                        <th class="text-secondary fw-bold">Especialidad / Turno</th>
                        <th class="text-secondary fw-bold text-center">Consultorio</th>
                        <th class="text-secondary fw-bold pe-4 text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php if (empty($equipoDoctores)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No hay doctores registrados.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($equipoDoctores as $miembro) { $renderFilaPersonal($miembro); } ?>
                </tbody>
            </table>
        </div>
    </div>

    <h5 class="fw-bold text-dark-blue mb-3"><i class="bi bi-bandaid-fill text-info me-2"></i>Equipo de Enfermeros</h5>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 text-secondary fw-bold">Nombre Completo</th>
                        <th class="text-secondary fw-bold">Puesto</th>
                        <th class="text-secondary fw-bold">Especialidad / Turno</th>
                        <th class="text-secondary fw-bold text-center">Consultorio</th>
                        <th class="text-secondary fw-bold pe-4 text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php if (empty($equipoEnfermeros)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No hay enfermeros registrados.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($equipoEnfermeros as $miembro) { $renderFilaPersonal($miembro); } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalPersonal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i> Alta de Personal</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAddPersonal">
                <div class="modal-body p-4">
                    <div id="alertaPersonal"></div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary small">Tipo de Personal</label>
                        <select name="tipo" id="select_tipo" class="form-select" onchange="toggleCampos()" required>
                            <option value="doctor">Doctor(a)</option>
                            <option value="enfermero">Enfermero(a)</option>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary small">Nombre(s)</label>
                            <input type="text" name="nombre" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary small">Apellido Paterno</label>
                            <input type="text" name="apellido_paterno" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3" id="campo_especialidad">
                        <label class="form-label fw-bold text-secondary small">Especialidad</label>
                        <select name="id_especialidad" class="form-select">
                            <?php foreach ($especialidades as $esp): ?>
                                <option value="<?php echo $esp['id_especialidad']; ?>"><?php echo htmlspecialchars($esp['nombre_especialidad']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3 d-none" id="campo_turno">
                        <label class="form-label fw-bold text-secondary small">Turno</label>
                        <select name="turno" class="form-select">
                            <option value="Matutino">Matutino</option>
                            <option value="Vespertino">Vespertino</option>
                            <option value="Nocturno">Nocturno</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary small">Consultorio Asignado (Opcional)</label>
                        <select name="id_consultorio" class="form-select">
                            <option value="">Sin asignar / Disponibilidad flotante</option>
                            <?php foreach ($consultorios as $cons): ?>
                                <option value="<?php echo $cons['id_consultorio']; ?>"><?php echo htmlspecialchars($cons['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" id="btnGuardarPersonal">Guardar Miembro</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarPersonal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i> Editar Personal</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditPersonal">
                <div class="modal-body p-4">
                    <div id="alertaEditarPersonal"></div>
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="tipo" id="edit_tipo">

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary small">Nombre(s)</label>
                            <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary small">Apellido Paterno</label>
                            <input type="text" name="apellido_paterno" id="edit_apellido_paterno" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3" id="edit_campo_especialidad">
                        <label class="form-label fw-bold text-secondary small">Especialidad</label>
                        <select name="id_especialidad" id="edit_id_especialidad" class="form-select">
                            <?php foreach ($especialidades as $esp): ?>
                                <option value="<?php echo $esp['id_especialidad']; ?>"><?php echo htmlspecialchars($esp['nombre_especialidad']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3 d-none" id="edit_campo_turno">
                        <label class="form-label fw-bold text-secondary small">Turno</label>
                        <select name="turno" id="edit_turno" class="form-select">
                            <option value="Matutino">Matutino</option>
                            <option value="Vespertino">Vespertino</option>
                            <option value="Nocturno">Nocturno</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary small">Consultorio Asignado (Opcional)</label>
                        <select name="id_consultorio" id="edit_id_consultorio" class="form-select">
                            <option value="">Sin asignar / Disponibilidad flotante</option>
                            <?php foreach ($consultorios as $cons): ?>
                                <option value="<?php echo $cons['id_consultorio']; ?>"><?php echo htmlspecialchars($cons['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" id="btnGuardarEditarPersonal">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDetallePersonal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-lines-fill me-2"></i>Detalle del Personal</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-12"><span class="text-secondary small d-block">Nombre completo</span><span class="fw-medium" id="det_nombre_completo"></span></div>
                    <div class="col-md-6"><span class="text-secondary small d-block">Puesto</span><span class="fw-medium" id="det_puesto"></span></div>
                    <div class="col-md-6"><span class="text-secondary small d-block">Estado</span><span class="fw-medium" id="det_estado"></span></div>
                    <div class="col-md-6"><span class="text-secondary small d-block">Especialidad</span><span class="fw-medium" id="det_especialidad"></span></div>
                    <div class="col-md-6"><span class="text-secondary small d-block">Turno</span><span class="fw-medium" id="det_turno"></span></div>
                    <div class="col-12"><span class="text-secondary small d-block">Consultorio asignado</span><span class="fw-medium" id="det_consultorio"></span></div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-4">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
function verDetallePersonal(btn) {
    const { nombreCompleto, puesto, especialidad, turno, consultorio, estado } = btn.dataset;
    document.getElementById('det_nombre_completo').textContent = nombreCompleto;
    document.getElementById('det_puesto').textContent = puesto;
    document.getElementById('det_estado').textContent = estado;
    document.getElementById('det_especialidad').textContent = especialidad;
    document.getElementById('det_turno').textContent = turno;
    document.getElementById('det_consultorio').textContent = consultorio;
    new bootstrap.Modal(document.getElementById('modalDetallePersonal')).show();
}

function toggleCampos() {
    const tipo = document.getElementById('select_tipo').value;
    if (tipo === 'doctor') {
        document.getElementById('campo_especialidad').classList.remove('d-none');
        document.getElementById('campo_turno').classList.add('d-none');
    } else {
        document.getElementById('campo_especialidad').classList.add('d-none');
        document.getElementById('campo_turno').classList.remove('d-none');
    }
}

document.getElementById('formAddPersonal').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('btnGuardarPersonal');
    btn.disabled = true; btn.innerHTML = 'Guardando...';
    
    const formData = new FormData(e.target);
    const dataObj = Object.fromEntries(formData);
    
    try {
        const data = await apiClient.post('add_personal.php', dataObj);

        if (data && data.status === 'success') {
            location.reload();
        } else {
            document.getElementById('alertaPersonal').innerHTML = `<div class="alert alert-danger py-2 small">${data ? data.message : 'Error de red'}</div>`;
        }
    } catch(err) {
        document.getElementById('alertaPersonal').innerHTML = `<div class="alert alert-danger py-2 small">Error de red</div>`;
    }
    btn.disabled = false; btn.innerHTML = 'Guardar Miembro';
});

async function darDeBaja(id, tipo) {
    if (!confirm(`¿Estás seguro de que deseas dar de baja a este ${tipo}?`)) return;

    try {
        const data = await apiClient.post('delete_personal.php', { id: id, tipo: tipo });
        if (data && data.status === 'success') location.reload();
        else alert('Error: ' + (data ? data.message : 'Error de conexión'));
    } catch(err) {
        alert('Error de conexión');
    }
}

function abrirModalEditarPersonal(btn) {
    const { id, tipo, nombre, apellido, especialidad, turno, consultorio } = btn.dataset;

    document.getElementById('edit_id').value = id;
    document.getElementById('edit_tipo').value = tipo;
    document.getElementById('edit_nombre').value = nombre;
    document.getElementById('edit_apellido_paterno').value = apellido;
    document.getElementById('edit_id_consultorio').value = consultorio || '';

    const esDoctor = tipo === 'doctor';
    document.getElementById('edit_campo_especialidad').classList.toggle('d-none', !esDoctor);
    document.getElementById('edit_campo_turno').classList.toggle('d-none', esDoctor);
    if (esDoctor) {
        document.getElementById('edit_id_especialidad').value = especialidad || '';
    } else {
        document.getElementById('edit_turno').value = turno || 'Matutino';
    }

    document.getElementById('alertaEditarPersonal').innerHTML = '';
    new bootstrap.Modal(document.getElementById('modalEditarPersonal')).show();
}

document.getElementById('formEditPersonal').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('btnGuardarEditarPersonal');
    btn.disabled = true; btn.innerHTML = 'Guardando...';

    const dataObj = Object.fromEntries(new FormData(e.target));

    try {
        const data = await apiClient.post('update_personal.php', dataObj);
        if (data && data.status === 'success') {
            location.reload();
        } else {
            document.getElementById('alertaEditarPersonal').innerHTML = `<div class="alert alert-danger py-2 small">${data ? data.message : 'Error de red'}</div>`;
        }
    } catch (err) {
        document.getElementById('alertaEditarPersonal').innerHTML = `<div class="alert alert-danger py-2 small">Error de red</div>`;
    }
    btn.disabled = false; btn.innerHTML = 'Guardar Cambios';
});
</script>
<?php require_once __DIR__ . '/../src/views/layout/footer.php'; ?>