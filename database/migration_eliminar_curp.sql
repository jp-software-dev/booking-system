-- Migración: eliminar por completo la columna `curp` de `pacientes`
--
-- Decisión de producto: el reporte que originó esto era que el admin podía
-- ver el CURP de todos los pacientes en admin_pacientes.php. La solución no
-- es ocultarlo en la interfaz -- es eliminar el concepto del sistema. Ya no
-- se recolecta en el registro (auth_registro.php no lo pide ni lo inserta
-- desde antes de esta migración), ni se usa en ninguna validación, PDF o
-- exportación de datos tras el commit que acompaña este archivo.
--
-- ADVERTENCIA: esta operación es IRREVERSIBLE. Borra permanentemente el
-- valor de CURP de todos los pacientes ya registrados en la base de datos.
-- No hay forma de recuperar ese dato después de aplicar este ALTER TABLE.
-- Aplícala manualmente (phpMyAdmin u otro cliente) solo cuando estés seguro.

ALTER TABLE pacientes DROP COLUMN curp;
