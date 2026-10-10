<?php

/**
 * VISTA: EQUIPO MÉDICO Y ENFERMERÍA (ADMIN)
 *
 * Directorio del personal con alta, edición, detalle y baja de médicos y
 * enfermeros.
 *
 * Acceso: administrador.
 */

require_once __DIR__ . '/../config/config.php';

use App\Http\Sesion;
use App\Models\Consultorio;
use App\Models\Especialidad;
use App\Models\Personal;

Sesion::exigirRol(Sesion::ROL_ADMIN);

// === DATOS ===
$equipo = (new Personal())->listarEquipoCompleto();
$especialidades = (new Especialidad())->listar();
$consultorios = (new Consultorio())->listarTodos();
$nombresConsultorio = array_column($consultorios, 'nombre', 'id_consultorio');

$doctores = array_filter($equipo, static fn (array $m): bool => $m['puesto'] === 'Doctor');
$enfermeros = array_filter($equipo, static fn (array $m): bool => $m['puesto'] === 'Enfermero');

/**
 * Dibuja la fila de un miembro del personal.
 *
 * @param array<string, mixed> $miembro Fila de Personal::listarEquipoCompleto().
 */
$filaPersonal = static function (array $miembro) use ($nombresConsultorio): void {
    $esDoctor = $miembro['puesto'] === 'Doctor';
    $nombreCompleto = $miembro['nombre'] . ' ' . $miembro['apellido_paterno'];
    $idConsultorio = $miembro['id_consultorio'] !== null ? (int) $miembro['id_consultorio'] : null;
    $consultorio = $idConsultorio !== null ? ($nombresConsultorio[$idConsultorio] ?? '#' . $idConsultorio) : 'Sin asignar';
    ?>
    <tr>
        <td class="ps-4 py-3" data-label="Nombre">
            <div class="d-flex align-items-center">
                <div class="bg-light rounded-circle p-2 me-3 text-center" style="width: 40px; height: 40px;">
                    <i class="bi <?= $esDoctor ? 'bi-heart-pulse-fill text-primary' : 'bi-bandaid-fill text-info' ?>"></i>
                </div>
                <span class="fw-bold text-dark"><?= e($nombreCompleto) ?></span>
            </div>
        </td>
        <td data-label="Puesto"><span class="badge rounded-pill <?= $esDoctor ? 'bg-primary' : 'bg-info text-dark' ?> px-3"><?= e($miembro['puesto']) ?></span></td>
        <td class="text-secondary" data-label="Especialidad / turno"><?= e($miembro['detalle']) ?></td>
        <td class="text-center" data-label="Consultorio">
            <?php if ($idConsultorio !== null): ?>
                <span class="fw-bold text-dark-blue">#<?= $idConsultorio ?></span>
            <?php else: ?>
                <span class="text-muted fst-italic">N/A</span>
            <?php endif; ?>
        </td>
        <td class="pe-4 text-end">
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 me-1" title="Ver detalle" data-ver-personal
                    data-nombre-completo="<?= e($nombreCompleto) ?>"
                    data-puesto="<?= e($miembro['puesto']) ?>"
                    data-especialidad="<?= e($esDoctor ? $miembro['detalle'] : 'N/A') ?>"
                    data-turno="<?= e($esDoctor ? 'N/A' : $miembro['detalle']) ?>"
                    data-consultorio="<?= e($consultorio) ?>">
                <i class="bi bi-eye-fill"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1" title="Editar" data-editar-personal
                    data-id="<?= (int) $miembro['id'] ?>"
                    data-tipo="<?= $esDoctor ? 'doctor' : 'enfermero' ?>"
                    data-nombre="<?= e($miembro['nombre']) ?>"
                    data-apellido="<?= e($miembro['apellido_paterno']) ?>"
                    data-especialidad="<?= e($miembro['id_especialidad']) ?>"
                    data-turno="<?= e($miembro['turno']) ?>"
                    data-consultorio="<?= e($idConsultorio) ?>">
                <i class="bi bi-pencil-square me-1"></i>Editar
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" data-baja-personal
                    data-id="<?= (int) $miembro['id'] ?>" data-tipo="<?= $esDoctor ? 'doctor' : 'enfermero' ?>" data-nombre="<?= e($nombreCompleto) ?>">
                <i class="bi bi-person-x-fill me-1"></i>Baja
            </button>
        </td>
    </tr>
    <?php
};

/**
 * Dibuja la tabla de una sección del equipo (médicos o enfermeros).
 *
 * @param list<array<string, mixed>> $miembros Personal de la sección.
 * @param string                     $vacio    Texto cuando no hay registros.
 */
$tablaPersonal = static function (array $miembros, string $vacio) use ($filaPersonal): void {
    ?>
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
                <?php foreach ($miembros as $miembro) { $filaPersonal($miembro); } ?>
                <?php if ($miembros === []): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4"><?= e($vacio) ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
};

/**
 * Opciones de un <select> de consultorios.
 */
