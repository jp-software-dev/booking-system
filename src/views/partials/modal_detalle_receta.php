<?php
/**
 * PARCIAL: MODAL CON EL DETALLE DE UNA RECETA
 *
 * Lo abre cualquier botón con data-ver-receta="<id_receta>" (ver public/assets/js/receta.js).
 * Lo usan el médico (panel e historial de recetas) y el paciente (Mi semana).
 * Mientras llegan los datos se muestra un esqueleto de carga.
 */
?>
<div class="modal fade" id="modalDetalleReceta" tabindex="-1" aria-labelledby="modalDetalleRecetaTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5" id="modalDetalleRecetaTitulo">Receta</h2>
                    <div class="small text-secondary ma-cifras" id="detalleRecetaSubtitulo"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="detalleRecetaCuerpo" aria-live="polite"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                <a class="btn btn-primary" id="detalleRecetaPdf" href="#"><i class="bi bi-download me-1" aria-hidden="true"></i>Descargar PDF</a>
            </div>
        </div>
    </div>
</div>
