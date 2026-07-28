-- MIGRACIÓN: Restricción de empalmes a nivel de base de datos
--
-- Objetivo: impedir que dos citas activas (no canceladas) del mismo doctor
-- ocupen la misma fecha/hora, incluso bajo condiciones de carrera (TOCTOU).
--
-- Alternativas evaluadas:
--   1) Índice único directo sobre (id_doctor, fecha_cita, hora_inicio):
--      simple, pero también impediría repetir un slot que fue cancelado
--      y debe poder volver a reservarse. Descartada.
--   2) Valor centinela (p. ej. fecha_cita/hora_inicio = NULL o un valor mágico
--      al cancelar): ensucia datos reales de la cita cancelada y complica
--      cualquier reporte histórico que dependa de esos campos. Descartada.
--   3) Columna generada (STORED) que sólo contiene el trío id_doctor+fecha+hora
--      cuando la cita está activa, y NULL cuando está 'Cancelada', con un
--      índice único sobre esa columna. MySQL/MariaDB tratan cada NULL como
--      distinto en un índice único, así que múltiples citas canceladas para
--      el mismo slot no chocan entre sí ni con una futura cita activa.
--      Elegida: no requiere tocar datos existentes de forma destructiva y
--      el motor la aplica de forma atómica, cerrando la ventana de carrera
--      que un simple check-then-insert en PHP no puede cerrar.
--
-- Compatibilidad: columnas generadas y GENERATED ALWAYS AS ... STORED están
-- disponibles desde MariaDB 5.2+ / MySQL 5.7+. El dump del proyecto reporta
-- MariaDB 10.4.32, así que es compatible.
--
-- IMPORTANTE: antes de aplicar el ADD UNIQUE KEY, verifica que no existan ya
-- citas activas duplicadas en el mismo slot (dato de prueba/desarrollo puede
-- tener colisiones). Ejecuta primero:
--
--   SELECT id_doctor, fecha_cita, hora_inicio, COUNT(*) AS duplicados
--   FROM citas
--   WHERE estado_cita <> 'Cancelada'
--   GROUP BY id_doctor, fecha_cita, hora_inicio
--   HAVING duplicados > 1;
--
-- Si esa consulta devuelve filas, resuelve manualmente esos conflictos
-- (reprogramando o cancelando una de las citas duplicadas) antes de continuar,
-- o el ALTER TABLE fallará con error de clave duplicada.

ALTER TABLE `citas`
  ADD COLUMN `slot_activo` VARCHAR(60)
  GENERATED ALWAYS AS (
    CASE WHEN estado_cita <> 'Cancelada'
         THEN CONCAT(id_doctor, '_', fecha_cita, '_', hora_inicio)
         ELSE NULL
    END
  ) STORED;

ALTER TABLE `citas`
  ADD UNIQUE KEY `uq_citas_slot_activo` (`slot_activo`);
