<?php
require_once __DIR__ . '/../config/config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: login.php"); exit(); }
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../src/views/layout/header.php';

$db = Database::getInstance();
$consultorios = $db->query("SELECT id_consultorio, nombre, ubicacion FROM consultorios ORDER BY id_consultorio ASC")->fetchAll();
$doctores = $db->query("SELECT id_doctor, nombre, apellido_paterno, id_consultorio FROM doctores")->fetchAll();
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
                <div class="bg-light rounded-3 p-3 mb-4 mt-3">
                    <h6 class="fw-bold text-secondary mb-2 small">MÉDICO ACTUAL</h6>
                    <?php if($docInfo): ?><div class="fw-bold text-dark doc-name">Dr. <?php echo htmlspecialchars($docInfo['nombre'].' '.$docInfo['apellido_paterno']); ?></div><?php else: ?><div class="text-muted doc-name">Ningún médico asignado</div><?php endif; ?>
                </div>
                <form class="form-asignar">
                    <input type="hidden" name="id_consultorio" value="<?php echo $idCons; ?>">
                    <div class="input-group">
                        <select name="id_doctor" class="form-select border-primary">
                            <option value="">-- Liberar Consultorio --</option>
                            <?php foreach($doctores as $d): ?>
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