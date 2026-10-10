<?php

/**
 * VISTA: ASIGNACIÓN DE CONSULTORIOS (ADMIN)
 *
 * Una tarjeta por consultorio para:
 *   - definir su especialidad predeterminada, y
 *   - asignar un médico (de esa especialidad) o dejarlo libre.
 *
 * Acceso: administrador.
 */

require_once __DIR__ . '/../config/config.php';

use App\Http\Sesion;
use App\Models\Consultorio;
use App\Models\Doctor;
use App\Models\Especialidad;

Sesion::exigirRol(Sesion::ROL_ADMIN);

// === DATOS ===
$consultorios = (new Consultorio())->listarTodos();
$doctores = (new Doctor())->listarActivos();
$especialidades = (new Especialidad())->listar();

/**
 * Médico asignado a un consultorio, o null si está libre.
 *
 * @return array<string, mixed>|null
 */
$medicoAsignado = static function (int $idConsultorio) use ($doctores): ?array {
    foreach ($doctores as $doctor) {
        if ((int) $doctor['id_consultorio'] === $idConsultorio) {
            return $doctor;
        }
    }
    return null;
};

/**
 * Médicos que se pueden elegir para el consultorio: los que no tienen
 * consultorio (o ya están en este) y, si el consultorio tiene especialidad,
 * solo los de esa especialidad.
 *
 * @return list<array<string, mixed>>
 */
$medicosDisponibles = static function (array $consultorio) use ($doctores): array {
    $idConsultorio = (int) $consultorio['id_consultorio'];

    return array_values(array_filter($doctores, static function (array $doctor) use ($consultorio, $idConsultorio): bool {
        $libreOAqui = $doctor['id_consultorio'] === null || (int) $doctor['id_consultorio'] === $idConsultorio;
        $mismaEspecialidad = $consultorio['id_especialidad'] === null
            || (int) $doctor['id_especialidad'] === (int) $consultorio['id_especialidad'];

        return $libreOAqui && $mismaEspecialidad;
    }));
};

