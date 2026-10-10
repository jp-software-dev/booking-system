<?php

/**
 * VISTA: ADMINISTRAR CUENTA (PACIENTE)
 *
 * Autogestión de la cuenta (derechos ARCO):
 *   - Datos personales (cambiar el correo exige la contraseña actual).
 *   - Cambio de contraseña con confirmación por correo.
 *   - Preferencia de notificaciones por correo.
 *   - Exportación de datos personales (PDF o JSON).
 *   - Historial de accesos (últimos inicios de sesión).
 *   - Eliminación de la cuenta.
 *
 * Acceso: paciente.
 */

require_once __DIR__ . '/../config/config.php';

use App\Helpers\Bitacora;
use App\Http\Sesion;
use App\Models\Paciente;

Sesion::exigirRol(Sesion::ROL_PACIENTE);

// === DATOS ===
$idPaciente = (int) Sesion::usuarioId();
$paciente = (new Paciente())->obtenerPorId($idPaciente);

if ($paciente === null) {
    Sesion::cerrar();
    Sesion::redirigir('login.php');
}

// Historial de accesos: se reutiliza la bitácora, que ya registra cada inicio de sesión.
$accesos = Bitacora::ultimosAccesos($idPaciente, Sesion::ROL_PACIENTE);

// === VISTA ===
require __DIR__ . '/../src/views/layout/header.php';
?>
<div class="container py-5" style="min-height: 85vh; max-width: 720px;">
    <div class="mb-5">
        <h2 class="fw-bold text-dark-blue mb-2">Administrar Cuenta</h2>
        <p class="text-secondary mb-0">Actualiza tus datos personales, tu contraseña o elimina tu cuenta.</p>
    </div>

    <div id="cuentaAlert"></div>

    <!-- === DATOS PERSONALES === -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-bottom py-3 px-4">
            <h5 class="fw-bold text-dark-blue mb-0"><i class="bi bi-person-vcard text-primary me-2"></i>Datos Personales</h5>
        </div>
        <div class="card-body p-4">
            <form id="formPerfil">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="perfil_nombre" class="form-label fw-bold text-secondary small">Nombre(s)</label>
                        <input type="text" name="nombre" id="perfil_nombre" class="form-control" maxlength="50" value="<?= e($paciente['nombre']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="perfil_paterno" class="form-label fw-bold text-secondary small">Apellido Paterno</label>
                        <input type="text" name="apellido_paterno" id="perfil_paterno" class="form-control" maxlength="50" value="<?= e($paciente['apellido_paterno']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="perfil_materno" class="form-label fw-bold text-secondary small">Apellido Materno</label>
                        <input type="text" name="apellido_materno" id="perfil_materno" class="form-control" maxlength="50" value="<?= e($paciente['apellido_materno']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="perfil_telefono" class="form-label fw-bold text-secondary small">Teléfono</label>
                        <input type="text" name="telefono" id="perfil_telefono" class="form-control" maxlength="10" inputmode="numeric" value="<?= e($paciente['telefono']) ?>" required>
                    </div>
                    <div class="col-12">
                        <label for="perfil_email" class="form-label fw-bold text-secondary small">Correo Electrónico</label>
                        <input type="email" name="email" id="perfil_email" class="form-control" maxlength="100" value="<?= e($paciente['email']) ?>" data-original="<?= e($paciente['email']) ?>" required>
                    </div>
                    <!-- Solo se muestra si el correo cambia: el servidor exige la contraseña para ese cambio. -->
                    <div class="col-12 d-none" id="grupoPasswordEmail">
                        <label for="perfil_password" class="form-label fw-bold text-secondary small">Contraseña actual (necesaria para cambiar el correo)</label>
                        <input type="password" name="password_actual" id="perfil_password" class="form-control" autocomplete="current-password">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold mt-4" id="btnGuardarPerfil">Guardar Cambios</button>
            </form>
        </div>
    </div>

    <!-- === CAMBIO DE CONTRASEÑA === -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-bottom py-3 px-4">
            <h5 class="fw-bold text-dark-blue mb-0"><i class="bi bi-shield-lock text-primary me-2"></i>Cambiar Contraseña</h5>
        </div>
        <div class="card-body p-4">
            <p class="text-secondary small mb-3">Por seguridad, el cambio no se aplica de inmediato: te enviaremos un correo para confirmarlo.</p>
            <form id="formPassword">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="pass_actual" class="form-label fw-bold text-secondary small">Contraseña Actual</label>
                        <input type="password" name="password_actual" id="pass_actual" class="form-control" autocomplete="current-password" required>
                    </div>
                    <div class="col-md-6">
                        <label for="pass_nueva" class="form-label fw-bold text-secondary small">Nueva Contraseña</label>
                        <input type="password" name="password_nueva" id="pass_nueva" class="form-control" minlength="8" maxlength="200" autocomplete="new-password" required>
                    </div>
                    <div class="col-md-6">
                        <label for="pass_confirmar" class="form-label fw-bold text-secondary small">Confirmar Nueva Contraseña</label>
                        <input type="password" name="password_confirmar" id="pass_confirmar" class="form-control" minlength="8" maxlength="200" autocomplete="new-password" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold mt-4" id="btnGuardarPassword">Actualizar Contraseña</button>
            </form>
        </div>
    </div>

    <!-- === PREFERENCIAS DE NOTIFICACIÓN === -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-bottom py-3 px-4">
            <h5 class="fw-bold text-dark-blue mb-0"><i class="bi bi-bell text-primary me-2"></i>Preferencias de Notificación</h5>
        </div>
        <div class="card-body p-4">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="chkNotificaciones" <?= !empty($paciente['recibir_notificaciones']) ? 'checked' : '' ?>>
                <label class="form-check-label fw-medium" for="chkNotificaciones">Recibir correos de registro, confirmación y cancelación de citas</label>
            </div>
            <div id="notificacionesAlert" class="mt-2"></div>
        </div>
    </div>

    <!-- === EXPORTAR DATOS === -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-bottom py-3 px-4">
            <h5 class="fw-bold text-dark-blue mb-0"><i class="bi bi-download text-primary me-2"></i>Exportar Mis Datos</h5>
        </div>
        <div class="card-body p-4">
            <p class="text-secondary mb-3">Descarga una copia de tus datos personales y tu historial completo de citas (derecho de acceso y portabilidad).</p>
            <div class="d-flex gap-2 flex-wrap">
                <a href="../api/exportar_datos_paciente.php?formato=pdf" class="btn btn-outline-primary rounded-pill px-4 fw-medium"><i class="bi bi-file-earmark-pdf me-2"></i>Descargar PDF</a>
                <a href="../api/exportar_datos_paciente.php?formato=json" class="btn btn-outline-secondary rounded-pill px-4 fw-medium"><i class="bi bi-filetype-json me-2"></i>Descargar JSON</a>
            </div>
        </div>
    </div>

    <!-- === HISTORIAL DE ACCESOS === -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-bottom py-3 px-4">
            <h5 class="fw-bold text-dark-blue mb-0"><i class="bi bi-clock-history text-primary me-2"></i>Historial de Accesos a Mi Cuenta</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th class="ps-4">Fecha</th><th class="pe-4">Hora</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accesos as $acceso): ?>
                            <tr>
                                <td class="ps-4" data-label="Fecha"><?= fecha_corta($acceso) ?></td>
                                <td class="pe-4" data-label="Hora"><?= hora_corta($acceso) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($accesos === []): ?>
                            <tr><td colspan="2" class="text-center text-muted py-4">No hay accesos registrados todavía.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- === ZONA DE RIESGO === -->
    <div class="card border border-danger border-opacity-50 shadow-sm rounded-4">
        <div class="card-header bg-danger bg-opacity-10 border-bottom border-danger border-opacity-25 py-3 px-4">
            <h5 class="fw-bold text-danger mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Zona de Riesgo</h5>
        </div>
        <div class="card-body p-4">
            <p class="text-secondary mb-3">Eliminar tu cuenta es una acción permanente: perderás el acceso, se borrarán tus datos personales y se cancelarán tus citas futuras.</p>
            <button type="button" class="btn btn-outline-danger rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#modalEliminarCuenta">
                <i class="bi bi-trash3 me-2"></i>Eliminar mi cuenta
            </button>
        </div>
    </div>
