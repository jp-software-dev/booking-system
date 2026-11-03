<?php
/**
 * PARCIAL: MODAL PARA EMITIR UNA RECETA (DOCTOR)
 *
 * Lo abre cualquier botón con data-recetar="<id_cita>" (ver public/assets/js/receta.js).
 * Cada medicamento se agrega desde la plantilla #plantillaMedicamento y muestra
 * en vivo cuántas tomas tendrá y a qué horas.
 */

use App\Models\Tratamiento;

$opcionesFrecuencia = array_map(
    static fn (int $horas): array => ['valor' => $horas, 'texto' => ucfirst(Tratamiento::describirFrecuencia($horas))],
    Tratamiento::FRECUENCIAS
);
?>
<div class="modal fade" id="modalEmitirReceta" tabindex="-1" aria-labelledby="modalEmitirRecetaTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="formEmitirReceta" novalidate>
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5" id="modalEmitirRecetaTitulo">Nueva receta</h2>
                        <div class="small text-secondary" id="recetaPaciente"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_cita" id="recetaIdCita">
                    <div id="recetaAlerta"></div>

                    <div class="mb-3">
                        <label for="recetaDiagnostico" class="form-label">Diagnóstico</label>
                        <input type="text" class="form-control" id="recetaDiagnostico" name="diagnostico" maxlength="255" required placeholder="Ej. Faringoamigdalitis bacteriana">
                    </div>
                    <div class="mb-4">
                        <label for="recetaIndicaciones" class="form-label">Indicaciones generales <span class="fw-semibold text-muted">(opcional)</span></label>
                        <textarea class="form-control" id="recetaIndicaciones" name="indicaciones" rows="2" maxlength="2000" placeholder="Ej. Tomar con alimentos, abundantes líquidos, reposo relativo"></textarea>
                    </div>

                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h3 class="fs-6 fw-bold mb-0">Medicamentos</h3>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnAgregarMedicamento"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Agregar medicamento</button>
                    </div>
                    <p class="small text-secondary mb-3">Al emitir la receta, cada toma queda programada en el pastillero del paciente y le llega un aviso a su hora.</p>
                    <div id="listaMedicamentos" class="d-flex flex-column gap-3"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnEmitirReceta">Emitir receta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Plantilla de un medicamento (la clona receta.js). -->
<template id="plantillaMedicamento">
    <fieldset class="ma-medicamento rounded-4 p-3" style="background: var(--ma-superficie-2);">
        <legend class="visually-hidden">Medicamento</legend>
        <div class="row g-2">
            <div class="col-md-6">
                <label class="form-label small mb-1">Medicamento</label>
                <input type="text" class="form-control" data-campo="medicamento" maxlength="120" required placeholder="Ej. Amoxicilina">
            </div>
            <div class="col-md-6">
                <label class="form-label small mb-1">Dosis</label>
                <input type="text" class="form-control" data-campo="dosis" maxlength="120" required placeholder="Ej. 1 cápsula de 500 mg">
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label small mb-1">Frecuencia</label>
                <select class="form-select" data-campo="frecuencia_horas">
                    <?php foreach ($opcionesFrecuencia as $opcion): ?>
                        <option value="<?= (int) $opcion['valor'] ?>"<?= $opcion['valor'] === 8 ? ' selected' : '' ?>><?= e($opcion['texto']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Durante (días)</label>
                <input type="number" class="form-control" data-campo="duracion_dias" min="1" max="<?= Tratamiento::DURACION_MAXIMA_DIAS ?>" value="7" required>
            </div>
            <div class="col-md-5">
                <label class="form-label small mb-1">Primera toma</label>
                <input type="datetime-local" class="form-control" data-campo="fecha_inicio" required>
            </div>
            <div class="col-12">
                <input type="text" class="form-control form-control-sm" data-campo="notas" maxlength="255" placeholder="Nota para el paciente (opcional), ej. después de comer">
            </div>
        </div>
        <div class="d-flex align-items-center justify-content-between gap-2 mt-2">
            <p class="small fw-semibold text-secondary mb-0" data-vista-previa aria-live="polite"></p>
            <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none p-0" data-quitar-medicamento>Quitar</button>
        </div>
    </fieldset>
</template>
