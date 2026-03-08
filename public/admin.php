<?php
// PANEL ADMINISTRATIVO: Interfaz centralizada para el control total de la agenda médica, permitiendo gestionar estados, editar horarios y eliminar registros.

// INICIALIZACIÓN: Reanuda la sesión para validar la identidad del administrador antes de cargar datos sensibles.
session_start();

// CONTROL DE ACCESO: Verifica estrictamente que el usuario tenga una sesión activa y posea el rol de 'admin' para evitar accesos no autorizados.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// DEPENDENCIAS: Carga la conexión a la base de datos y la cabecera visual del panel.
require_once '../config/Database.php';
require_once '../src/views/layout/header.php';

// CONEXIÓN DB: Obtiene la instancia única activa de la base de datos mediante el patrón Singleton.
$db = Database::getInstance();

// CONSULTA MAESTRA: Extrae el registro histórico de citas vinculando pacientes y doctores (JOIN) para mostrar información humana en lugar de IDs.
$query = "SELECT c.id_cita, p.nombre AS paciente, d.nombre AS doctor, d.apellido_paterno AS doctor_ap,
                 c.fecha_cita, c.hora_inicio, c.estado_cita
          FROM citas c
          JOIN pacientes p ON c.id_paciente = p.id_paciente
          JOIN doctores d ON c.id_doctor = d.id_doctor
          ORDER BY c.fecha_cita DESC, c.hora_inicio DESC";
$stmt = $db->query($query);
$citas = $stmt->fetchAll();

// MÉTRICAS EN TIEMPO REAL: Calcula el volumen total de registros y filtra las citas en espera para actualizar los contadores superiores.
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
                    <span class="text-secondary small fw-bold me-2">TOTAL:</span>
                    <span id="total-count" class="badge bg-primary rounded-pill fs-6"><?php echo $total; ?></span>
                </div>
                <div class="bg-white border rounded-pill px-4 py-2 shadow-sm">
                    <span class="text-secondary small fw-bold me-2">PENDIENTES:</span>
                    <span id="pending-count" class="badge bg-warning text-dark rounded-pill fs-6"><?php echo $pendientes; ?></span>
                </div>
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
                        <?php 
                        // ITERACIÓN DE REGISTROS: Genera dinámicamente cada fila de la tabla basándose en la información recuperada de la base de datos.
                        foreach ($citas as $cita):
                            $estado = $cita['estado_cita'];
                            // ASIGNACIÓN VISUAL: Utiliza la función match de PHP 8 para asignar clases de color de Bootstrap según el estado de la cita.
                            $claseEstado = match($estado) {
                                'Confirmada' => 'bg-success text-white',
                                'Cancelada' => 'bg-danger text-white',
                                'Completada' => 'bg-primary text-white',
                                default => 'bg-warning text-dark'
                            };
                        ?>
                        <tr id="row-<?php echo $cita['id_cita']; ?>">
                            <td class="ps-4 fw-bold">#<?php echo $cita['id_cita']; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="bg-light rounded-circle p-2 me-2">
                                        <i class="bi bi-person text-secondary"></i>
                                    </div>
                                    <?php 
                                    // CAPITALIZACIÓN: Convierte el nombre del paciente a mayúsculas y aplica sanitización contra ataques XSS.
                                    echo htmlspecialchars(strtoupper($cita['paciente'])); 
                                    ?>
                                </div>
                            </td>
                            <td class="text-secondary">Dr. <?php echo htmlspecialchars($cita['doctor'] . ' ' . $cita['doctor_ap']); ?></td>
                            <td>
                                <div><i class="bi bi-calendar-event me-1 text-primary"></i> <?php echo $cita['fecha_cita']; ?></div>
                                <small class="text-secondary"><i class="bi bi-clock me-1"></i> <?php echo date('h:i A', strtotime($cita['hora_inicio'])); ?></small>
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
                                <button class="btn btn-primary btn-sm rounded-circle me-1" 
                                        style="width: 38px; height: 38px; padding: 0; display: inline-flex; align-items: center; justify-content: center;"
                                        onclick="abrirModalEditar(<?php echo $cita['id_cita']; ?>, '<?php echo $cita['fecha_cita']; ?>', '<?php echo $cita['hora_inicio']; ?>')" 
                                        title="Editar cita">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                                <button class="btn btn-danger btn-sm rounded-circle" 
                                        style="width: 38px; height: 38px; padding: 0; display: inline-flex; align-items: center; justify-content: center;"
                                        onclick="eliminarCita(<?php echo $cita['id_cita']; ?>)" 
                                        title="Eliminar cita">
                                    <i class="bi bi-trash3-fill"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php 
                        // FALLBACK: Muestra un mensaje amigable en caso de que la consulta no devuelva ninguna cita registrada.
                        if (empty($citas)): 
                        ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-secondary">No hay citas registradas</td>
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
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i> Editar cita</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarCita">
                <div class="modal-body p-4">
                    <input type="hidden" name="id_cita" id="edit_id_cita">
                    <div class="mb-3">
                        <label for="edit_fecha" class="form-label fw-bold text-secondary">Nueva fecha</label>
                        <input type="date" id="edit_fecha" name="fecha_cita" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_hora" class="form-label fw-bold text-secondary">Nueva hora</label>
                        <input type="time" id="edit_hora" name="hora_inicio" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="adminToast" class="toast align-items-center text-white bg-dark border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="toastMessage"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script>