</div>

<!-- === MODAL: ELIMINAR CUENTA === -->
<div class="modal fade" id="modalEliminarCuenta" tabindex="-1" aria-labelledby="modalEliminarCuentaTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-danger text-white rounded-top-4">
                <h5 class="modal-title fw-bold" id="modalEliminarCuentaTitulo"><i class="bi bi-exclamation-triangle-fill me-2"></i>Eliminar Cuenta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <p class="fw-medium">Esta acción es permanente y no podrás recuperar tu cuenta. ¿Deseas continuar?</p>
                <label for="eliminar_password" class="form-label fw-bold text-secondary small">Escribe tu contraseña para confirmar</label>
                <input type="password" id="eliminar_password" class="form-control mb-3" autocomplete="current-password">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="chkEntiendo">
                    <label class="form-check-label small fw-medium" for="chkEntiendo">Entiendo que esta acción no se puede deshacer</label>
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
// === DATOS PERSONALES ===
const campoEmail = document.getElementById('perfil_email');

// El campo de contraseña aparece solo cuando el correo es distinto del actual.
campoEmail.addEventListener('input', () => {
    const cambio = campoEmail.value.trim().toLowerCase() !== campoEmail.dataset.original.toLowerCase();
    document.getElementById('grupoPasswordEmail').classList.toggle('d-none', !cambio);
});

