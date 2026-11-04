<?php

/**
 * VISTA: HISTORIAL DE CITAS DEL PACIENTE
 *
 * Dos secciones:
 *   - Próximas citas activas: el paciente puede reprogramarlas o cancelarlas
 *     (si están pendientes o confirmadas).
 *   - Atenciones pasadas (completadas o canceladas): puede quitarlas de su vista.
 *
 * Acceso: paciente.
 */

require_once __DIR__ . '/../config/config.php';

use App\Helpers\AppointmentValidator;
use App\Helpers\Horario;
use App\Http\Sesion;
use App\Models\Cita;

Sesion::exigirRol(Sesion::ROL_PACIENTE);

// === DATOS ===
$citas = (new Cita())->listarPorPaciente((int) Sesion::usuarioId(), false);

$citasActivas = array_filter($citas, static fn (array $c): bool => in_array($c['estado_cita'], Cita::ESTADOS_ACTIVOS, true));
$citasPasadas = array_filter($citas, static fn (array $c): bool => !in_array($c['estado_cita'], Cita::ESTADOS_ACTIVOS, true));

// === VISTA ===
$tituloPagina = 'Mis citas';
require __DIR__ . '/../src/views/layout/header.php';
?>
<div class="container ma-pagina">
    <div class="ma-encabezado-pagina">
        <div>
            <h1 class="ma-titulo">Mis citas</h1>
            <p class="ma-subtitulo"><?= count($citasActivas) ?> próxima(s) · <?= count($citasPasadas) ?> en tu historial</p>
        </div>
        <a href="agenda.php" class="btn btn-primary"><i class="bi bi-calendar-plus me-1" aria-hidden="true"></i>Agendar una cita</a>
    </div>

    <div id="historialAlert"></div>

    <!-- === PRÓXIMAS CITAS === -->
    <section class="ma-panel mb-4" aria-labelledby="tituloProximas">
        <h2 class="ma-panel-titulo" id="tituloProximas">Próximas</h2>
        <?php if ($citasActivas === []): ?>
            <div class="ma-vacio">
                <i class="bi bi-calendar2-plus" aria-hidden="true"></i>
                <strong>No tienes citas programadas</strong>
                Agenda una cuando la necesites.
            </div>
        <?php endif; ?>
        <ul class="list-unstyled mb-0">
            <?php foreach ($citasActivas as $cita): ?>
                <?php $modificable = in_array($cita['estado_cita'], Cita::ESTADOS_MODIFICABLES, true); ?>
                <li class="d-flex flex-wrap align-items-center gap-3 py-3 border-bottom">
                    <span class="ma-fecha"><b><?= e(date('j', strtotime((string) $cita['fecha_cita']))) ?></b><small><?= e(mes_corto((string) $cita['fecha_cita'])) ?></small></span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-bold"><?= e(ucfirst(fecha_larga((string) $cita['fecha_cita']))) ?> · <?= hora_corta($cita['hora_inicio']) ?></div>
                        <div class="small text-secondary">Dr(a). <?= e($cita['doctor_nombre'] . ' ' . $cita['doctor_apellido']) ?> · <?= e($cita['consultorio_nombre'] ?? 'Consultorio por asignar') ?></div>
                        <div class="small text-secondary text-truncate" style="max-width: 60ch;" title="<?= e($cita['motivo_consulta']) ?>"><?= e($cita['motivo_consulta']) ?></div>
                    </div>
                    <span class="badge <?= clase_estado_cita($cita['estado_cita']) ?>"><?= e($cita['estado_cita']) ?></span>
                    <?php if ($modificable): ?>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-light" data-accion="reprogramar"
                                    data-id="<?= (int) $cita['id_cita'] ?>"
                                    data-fecha="<?= e($cita['fecha_cita']) ?>"
                                    data-hora="<?= e(substr($cita['hora_inicio'], 0, 5)) ?>"
                                    data-motivo="<?= e($cita['motivo_consulta']) ?>">Reprogramar</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-accion="cancelar" data-id="<?= (int) $cita['id_cita'] ?>">Cancelar</button>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <!-- === HISTORIAL (COMPLETADAS Y CANCELADAS) === -->
    <section class="ma-panel" aria-labelledby="tituloHistorial">
        <h2 class="ma-panel-titulo" id="tituloHistorial">Historial</h2>
        <?php if ($citasPasadas === []): ?>
            <p class="text-secondary mb-0">Aquí aparecerán tus citas completadas y canceladas.</p>
        <?php endif; ?>
        <ul class="list-unstyled mb-0">
            <?php foreach ($citasPasadas as $cita): ?>
                <li class="d-flex flex-wrap align-items-center gap-3 py-3 border-bottom">
                    <span class="ma-fecha" style="background: var(--ma-superficie-2); color: var(--ma-tinta-2);"><b><?= e(date('j', strtotime((string) $cita['fecha_cita']))) ?></b><small><?= e(mes_corto((string) $cita['fecha_cita'])) ?></small></span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-bold"><?= e(ucfirst(fecha_larga((string) $cita['fecha_cita'], true))) ?> · <?= hora_corta($cita['hora_inicio']) ?></div>
                        <div class="small text-secondary">Dr(a). <?= e($cita['doctor_nombre'] . ' ' . $cita['doctor_apellido']) ?> · <span class="text-truncate"><?= e($cita['motivo_consulta']) ?></span></div>
                    </div>
                    <span class="badge <?= clase_estado_cita($cita['estado_cita']) ?>"><?= e($cita['estado_cita']) ?></span>
                    <button type="button" class="btn btn-sm btn-light" aria-label="Quitar de mi historial la cita del <?= e(fecha_corta($cita['fecha_cita'])) ?>" title="Quitar de mi historial" data-accion="ocultar" data-id="<?= (int) $cita['id_cita'] ?>">
                        <i class="bi bi-eye-slash" aria-hidden="true"></i>
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>

