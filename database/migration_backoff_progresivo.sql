-- Backoff progresivo para login_attempts.
-- block_count: cuántas veces consecutivas se ha bloqueado este identificador (se resetea al
--              borrarse la fila, lo cual ocurre en un login exitoso vía RateLimiter::resetAttempts).
-- blocked_until: fin del bloqueo activo. Mientras NOW() < blocked_until, tooManyAttempts() bloquea
--              sin importar cuántos intentos lleve la ventana actual.
ALTER TABLE `login_attempts`
    ADD COLUMN `block_count` INT(11) NOT NULL DEFAULT 0 AFTER `first_attempt`,
    ADD COLUMN `blocked_until` DATETIME NULL DEFAULT NULL AFTER `block_count`;
