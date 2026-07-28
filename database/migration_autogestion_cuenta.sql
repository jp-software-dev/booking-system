-- Migración: Autogestión de cuenta del paciente (Parte 2)
--
-- 1) cuenta_eliminada: bandera de borrado lógico. No existía ninguna columna de
--    estado/activo en `pacientes`, así que se agrega para poder desactivar el
--    acceso sin borrar la fila (las citas históricas dependen de id_paciente
--    con FK ON DELETE CASCADE — un DELETE físico se llevaría también las citas).
-- 2) telefono y curp pasan a admitir NULL: al anonimizar una cuenta eliminada
--    se limpian estos campos (dato personal identificable). MySQL/MariaDB
--    permiten múltiples NULL en una columna UNIQUE, así que esto no rompe la
--    restricción UNIQUE de `curp`.

ALTER TABLE pacientes
    ADD COLUMN cuenta_eliminada TINYINT(1) NOT NULL DEFAULT 0 AFTER email,
    MODIFY COLUMN telefono varchar(10) DEFAULT NULL,
    MODIFY COLUMN curp varchar(18) DEFAULT NULL;
