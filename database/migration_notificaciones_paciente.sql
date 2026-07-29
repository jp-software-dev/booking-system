-- Migración: preferencias de notificación del paciente (Parte 7 - derechos ARCO, ronda 2)
--
-- Permite al paciente desactivar los correos de confirmación/cancelación de citas
-- (update_status.php) sin afectar el resto del flujo. DEFAULT 1 para no cambiar el
-- comportamiento actual de los pacientes existentes (ya reciben notificaciones hoy).

ALTER TABLE pacientes
    ADD COLUMN recibir_notificaciones TINYINT(1) NOT NULL DEFAULT 1 AFTER cuenta_eliminada;
