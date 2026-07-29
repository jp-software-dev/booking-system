-- Migración: métricas de pacientes nuevos por mes (Parte 3.1 - ronda 2)
--
-- No existía columna de fecha de alta en `pacientes`, necesaria para agrupar
-- altas por mes en el dashboard. Se usa DEFAULT CURRENT_TIMESTAMP para que
-- los registros existentes queden con la fecha de aplicación de esta
-- migración (no hay forma de reconstruir su fecha de alta real) y los
-- nuevos registros se sigan poblando automáticamente sin tocar el código
-- de inserción existente.

ALTER TABLE pacientes
    ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER id_paciente;
