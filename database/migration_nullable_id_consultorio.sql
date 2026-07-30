-- Migración: permitir NULL en doctores.id_consultorio
--
-- Diagnóstico (confirmado con `DESCRIBE doctores;` sobre la BD real):
-- `id_consultorio` es NOT NULL, pero "Liberar Consultorio" en
-- admin_consultorios.php / assign_consultorio.php necesita poder dejar un
-- consultorio sin médico asignado (estado "Disponible", ya contemplado en la
-- UI). Con la columna NOT NULL, ese UPDATE siempre lanza una PDOException
-- ("Column 'id_consultorio' cannot be null"), que es exactamente el error
-- "Error interno al procesar la asignación" reportado.

ALTER TABLE doctores
    MODIFY COLUMN id_consultorio INT(11) DEFAULT NULL;