// SISTEMA DE NOTIFICACIONES: Función para renderizar avisos flotantes (toasts) que informan sobre el éxito o fallo de las acciones administrativas.
function mostrarToast(mensaje, tipo = 'success') {
    const toast = document.getElementById('adminToast');
    toast.className = `toast align-items-center text-white border-0 bg-${tipo === 'success' ? 'dark' : 'danger'}`;
    document.getElementById('toastMessage').innerHTML = `<i class="bi bi-${tipo === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i> ${mensaje}`;
    new bootstrap.Toast(toast).show();
}

// SINCRONIZACIÓN DE DATOS: Realiza una petición Fetch para obtener los totales actualizados de la base de datos sin recargar la página.
async function actualizarContadores() {
    try {
        const res = await fetch('../api/get_totales.php');
        const data = await res.json();
        if (data.total !== undefined && data.pendientes !== undefined) {
            document.getElementById('total-count').innerText = data.total;
            document.getElementById('pending-count').innerText = data.pendientes;
        }
    } catch (e) {
        console.error('Error al actualizar contadores:', e);
    }
}

// GESTIÓN DE ESTADOS: Envía el nuevo estado de la cita al servidor y actualiza visualmente la apariencia del selector según el resultado.
async function cambiarEstado(select, id) {
    const nuevoEstado = select.value;
    select.disabled = true;
    try {
        const res = await fetch('../api/update_status.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id_cita: id, estado: nuevoEstado})
        });
        const data = await res.json();
        if (data.status === 'success') {
            mostrarToast('Estado actualizado correctamente');
            select.className = 'form-select form-select-sm w-auto border-0 shadow-sm fw-bold status-dropdown';
            // CAMBIO DE COLOR: Inyecta dinámicamente las clases de Bootstrap según el nuevo estado confirmado por la API.
            if (nuevoEstado === 'Confirmada') select.classList.add('bg-success', 'text-white');
            else if (nuevoEstado === 'Cancelada') select.classList.add('bg-danger', 'text-white');
            else if (nuevoEstado === 'Completada') select.classList.add('bg-primary', 'text-white');
            else select.classList.add('bg-warning', 'text-dark');
            await actualizarContadores();
        } else {
            mostrarToast('Error al actualizar', 'error');
            select.value = select.getAttribute('data-original') || 'Pendiente';
        }
    } catch (e) {
        mostrarToast('Error de conexión', 'error');
    } finally {
        select.disabled = false;
    }
}

// BORRADO ASÍNCRONO: Solicita la eliminación de una cita, ejecuta una animación de salida en la fila y actualiza los totales del dashboard.
async function eliminarCita(id) {
    if (!confirm('¿Eliminar permanentemente esta cita?')) return;
    const row = document.getElementById(`row-${id}`);
    row.style.opacity = '0.5';
    try {
        const res = await fetch('../api/delete_cita.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id_cita: id})
        });
        const data = await res.json();
        if (data.status === 'success') {
            mostrarToast('Cita eliminada');
            // ANIMACIÓN DE SALIDA: Desplaza la fila hacia la derecha antes de eliminar el nodo del DOM definitivamente.
            row.style.transition = 'all 0.3s';
            row.style.transform = 'translateX(100%)';
            setTimeout(() => row.remove(), 300);
            await actualizarContadores();
        } else {
            mostrarToast('No se pudo eliminar', 'error');
            row.style.opacity = '1';
        }
    } catch (e) {
        mostrarToast('Error de conexión', 'error');
        row.style.opacity = '1';
    }
}

// CONTROLADOR DE MODAL: Puebla los campos del formulario de edición con los datos de la cita seleccionada y despliega la ventana emergente.
function abrirModalEditar(id, fecha, hora) {
    document.getElementById('edit_id_cita').value = id;
    document.getElementById('edit_fecha').value = fecha;
    document.getElementById('edit_hora').value = hora;
    new bootstrap.Modal(document.getElementById('modalEditarCita')).show();
}

// PROCESAMIENTO DE EDICIÓN: Envía la nueva fecha y hora a la API de reprogramación administrativa y refresca la vista tras el éxito.
document.getElementById('formEditarCita').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';

    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());

    try {
        const res = await fetch('../api/update_cita_admin.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.status === 'success') {
            // CIERRE Y RECARGA: Oculta el modal, notifica éxito y refresca la página para reflejar los cambios en el listado.
            bootstrap.Modal.getInstance(document.getElementById('modalEditarCita')).hide();
            mostrarToast('Cita actualizada correctamente');
            location.reload();
        } else {
            mostrarToast('Error: ' + (result.message || 'No se pudo actualizar'), 'error');
        }
    } catch (error) {
        mostrarToast('Error de conexión', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Guardar cambios';
    }
});
</script>

<?php 
// PIE DE PÁGINA: Incorpora el layout del footer que cierra las etiquetas del documento y carga librerías externas.
require_once '../src/views/layout/footer.php'; 
?>