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
                            <label for="email" class="form-label fw-bold text-secondary">Correo Electrónico</label>
                            <input type="email" name="email" id="email" class="form-control" required value="<?php echo htmlspecialchars(isset($_SESSION['role']) && $_SESSION['role'] === 'paciente' ? ($_SESSION['email'] ?? '') : ''); ?>">
                        </div>
                        <div class="col-md-12">
                            <label for="id_doctor" class="form-label fw-bold text-secondary">Médico Especialista</label>
                            <select name="id_doctor" id="id_doctor" class="form-select" required>
                                <option value="">Seleccione un médico...</option>
                                <?php if(isset($doctores)) { foreach ($doctores as $doc): ?>
                                    <option value="<?php echo $doc['id_doctor']; ?>">
                                        Dr. <?php echo htmlspecialchars($doc['nombre'] . ' ' . $doc['apellido_paterno']); ?>
                                    </option>
                                <?php endforeach; } ?>
                            </select>
                        </div>
                        <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <div class="col-md-12">
                            <label for="id_consultorio" class="form-label fw-bold text-secondary">Asignar Consultorio</label>
                            <select name="id_consultorio" id="id_consultorio" class="form-select" required>
                                <option value="">Seleccione un consultorio...</option>
                                <option value="1">Consultorio 1 (Habitación 101)</option>
                                <option value="2">Consultorio 2 (Habitación 102)</option>
                                <option value="3">Consultorio 3 (Habitación 103)</option>
                                <option value="4">Consultorio 4 (Habitación 104)</option>
                                <option value="5">Consultorio 5 (Habitación 105)</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-12">
                            <label for="form_hora" class="form-label fw-bold text-secondary">Horario Disponible</label>
                            <select name="hora_inicio" id="form_hora" class="form-select" required>
                                <option value="">Seleccione una hora...</option>
                                <option value="07:00">07:00 AM</option>
                                <option value="07:30">07:30 AM</option>
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
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label for="motivo" class="form-label fw-bold text-secondary">Motivo (opcional)</label>
                            <textarea name="motivo" id="motivo" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Agendar Cita</button>
                </div>
            </form>
        </div>
    </div>
</div>