$opcionesConsultorio = static function () use ($consultorios): void {
    ?>
    <option value="">Sin asignar / Disponibilidad flotante</option>
    <?php foreach ($consultorios as $consultorio): ?>
        <option value="<?= (int) $consultorio['id_consultorio'] ?>"><?= e($consultorio['nombre']) ?></option>
    <?php endforeach;
};

/**
 * Opciones de un <select> de especialidades.
 */
$opcionesEspecialidad = static function () use ($especialidades): void {
    foreach ($especialidades as $especialidad): ?>
        <option value="<?= (int) $especialidad['id_especialidad'] ?>"><?= e($especialidad['nombre_especialidad']) ?></option>
    <?php endforeach;
};

// === VISTA ===
require __DIR__ . '/../src/views/layout/header.php';
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold text-dark-blue mb-0"><i class="bi bi-people-fill text-primary me-2"></i>Equipo Médico y Enfermería</h2>
            <p class="text-secondary mb-0 mt-1">Directorio y control del personal del hospital.</p>
        </div>
        <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalPersonal">
            <i class="bi bi-person-plus-fill me-2"></i>Dar de alta
        </button>
    </div>

    <div id="equipoAlert"></div>

    <h5 class="fw-bold text-dark-blue mb-3"><i class="bi bi-heart-pulse-fill text-primary me-2"></i>Equipo de Doctores</h5>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <?php $tablaPersonal(array_values($doctores), 'No hay doctores registrados.'); ?>
    </div>

    <h5 class="fw-bold text-dark-blue mb-3"><i class="bi bi-bandaid-fill text-info me-2"></i>Equipo de Enfermeros</h5>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <?php $tablaPersonal(array_values($enfermeros), 'No hay enfermeros registrados.'); ?>
    </div>
</div>

<!-- === MODAL: ALTA DE PERSONAL === -->
<div class="modal fade" id="modalPersonal" tabindex="-1" aria-labelledby="modalPersonalTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold" id="modalPersonalTitulo"><i class="bi bi-person-plus-fill me-2"></i> Alta de Personal</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formAltaPersonal">
                <div class="modal-body p-4">
                    <div id="alertaAltaPersonal"></div>

                    <div class="mb-3">
                        <label for="alta_tipo" class="form-label fw-bold text-secondary small">Tipo de personal</label>
                        <select name="tipo" id="alta_tipo" class="form-select" required>
                            <option value="doctor">Doctor(a)</option>
                            <option value="enfermero">Enfermero(a)</option>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label for="alta_nombre" class="form-label fw-bold text-secondary small">Nombre(s)</label>
                            <input type="text" name="nombre" id="alta_nombre" class="form-control" maxlength="50" required>
                        </div>
                        <div class="col-md-6">
                            <label for="alta_apellido" class="form-label fw-bold text-secondary small">Apellido Paterno</label>
                            <input type="text" name="apellido_paterno" id="alta_apellido" class="form-control" maxlength="50" required>
                        </div>
                    </div>

                    <div class="mb-3" id="alta_campo_especialidad">
                        <label for="alta_especialidad" class="form-label fw-bold text-secondary small">Especialidad</label>
                        <select name="id_especialidad" id="alta_especialidad" class="form-select"><?php $opcionesEspecialidad(); ?></select>
                    </div>

                    <div class="mb-3 d-none" id="alta_campo_turno">
                        <label for="alta_turno" class="form-label fw-bold text-secondary small">Turno</label>
                        <select name="turno" id="alta_turno" class="form-select">
                            <?php foreach (Personal::TURNOS as $turno): ?>
                                <option value="<?= e($turno) ?>"><?= e($turno) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="alta_consultorio" class="form-label fw-bold text-secondary small">Consultorio asignado (opcional)</label>
                        <select name="id_consultorio" id="alta_consultorio" class="form-select"><?php $opcionesConsultorio(); ?></select>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">Guardar Miembro</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- === MODAL: EDITAR PERSONAL === -->
<div class="modal fade" id="modalEditarPersonal" tabindex="-1" aria-labelledby="modalEditarPersonalTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold" id="modalEditarPersonalTitulo"><i class="bi bi-pencil-square me-2"></i> Editar Personal</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formEditarPersonal">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="tipo" id="edit_tipo">
                <div class="modal-body p-4">
                    <div id="alertaEditarPersonal"></div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label for="edit_nombre" class="form-label fw-bold text-secondary small">Nombre(s)</label>
                            <input type="text" name="nombre" id="edit_nombre" class="form-control" maxlength="50" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_apellido" class="form-label fw-bold text-secondary small">Apellido Paterno</label>
                            <input type="text" name="apellido_paterno" id="edit_apellido" class="form-control" maxlength="50" required>
                        </div>
                    </div>

                    <div class="mb-3" id="edit_campo_especialidad">
                        <label for="edit_especialidad" class="form-label fw-bold text-secondary small">Especialidad</label>
                        <select name="id_especialidad" id="edit_especialidad" class="form-select"><?php $opcionesEspecialidad(); ?></select>
                    </div>

                    <div class="mb-3 d-none" id="edit_campo_turno">
                        <label for="edit_turno" class="form-label fw-bold text-secondary small">Turno</label>
                        <select name="turno" id="edit_turno" class="form-select">
                            <?php foreach (Personal::TURNOS as $turno): ?>
                                <option value="<?= e($turno) ?>"><?= e($turno) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="edit_consultorio" class="form-label fw-bold text-secondary small">Consultorio asignado (opcional)</label>
                        <select name="id_consultorio" id="edit_consultorio" class="form-select"><?php $opcionesConsultorio(); ?></select>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- === MODAL: DETALLE DEL PERSONAL === -->
