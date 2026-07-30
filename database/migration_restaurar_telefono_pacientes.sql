-- Migración: restaurar columna `telefono` en `pacientes` y permitir NULL en `curp`
--
-- Diagnóstico (confirmado con `DESCRIBE pacientes;` sobre la BD real antes de
-- escribir este archivo): migration_autogestion_cuenta.sql (ronda anterior)
-- asumía que `telefono` ya existía en `pacientes` -- usaba MODIFY COLUMN, no
-- ADD COLUMN -- y que solo había que relajar su nulabilidad junto con `curp`.
-- Esa migración nunca se aplicó contra la base real: `telefono` no existe en
-- absoluto, y `curp` sigue siendo NOT NULL UNIQUE sin default. Esto rompe,
-- todos por la misma causa:
--   - registro de pacientes (auth_registro.php inserta en una columna
--     `telefono` inexistente, y no envía `curp`, NOT NULL sin default)
--   - listado y detalle de pacientes en admin (admin_pacientes.php)
--   - agenda.php (prellenado de teléfono al reservar cita)
--   - cuenta.php / update_perfil.php (edición de perfil del paciente)
--   - delete_cuenta.php (anonimización de cuenta pone telefono/curp en NULL)
--   - exportar_datos_paciente.php (exportación de datos personales)
--
-- `curp` se deja UNIQUE pero nullable: MySQL/MariaDB permiten múltiples NULL
-- en una columna UNIQUE, así que las cuentas anonimizadas (curp = NULL) no
-- rompen la restricción para las cuentas activas.

ALTER TABLE pacientes
    ADD COLUMN telefono VARCHAR(10) DEFAULT NULL AFTER email,
    MODIFY COLUMN curp VARCHAR(18) DEFAULT NULL;
