<div class="modal fade" id="modalCita" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-calendar-plus me-2"></i> Nueva Cita</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCita" action="../api/create_cita.php" method="POST">
                <input type="hidden" name="fecha_cita" id="form_fecha">
                
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="curp" class="form-label fw-bold text-secondary">CURP</label>
                            <input type="text" name="curp" id="curp" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label for="nombre" class="form-label fw-bold text-secondary">Nombre</label>
                            <input type="text" name="nombre" id="nombre" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label for="apellido_p" class="form-label fw-bold text-secondary">Apellido Paterno</label>
                            <input type="text" name="apellido_p" id="apellido_p" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label for="apellido_m" class="form-label fw-bold text-secondary">Apellido Materno</label>
                            <input type="text" name="apellido_m" id="apellido_m" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label for="edad" class="form-label fw-bold text-secondary">Edad</label>
                            <input type="number" name="edad" id="edad" class="form-control" required>
                        </div>
                        <div class="col-md-9">
                            <label for="email" class="form-label fw-bold text-secondary">Email</label>
                            <input type="email" name="email" id="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="id_doctor" class="form-label fw-bold text-secondary">Doctor</label>
                            <select name="id_doctor" id="id_doctor" class="form-select" required></select>
                        </div>
                        <div class="col-md-6">
                            <label for="hora_inicio" class="form-label fw-bold text-secondary">Hora</label>
                            <select name="hora_inicio" id="form_hora" class="form-select" required>
                                <option value="">Selecciona una hora</option>
                                <option value="08:00">08:00 AM</option>
                                <option value="08:30">08:30 AM</option>
                                <option value="09:00">09:00 AM</option>
                                <option value="09:30">09:30 AM</option>
                                <option value="10:00">10:00 AM</option>
                                <option value="10:30">10:30 AM</option>
                                <option value="11:00">11:00 AM</option>
                                <option value="11:30">11:30 AM</option>
                                <option value="12:00">12:00 PM</option>
                                <option value="12:30">12:30 PM</option>
                                <option value="13:00">01:00 PM</option>
                                <option value="13:30">01:30 PM</option>
                                <option value="14:00">02:00 PM</option>
                                <option value="14:30">02:30 PM</option>
                                <option value="15:00">03:00 PM</option>
                                <option value="15:30">03:30 PM</option>
                                <option value="16:00">04:00 PM</option>
                                <option value="16:30">04:30 PM</option>
                                <option value="17:00">05:00 PM</option>
                                <option value="17:30">05:30 PM</option>
                                <option value="18:00">06:00 PM</option>
                                <option value="18:30">06:30 PM</option>
                                <option value="19:00">07:00 PM</option>
                                <option value="19:30">07:30 PM</option>
                                <option value="20:00">08:00 PM</option>
                                <option value="20:30">08:30 PM</option>
                                <option value="21:00">09:00 PM</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Agendar</button>
                </div>
            </form>
        </div>
    </div>
</div>