-- Migración: corrige el constraint de empalmes de citas para que coincida con lo
-- que el código y migration_unique_slot.sql siempre asumieron.
--
-- DIAGNÓSTICO (confirmado con SHOW CREATE TABLE citas sobre la BD real): la tabla
-- en producción tenía `UNIQUE KEY unq_cita_doctor (id_doctor, fecha_cita, hora_inicio)`,
-- un índice único plano que NO excluye las citas 'Cancelada'. migration_unique_slot.sql
-- nunca se aplicó tal cual — en su lugar corrió esta variante más simple, sin dejar
-- constancia en ningún archivo de migración versionado.
--
-- EFECTO DEL BUG: cancelar una cita bloqueaba ese doctor+fecha+hora para siempre.
-- El siguiente intento de reservar exactamente ese horario (de cualquier paciente)
-- fallaba con "El horario seleccionado acaba de ser ocupado por otro paciente"
-- (SQLSTATE 23000, capturado en AppointmentController::create, update_cita_admin.php
-- y update_cita_user.php), aunque el horario en realidad estaba libre.
--
-- Esta migración reemplaza unq_cita_doctor por el diseño original documentado en
-- migration_unique_slot.sql: una columna generada `slot_activo` que solo contiene
-- el trío id_doctor+fecha+hora cuando la cita está activa, y NULL cuando está
-- 'Cancelada' (MySQL/MariaDB tratan cada NULL como distinto en un índice único,
-- así que varias citas canceladas para el mismo slot no chocan entre sí ni con
-- una futura cita activa).
--
-- NOTA TÉCNICA: unq_cita_doctor era el único índice que arrancaba con id_doctor,
-- así que la FK fk_cita_doctor dependía de él como índice de soporte. Por eso se
-- crea primero idx_cita_doctor (índice simple) antes de poder hacer DROP INDEX
-- unq_cita_doctor -- si se omite ese paso, InnoDB rechaza el DROP con el error
-- 1553 ("Cannot drop index: needed in a foreign key constraint").
--
-- PRE-REQUISITO: antes de aplicar, confirmar que no hay duplicados activos:
--   SELECT id_doctor, fecha_cita, hora_inicio, COUNT(*) AS duplicados
--   FROM citas WHERE estado_cita <> 'Cancelada'
--   GROUP BY id_doctor, fecha_cita, hora_inicio HAVING duplicados > 1;
-- (En la base real, al momento de aplicar esta migración, esa consulta no
-- devolvió filas -- el índice unq_cita_doctor ya lo garantizaba de antemano.)

ALTER TABLE citas ADD INDEX idx_cita_doctor (id_doctor);

ALTER TABLE citas DROP INDEX unq_cita_doctor;

ALTER TABLE citas ADD COLUMN slot_activo VARCHAR(50)
  GENERATED ALWAYS AS (
    CASE WHEN estado_cita <> 'Cancelada'
    THEN CONCAT(id_doctor, '_', fecha_cita, '_', hora_inicio)
    ELSE NULL END
  ) STORED;

ALTER TABLE citas ADD UNIQUE KEY unq_slot_activo (slot_activo);