// === VISTA ===
require __DIR__ . '/../src/views/layout/header.php';
?>
<div class="container py-5">
    <h2 class="fw-bold text-dark-blue mb-4"><i class="bi bi-door-open-fill text-primary me-2"></i>Asignación de Consultorios</h2>

    <div id="consultoriosAlert"></div>

    <div class="row g-4">
        <?php foreach ($consultorios as $consultorio): ?>
            <?php
            $idConsultorio = (int) $consultorio['id_consultorio'];
            $asignado = $medicoAsignado($idConsultorio);
            ?>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-4 position-relative" data-consultorio="<?= $idConsultorio ?>">
                    <div class="position-absolute top-0 end-0 p-3">
                        <span class="badge rounded-pill <?= $asignado ? 'bg-danger' : 'bg-success' ?>" data-insignia><?= $asignado ? 'Ocupado' : 'Disponible' ?></span>
                    </div>

                    <h4 class="fw-bold text-dark-blue mb-1"><?= e($consultorio['nombre']) ?></h4>

                    <!-- Especialidad predeterminada del consultorio -->
                    <form class="form-especialidad mb-3">
                        <input type="hidden" name="id_consultorio" value="<?= $idConsultorio ?>">
                        <label class="form-label fw-bold text-secondary small mb-1" for="esp_<?= $idConsultorio ?>">Especialidad del consultorio</label>
                        <div class="input-group input-group-sm">
                            <select name="id_especialidad" id="esp_<?= $idConsultorio ?>" class="form-select">
                                <option value="">-- Sin especialidad definida --</option>
                                <?php foreach ($especialidades as $especialidad): ?>
                                    <option value="<?= (int) $especialidad['id_especialidad'] ?>" <?= (int) $consultorio['id_especialidad'] === (int) $especialidad['id_especialidad'] ? 'selected' : '' ?>>
                                        <?= e($especialidad['nombre_especialidad']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-outline-primary">Guardar</button>
                        </div>
                    </form>

                    <div class="bg-light rounded-3 p-3 mb-4">
                        <h6 class="fw-bold text-secondary mb-2 small">MÉDICO ACTUAL</h6>
                        <div class="<?= $asignado ? 'fw-bold text-dark' : 'text-muted' ?>" data-nombre-medico>
                            <?= $asignado ? 'Dr. ' . e($asignado['nombre'] . ' ' . $asignado['apellido_paterno']) : 'Ningún médico asignado' ?>
                        </div>
                    </div>

                    <!-- Asignación de médico -->
                    <form class="form-asignar">
                        <input type="hidden" name="id_consultorio" value="<?= $idConsultorio ?>">
                        <div class="input-group">
                            <select name="id_doctor" class="form-select border-primary" aria-label="Médico asignado a <?= e($consultorio['nombre']) ?>">
                                <option value="">-- Liberar Consultorio --</option>
                                <?php foreach ($medicosDisponibles($consultorio) as $doctor): ?>
                                    <option value="<?= (int) $doctor['id_doctor'] ?>" <?= $asignado && (int) $asignado['id_doctor'] === (int) $doctor['id_doctor'] ? 'selected' : '' ?>>
                                        Dr. <?= e($doctor['nombre'] . ' ' . $doctor['apellido_paterno']) ?>
                                    </option>
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
// === ESPECIALIDAD DEL CONSULTORIO ===
// Al cambiarla, la lista de médicos elegibles cambia, por eso se recarga la página.
document.querySelectorAll('.form-especialidad').forEach((formulario) => {
    formulario.addEventListener('submit', async (evento) => {
        evento.preventDefault();

        const restaurar = bloquearBoton(formulario.querySelector('button[type="submit"]'), 'Guardando...');
        const respuesta = await apiClient.post('update_consultorio_especialidad.php', Object.fromEntries(new FormData(formulario)));

        if (respuesta?.status === 'success') {
            location.reload();
            return;
        }

        mostrarAlerta('consultoriosAlert', 'danger', mensajeDeError(respuesta));
        restaurar();
    });
});

// === ASIGNACIÓN DE MÉDICO ===
// Se actualiza la tarjeta sin recargar la página.
document.querySelectorAll('.form-asignar').forEach((formulario) => {
    formulario.addEventListener('submit', async (evento) => {
        evento.preventDefault();

        const boton = formulario.querySelector('button[type="submit"]');
        const selector = formulario.querySelector('select[name="id_doctor"]');
        const restaurar = bloquearBoton(boton, 'Actualizando...');
        const respuesta = await apiClient.post('assign_consultorio.php', Object.fromEntries(new FormData(formulario)));

        if (respuesta?.status !== 'success') {
            mostrarAlerta('consultoriosAlert', 'danger', mensajeDeError(respuesta));
            restaurar();
            return;
        }

        const tarjeta = formulario.closest('[data-consultorio]');
        const insignia = tarjeta.querySelector('[data-insignia]');
        const nombreMedico = tarjeta.querySelector('[data-nombre-medico]');
        const ocupado = selector.value !== '';

        insignia.className = `badge rounded-pill ${ocupado ? 'bg-danger' : 'bg-success'}`;
        insignia.textContent = ocupado ? 'Ocupado' : 'Disponible';
        nombreMedico.className = ocupado ? 'fw-bold text-dark' : 'text-muted';
        nombreMedico.textContent = ocupado ? selector.selectedOptions[0].textContent.trim() : 'Ningún médico asignado';

        limpiarAlerta('consultoriosAlert');
        boton.classList.replace('btn-primary', 'btn-success');
        boton.textContent = '¡Listo!';
        setTimeout(() => {
            boton.classList.replace('btn-success', 'btn-primary');
            restaurar();
        }, 1500);
    });
});
</script>

<?php require __DIR__ . '/../src/views/layout/footer.php'; ?>