// El teléfono solo acepta dígitos.
document.getElementById('perfil_telefono').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '');
});

document.getElementById('formPerfil').addEventListener('submit', async (evento) => {
    evento.preventDefault();
    limpiarAlerta('cuentaAlert');

    const restaurar = bloquearBoton(document.getElementById('btnGuardarPerfil'), 'Guardando...');
    const respuesta = await apiClient.post('update_perfil.php', Object.fromEntries(new FormData(evento.target)));
    restaurar();

    if (respuesta?.status === 'success') {
        mostrarAlerta('cuentaAlert', 'success', respuesta.message);
        campoEmail.dataset.original = campoEmail.value.trim();
        document.getElementById('perfil_password').value = '';
        document.getElementById('grupoPasswordEmail').classList.add('d-none');
    } else {
        mostrarAlerta('cuentaAlert', 'danger', mensajeDeError(respuesta));
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

// === CAMBIO DE CONTRASEÑA ===
document.getElementById('formPassword').addEventListener('submit', async (evento) => {
    evento.preventDefault();
    limpiarAlerta('cuentaAlert');

    const restaurar = bloquearBoton(document.getElementById('btnGuardarPassword'), 'Enviando...');
    const respuesta = await apiClient.post('solicitar_cambio_password.php', Object.fromEntries(new FormData(evento.target)));
    restaurar();

    if (respuesta?.status === 'success') {
        mostrarAlerta('cuentaAlert', 'success', respuesta.message);
        evento.target.reset();
    } else {
        mostrarAlerta('cuentaAlert', 'danger', mensajeDeError(respuesta));
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

// === PREFERENCIA DE NOTIFICACIONES ===
document.getElementById('chkNotificaciones').addEventListener('change', async function () {
    this.disabled = true;
    const respuesta = await apiClient.post('update_perfil.php', { recibir_notificaciones: this.checked ? 1 : 0 });

    if (respuesta?.status === 'success') {
        mostrarAlerta('notificacionesAlert', 'success', 'Preferencia actualizada.');
    } else {
        this.checked = !this.checked; // Se revierte el interruptor si el cambio no se guardó.
        mostrarAlerta('notificacionesAlert', 'danger', mensajeDeError(respuesta));
    }
    this.disabled = false;
});

// === ELIMINAR CUENTA ===
document.getElementById('chkEntiendo').addEventListener('change', function () {
    document.getElementById('btnConfirmarEliminar').disabled = !this.checked;
});

document.getElementById('btnConfirmarEliminar').addEventListener('click', async (evento) => {
    limpiarAlerta('deleteCuentaAlert');
    const restaurar = bloquearBoton(evento.currentTarget, 'Eliminando...');

    const respuesta = await apiClient.post('delete_cuenta.php', {
        confirmar: true,
        password: document.getElementById('eliminar_password').value,
    });

    if (respuesta?.status === 'success') {
        window.location.href = respuesta.redirect || 'index.php';
        return;
    }

    mostrarAlerta('deleteCuentaAlert', 'danger', mensajeDeError(respuesta));
    restaurar();
});
</script>

<?php require __DIR__ . '/../src/views/layout/footer.php'; ?>