<div class="modal fade" id="modalDetallePersonal" tabindex="-1" aria-labelledby="modalDetallePersonalTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold" id="modalDetallePersonalTitulo"><i class="bi bi-person-lines-fill me-2"></i>Detalle del Personal</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-12"><span class="text-secondary small d-block">Nombre completo</span><span class="fw-medium" id="det_nombreCompleto"></span></div>
                    <div class="col-md-6"><span class="text-secondary small d-block">Puesto</span><span class="fw-medium" id="det_puesto"></span></div>
                    <div class="col-md-6"><span class="text-secondary small d-block">Estado</span><span class="fw-medium">Activo</span></div>
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
/** Obtiene (o crea) la instancia de un modal. Bootstrap se carga al final de la página. */
const modal = (id) => bootstrap.Modal.getOrCreateInstance(document.getElementById(id));

/** Muestra el campo de especialidad (médico) o de turno (enfermero) según el tipo. */
function alternarCamposPorTipo(prefijo, tipo) {
    const esDoctor = tipo === 'doctor';
    document.getElementById(`${prefijo}_campo_especialidad`).classList.toggle('d-none', !esDoctor);
    document.getElementById(`${prefijo}_campo_turno`).classList.toggle('d-none', esDoctor);
}

/** Envía un formulario de personal y recarga la página si se guardó. */
async function enviarFormularioPersonal(formulario, endpoint, contenedorAlerta) {
    const restaurar = bloquearBoton(formulario.querySelector('button[type="submit"]'), 'Guardando...');
    const respuesta = await apiClient.post(endpoint, Object.fromEntries(new FormData(formulario)));

    if (respuesta?.status === 'success') {
        location.reload();
        return;
    }

    mostrarAlerta(contenedorAlerta, 'danger', mensajeDeError(respuesta));
    restaurar();
}

// === ALTA ===
document.getElementById('alta_tipo').addEventListener('change', (evento) => alternarCamposPorTipo('alta', evento.target.value));

document.getElementById('formAltaPersonal').addEventListener('submit', (evento) => {
    evento.preventDefault();
    enviarFormularioPersonal(evento.target, 'add_personal.php', 'alertaAltaPersonal');
});

// === DETALLE ===
document.querySelectorAll('[data-ver-personal]').forEach((boton) => {
    boton.addEventListener('click', () => {
        ['nombreCompleto', 'puesto', 'especialidad', 'turno', 'consultorio'].forEach((campo) => {
            document.getElementById(`det_${campo}`).textContent = boton.dataset[campo];
        });
        modal('modalDetallePersonal').show();
    });
});

// === EDICIÓN ===
document.querySelectorAll('[data-editar-personal]').forEach((boton) => {
    boton.addEventListener('click', () => {
        const { id, tipo, nombre, apellido, especialidad, turno, consultorio } = boton.dataset;

        document.getElementById('edit_id').value = id;
        document.getElementById('edit_tipo').value = tipo;
        document.getElementById('edit_nombre').value = nombre;
        document.getElementById('edit_apellido').value = apellido;
        document.getElementById('edit_consultorio').value = consultorio || '';
        document.getElementById('edit_especialidad').value = especialidad || '';
        document.getElementById('edit_turno').value = turno || 'Matutino';
        alternarCamposPorTipo('edit', tipo);

        limpiarAlerta('alertaEditarPersonal');
        modal('modalEditarPersonal').show();
    });
});

document.getElementById('formEditarPersonal').addEventListener('submit', (evento) => {
    evento.preventDefault();
    enviarFormularioPersonal(evento.target, 'update_personal.php', 'alertaEditarPersonal');
});

// === BAJA ===
document.querySelectorAll('[data-baja-personal]').forEach((boton) => {
    boton.addEventListener('click', async () => {
        const { id, tipo, nombre } = boton.dataset;
        if (!confirm(`¿Seguro que deseas dar de baja a ${nombre}?`)) {
            return;
        }

        const respuesta = await apiClient.post('delete_personal.php', { id: Number(id), tipo });
        if (respuesta?.status === 'success') {
            location.reload();
            return;
        }
        mostrarAlerta('equipoAlert', 'danger', mensajeDeError(respuesta));
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});
</script>

<?php require __DIR__ . '/../src/views/layout/footer.php'; ?>
