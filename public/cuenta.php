<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'paciente') { header("Location: login.php"); exit(); }

$db = Database::getInstance();
$stmt = $db->prepare("SELECT nombre, apellido_paterno, apellido_materno, telefono, email FROM pacientes WHERE id_paciente = ?");
$stmt->execute([$_SESSION['user_id']]);
$paciente = $stmt->fetch();

require_once __DIR__ . '/../src/views/layout/header.php';
?>

<div class="container py-5" style="min-height: 85vh; max-width: 720px;">
    <div class="mb-5">
        <h2 class="fw-bold text-dark-blue mb-2">Administrar Cuenta</h2>
        <p class="text-secondary mb-0">Actualiza tus datos personales, tu contraseña, o elimina tu cuenta.</p>
    </div>

    <div id="cuentaAlert"></div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-bottom py-3 px-4">
            <h5 class="fw-bold text-dark-blue mb-0"><i class="bi bi-person-vcard text-primary me-2"></i>Datos Personales</h5>
        </div>
        <div class="card-body p-4">
            <form id="formPerfil">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary small">Nombre(s)</label>
                        <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($paciente['nombre']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary small">Apellido Paterno</label>
                        <input type="text" name="apellido_paterno" class="form-control" value="<?php echo htmlspecialchars($paciente['apellido_paterno']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary small">Apellido Materno</label>
                        <input type="text" name="apellido_materno" class="form-control" value="<?php echo htmlspecialchars($paciente['apellido_materno'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary small">Teléfono</label>
                        <input type="text" name="telefono" class="form-control" maxlength="10" value="<?php echo htmlspecialchars($paciente['telefono'] ?? ''); ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold text-secondary small">Correo Electrónico</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($paciente['email']); ?>" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold mt-4" id="btnGuardarPerfil">Guardar Cambios</button>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-bottom py-3 px-4">
            <h5 class="fw-bold text-dark-blue mb-0"><i class="bi bi-shield-lock text-primary me-2"></i>Cambiar Contraseña</h5>
        </div>
        <div class="card-body p-4">
            <form id="formPassword">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold text-secondary small">Contraseña Actual</label>
                        <input type="password" name="password_actual" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary small">Nueva Contraseña</label>
                        <input type="password" name="password_nueva" class="form-control" minlength="8" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-secondary small">Confirmar Nueva Contraseña</label>
                        <input type="password" name="password_confirmar" class="form-control" minlength="8" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold mt-4" id="btnGuardarPassword">Actualizar Contraseña</button>
            </form>
        </div>
    </div>

    <div class="card border border-danger border-opacity-50 shadow-sm rounded-4">
        <div class="card-header bg-danger bg-opacity-10 border-bottom border-danger border-opacity-25 py-3 px-4">
            <h5 class="fw-bold text-danger mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Zona de Riesgo</h5>
        </div>
        <div class="card-body p-4">
            <p class="text-secondary mb-3">Eliminar tu cuenta es una acción permanente. Perderás el acceso y no podrás recuperar tus datos personales.</p>
            <button type="button" class="btn btn-outline-danger rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#modalEliminarCuenta">
                <i class="bi bi-trash3 me-2"></i>Eliminar mi cuenta
            </button>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEliminarCuenta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-danger text-white rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Eliminar Cuenta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="fw-medium">Esta acción es permanente. Se eliminará tu cuenta y no podrás recuperarla. ¿Deseas continuar?</p>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="chkEntiendo">
                    <label class="form-check-label small fw-medium" for="chkEntiendo">
                        Entiendo que esta acción no se puede deshacer
                    </label>
                </div>
                <div id="deleteCuentaAlert" class="mt-3"></div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger fw-bold" id="btnConfirmarEliminar" disabled>Eliminar mi cuenta definitivamente</button>
            </div>
        </div>
    </div>
</div>

<script>
function mostrarAlerta(id, tipo, mensaje) {
    document.getElementById(id).innerHTML = `<div class="alert alert-${tipo} py-2 small">${mensaje}</div>`;
}

document.getElementById('formPerfil').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('btnGuardarPerfil');
    btn.disabled = true; btn.innerHTML = 'Guardando...';
    document.getElementById('cuentaAlert').innerHTML = '';
    try {
        const dataObj = Object.fromEntries(new FormData(e.target));
        const data = await apiClient.post('update_perfil.php', dataObj);
        if (data && data.status === 'success') {
            mostrarAlerta('cuentaAlert', 'success', 'Datos actualizados correctamente.');
        } else {
            mostrarAlerta('cuentaAlert', 'danger', data ? data.message : 'Error de conexión');
        }
    } catch (err) {
        mostrarAlerta('cuentaAlert', 'danger', 'Error de conexión');
    }
    btn.disabled = false; btn.innerHTML = 'Guardar Cambios';
});

document.getElementById('formPassword').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('btnGuardarPassword');
    btn.disabled = true; btn.innerHTML = 'Actualizando...';
    document.getElementById('cuentaAlert').innerHTML = '';
    try {
        const dataObj = Object.fromEntries(new FormData(e.target));
        const data = await apiClient.post('update_perfil.php', dataObj);
        if (data && data.status === 'success') {
            mostrarAlerta('cuentaAlert', 'success', 'Contraseña actualizada correctamente.');
            e.target.reset();
        } else {
            mostrarAlerta('cuentaAlert', 'danger', data ? data.message : 'Error de conexión');
        }
    } catch (err) {
        mostrarAlerta('cuentaAlert', 'danger', 'Error de conexión');
    }
    btn.disabled = false; btn.innerHTML = 'Actualizar Contraseña';
});

document.getElementById('chkEntiendo').addEventListener('change', function() {
    document.getElementById('btnConfirmarEliminar').disabled = !this.checked;
});

document.getElementById('btnConfirmarEliminar').addEventListener('click', async () => {
    const btn = document.getElementById('btnConfirmarEliminar');
    btn.disabled = true; btn.innerHTML = 'Eliminando...';
    document.getElementById('deleteCuentaAlert').innerHTML = '';
    try {
        const data = await apiClient.post('delete_cuenta.php', { confirmar: true });
        if (data && data.status === 'success') {
            window.location.href = data.redirect || 'index.php';
        } else {
            mostrarAlerta('deleteCuentaAlert', 'danger', data ? data.message : 'Error de conexión');
            btn.disabled = false; btn.innerHTML = 'Eliminar mi cuenta definitivamente';
        }
    } catch (err) {
        mostrarAlerta('deleteCuentaAlert', 'danger', 'Error de conexión');
        btn.disabled = false; btn.innerHTML = 'Eliminar mi cuenta definitivamente';
    }
});
</script>

<?php require_once __DIR__ . '/../src/views/layout/footer.php'; ?>
