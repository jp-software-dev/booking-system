-- Migración: permitir NULL en enfermeros.id_consultorio
--
-- Diagnóstico (confirmado con `DESCRIBE enfermeros;` sobre la BD real): `id_consultorio`
-- era NOT NULL, pero admin_equipo.php ofrece explícitamente la opción "Sin asignar /
-- Disponibilidad flotante" al dar de alta o editar un enfermero, y add_personal.php /
-- update_personal.php ya construían `id_consultorio = null` cuando ese campo llegaba
-- vacío. Con la columna NOT NULL, ese INSERT/UPDATE siempre fallaba -- exactamente la
-- misma clase de bug que migration_nullable_id_consultorio.sql corrigió para
-- doctores.id_consultorio, pero nunca se replicó aquí.

ALTER TABLE enfermeros
    MODIFY COLUMN id_consultorio INT(11) DEFAULT NULL;
