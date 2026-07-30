<?php
require_once __DIR__ . '/../config/config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: login.php"); exit(); }
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../src/views/layout/header.php';

$db = Database::getInstance();
$consultorios = $db->query("SELECT id_consultorio, nombre, ubicacion, id_especialidad FROM consultorios ORDER BY id_consultorio ASC")->fetchAll();
$doctores = $db->query("SELECT id_doctor, nombre, apellido_paterno, id_consultorio, id_especialidad FROM doctores WHERE estado = 1")->fetchAll();
$especialidades = $db->query("SELECT id_especialidad, nombre_especialidad FROM especialidades ORDER BY nombre_especialidad ASC")->fetchAll();
$mapaEspecialidades = array_column($especialidades, 'nombre_especialidad', 'id_especialidad');
?>
<div class="container py-5">
    <h2 class="fw-bold text-dark-blue mb-4"><i class="bi bi-door-open-fill text-primary me-2"></i>Asignación de Consultorios</h2>
    <div class="row g-4">
        <?php foreach ($consultorios as $cons): 
            $idCons = $cons['id_consultorio'];
            $docAsignado = array_filter($doctores, fn($d) => $d['id_consultorio'] == $idCons);
            $docInfo = reset($docAsignado);
            $estaOcupado = ($docInfo !== false);
        ?>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 position-relative">
                <div class="position-absolute top-0 end-0 p-3">
                    <?php if($estaOcupado): ?><span class="badge bg-danger rounded-pill">Ocupado</span><?php else: ?><span class="badge bg-success rounded-pill">Disponible</span><?php endif; ?>
                </div>
                <h4 class="fw-bold text-dark-blue mb-1"><?php echo htmlspecialchars($cons['nombre']); ?></h4>
                <form class="form-especialidad mb-3">
                    <input type="hidden" name="id_consultorio" value="<?php echo $idCons; ?>">
                    <label class="form-label fw-bold text-secondary small mb-1">Especialidad del consultorio</label>
                    <div class="input-group input-group-sm">
                        <select name="id_especialidad" class="form-select">
                            <option value="">-- Sin especialidad definida --</option>
                            <?php foreach ($especialidades as $esp): ?>
                                <option value="<?php echo $esp['id_especialidad']; ?>" <?php echo ($cons['id_especialidad'] == $esp['id_especialidad']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($esp['nombre_especialidad']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-outline-primary">Guardar</button>
                    </div>
                </form>
                <div class="bg-light rounded-3 p-3 mb-4">
                    <h6 class="fw-bold text-secondary mb-2 small">MÉDICO ACTUAL</h6>
                    <?php if($docInfo): ?><div class="fw-bold text-dark doc-name">Dr. <?php echo htmlspecialchars($docInfo['nombre'].' '.$docInfo['apellido_paterno']); ?></div><?php else: ?><div class="text-muted doc-name">Ningún médico asignado</div><?php endif; ?>
                </div>
                <form class="form-asignar">
                    <input type="hidden" name="id_consultorio" value="<?php echo $idCons; ?>">
                    <div class="input-group">
                        <select name="id_doctor" class="form-select border-primary">
                            <option value="">-- Liberar Consultorio --</option>
                            <?php
                            // Solo médicos sin consultorio asignado, o el que ya ocupa ESTE consultorio
                            // (si no, no aparecería seleccionado en su propia tarjeta). Los asignados a
                            // OTRO consultorio no se muestran hasta que se liberen desde su tarjeta.
                            // Además, si el consultorio ya tiene especialidad definida, solo se muestran
                            // doctores con esa misma especialidad (mientras no tenga especialidad definida,
                            // no se restringe para no bloquear la asignación).
                            $docsDisponibles = array_filter($doctores, function ($d) use ($idCons, $cons) {
                                $libreOEnEsteConsultorio = $d['id_consultorio'] === null || $d['id_consultorio'] == $idCons;
                                $coincideEspecialidad = empty($cons['id_especialidad']) || $d['id_especialidad'] == $cons['id_especialidad'];
                                return $libreOEnEsteConsultorio && $coincideEspecialidad;
                            });
                            foreach ($docsDisponibles as $d): ?>
                                <option value="<?php echo $d['id_doctor']; ?>" <?php echo ($docInfo && $docInfo['id_doctor'] == $d['id_doctor']) ? 'selected' : ''; ?>>Dr. <?php echo htmlspecialchars($d['nombre'].' '.$d['apellido_paterno']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<script>
document.querySelectorAll('.form-especialidad').forEach(f => f.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = f.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = 'Guardando...';

    const formData = new FormData(f);
    const dataObj = Object.fromEntries(formData);

    try {
        const data = await apiClient.post('update_consultorio_especialidad.php', dataObj);

        if (data && data.status === 'success') {
            location.reload();
        } else {
            alert(data ? data.message : 'Error de red al actualizar la especialidad');
            btn.disabled = false;
            btn.innerHTML = 'Guardar';
        }
    } catch (err) {
        alert('Error de red al actualizar la especialidad');
        btn.disabled = false;
        btn.innerHTML = 'Guardar';
    }
}));

document.querySelectorAll('.form-asignar').forEach(f => f.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = f.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = 'Actualizando...';
    
    const formData = new FormData(f);
    const dataObj = Object.fromEntries(formData);
    const docSelect = f.querySelector('select[name="id_doctor"]');
    const idDoctor = docSelect.value;
    
    try {
        const data = await apiClient.post('assign_consultorio.php', dataObj);

        if(data && data.status === 'success') {
            const card = f.closest('.card');
            const badge = card.querySelector('.badge');
            const docName = card.querySelector('.doc-name');
            
            if (!idDoctor) {
                badge.className = 'badge bg-success rounded-pill';
                badge.textContent = 'Disponible';
                docName.className = 'text-muted doc-name';
                docName.textContent = 'Ningún médico asignado';
            } else {
                badge.className = 'badge bg-danger rounded-pill';
                badge.textContent = 'Ocupado';
                docName.className = 'fw-bold text-dark doc-name';
                docName.textContent = docSelect.options[docSelect.selectedIndex].text;
            }
            
            btn.classList.replace('btn-primary', 'btn-success');
            btn.innerHTML = '¡Listo!';
            setTimeout(() => {
                btn.classList.replace('btn-success', 'btn-primary');
                btn.innerHTML = 'Actualizar';
                btn.disabled = false;
            }, 1500);
        } else {
            alert(data ? data.message : 'Error de red al actualizar consultorio');
            btn.disabled = false;
            btn.innerHTML = 'Actualizar';
        }
    } catch(err) {
        alert('Error de red al actualizar consultorio');
        btn.disabled = false;
        btn.innerHTML = 'Actualizar';
    }
}));
</script>
<?php require_once __DIR__ . '/../src/views/layout/footer.php'; ?>