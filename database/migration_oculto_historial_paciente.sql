-- Migración: ocultar entradas del historial médico del paciente (Parte 3)
--
-- No se usa DELETE físico: la cita sigue siendo relevante para el historial
-- administrativo (admin_historial.php) y las métricas del dashboard (Parte 8).
-- Este campo solo excluye la fila de la vista del propio paciente.

ALTER TABLE citas
    ADD COLUMN oculto_para_paciente TINYINT(1) NOT NULL DEFAULT 0 AFTER estado_cita;
