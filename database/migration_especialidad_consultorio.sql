-- Migración: especialidad predeterminada por consultorio
--
-- `consultorios` no tenía columna de especialidad, por lo que el selector de
-- "Asignación de Consultorios" mostraba TODOS los doctores activos sin
-- filtrar por especialidad. Se agrega id_especialidad (nullable, igual que
-- doctores.id_especialidad) para que cada consultorio pueda declarar su
-- especialidad predeterminada y el backend filtre los doctores asignables
-- por coincidencia de especialidad.

ALTER TABLE consultorios
    ADD COLUMN id_especialidad INT(11) DEFAULT NULL AFTER nombre,
    ADD CONSTRAINT fk_consultorios_especialidad FOREIGN KEY (id_especialidad) REFERENCES especialidades (id_especialidad);