<!-- === MODAL: REPROGRAMAR CITA === -->
<div class="modal fade" id="modalReprogramar" tabindex="-1" aria-labelledby="modalReprogramarTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-primary text-white rounded-top-4">
                <h2 class="modal-title fs-5" id="modalReprogramarTitulo">Reprogramar cita</h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formReprogramar">
                <input type="hidden" name="id_cita" id="rep_id_cita">
                <div class="modal-body p-4">
                    <div id="reprogramarAlert"></div>
                    <p class="small text-secondary">Al reprogramar, la cita vuelve a quedar pendiente de confirmación.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="rep_fecha" class="form-label fw-bold text-secondary small">Nueva fecha</label>
                            <input type="date" name="fecha_cita" id="rep_fecha" class="form-control" min="<?= e(date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="rep_hora" class="form-label fw-bold text-secondary small">Nueva hora</label>
                            <select name="hora_inicio" id="rep_hora" class="form-select" required>
                                <?php foreach (Horario::slots() as $slot): ?>
                                    <option value="<?= e($slot) ?>"><?= hora_corta($slot) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="rep_motivo" class="form-label fw-bold text-secondary small">Motivo de la consulta</label>
                            <textarea name="motivo" id="rep_motivo" class="form-control" rows="3" maxlength="<?= AppointmentValidator::MAX_MOTIVO ?>"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- === MODAL DE CONFIRMACIÓN (cancelar cita / quitar del historial) === -->
<div class="modal fade" id="modalConfirmar" tabindex="-1" aria-labelledby="modalConfirmarTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-danger text-white rounded-top-4">
                <h5 class="modal-title fw-bold" id="modalConfirmarTitulo"><i class="bi bi-exclamation-triangle-fill me-2"></i><span id="confirmarTitulo"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <p class="fw-medium mb-0" id="confirmarTexto"></p>
                <div id="confirmarAlert" class="mt-3"></div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Volver</button>
                <button type="button" class="btn btn-danger fw-bold" id="btnConfirmarAccion">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<script>
// === CONFIGURACIÓN DE LAS ACCIONES QUE PIDEN CONFIRMACIÓN ===
const accionesConfirmables = {
    cancelar: {
        titulo: 'Cancelar cita',
        texto: '¿Seguro que deseas cancelar esta cita? El horario quedará libre para otros pacientes.',
        endpoint: 'cancel_cita_user.php',
    },
    ocultar: {
        titulo: 'Quitar del historial',
        texto: 'El registro dejará de aparecer en tu historial. ¿Deseas continuar?',
        endpoint: 'delete_historial_paciente.php',
    },
};

let accionPendiente = null; // { endpoint, idCita }

// Los modales se obtienen al usarlos: Bootstrap se carga al final de la página (footer.php).
const modal = (id) => bootstrap.Modal.getOrCreateInstance(document.getElementById(id));

// === BOTONES DE LAS TABLAS ===
document.querySelectorAll('[data-accion]').forEach((boton) => {
    boton.addEventListener('click', () => {
        const { accion, id } = boton.dataset;

        if (accion === 'reprogramar') {
            document.getElementById('rep_id_cita').value = id;
            document.getElementById('rep_fecha').value = boton.dataset.fecha;
            document.getElementById('rep_hora').value = boton.dataset.hora;
            document.getElementById('rep_motivo').value = boton.dataset.motivo;
            limpiarAlerta('reprogramarAlert');
            modal('modalReprogramar').show();
            return;
        }

        const configuracion = accionesConfirmables[accion];
        accionPendiente = { endpoint: configuracion.endpoint, idCita: Number(id) };
        document.getElementById('confirmarTitulo').textContent = configuracion.titulo;
        document.getElementById('confirmarTexto').textContent = configuracion.texto;
        limpiarAlerta('confirmarAlert');
        modal('modalConfirmar').show();
    });
});

// === REPROGRAMAR ===
document.getElementById('formReprogramar').addEventListener('submit', async (evento) => {
    evento.preventDefault();

    const restaurar = bloquearBoton(evento.target.querySelector('button[type="submit"]'), 'Guardando...');
    const respuesta = await apiClient.post('update_cita_user.php', Object.fromEntries(new FormData(evento.target)));

    if (respuesta?.status === 'success') {
        location.reload();
        return;
    }

    mostrarAlerta('reprogramarAlert', 'danger', mensajeDeError(respuesta));
    restaurar();
});

// === CANCELAR / QUITAR DEL HISTORIAL ===
document.getElementById('btnConfirmarAccion').addEventListener('click', async (evento) => {
    if (!accionPendiente) {
        return;
    }

    const restaurar = bloquearBoton(evento.currentTarget, 'Procesando...');
    const respuesta = await apiClient.post(accionPendiente.endpoint, { id_cita: accionPendiente.idCita });

    if (respuesta?.status === 'success') {
        location.reload();
        return;
    }

    mostrarAlerta('confirmarAlert', 'danger', mensajeDeError(respuesta));
    restaurar();
});
</script>

<?php require __DIR__ . '/../src/views/layout/footer.php'; ?>
