<div class="modal fade" id="modalVerCita" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            
            <div class="modal-header border-0 bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-eye-fill me-2"></i> Detalles de la Cita</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <form id="formActualizarCita">
                <div class="modal-body p-4">
                    <input type="hidden" name="id_cita" id="ver_id_cita">

                    <div class="mb-3">
                        <label for="ver_titulo" class="form-label fw-bold text-secondary">Paciente</label>
                        <input type="text" id="ver_titulo" class="form-control bg-light border-0" readonly>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="ver_fecha" class="form-label fw-bold text-secondary">Fecha</label>
                            <input type="date" name="fecha_cita" id="ver_fecha" class="form-control bg-light border-0" required>
                        </div>
                        <div class="col-md-6">
                            <label for="ver_hora" class="form-label fw-bold text-secondary">Hora</label>
                            <input type="time" name="hora_inicio" id="ver_hora" class="form-control bg-light border-0" required>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label for="ver_motivo" class="form-label fw-bold text-secondary">Motivo</label>
                        <textarea name="motivo" id="ver_motivo" class="form-control bg-light border-0" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Actualizar</button>
                </div>
            </form>

        </div>
    </div>
</div>