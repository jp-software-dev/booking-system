<?php
namespace Helpers;

class RateLimiter
{
    // Duración de cada bloqueo consecutivo: 1min, 5min, 15min, 30min, tope 1h.
    private const BACKOFF_SCHEDULE = [60, 300, 900, 1800, 3600];

    public static function tooManyAttempts(string $key, int $maxAttempts = 5, int $windowSeconds = 300): bool
    {
        $db = \Database::getInstance();
        $stmt = $db->prepare("SELECT attempts, first_attempt, blocked_until FROM login_attempts WHERE identificador = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        if (!$row) {
            return false;
        }

        // Bloqueo por backoff progresivo todavía vigente.
        if (!empty($row['blocked_until']) && strtotime($row['blocked_until']) > time()) {
            return true;
        }

        // El bloqueo anterior (si hubo) ya expiró: se abre una ventana de intentos nueva,
        // pero se conserva block_count para que el próximo bloqueo siga escalando.
        if (!empty($row['blocked_until']) || (time() - strtotime($row['first_attempt'])) > $windowSeconds) {
            $db->prepare("UPDATE login_attempts SET attempts = 0, first_attempt = NOW(), blocked_until = NULL WHERE identificador = ?")
               ->execute([$key]);
            return false;
        }

        return (int)$row['attempts'] >= $maxAttempts;
    }

    public static function registerAttempt(string $key, int $maxAttempts = 5): void
    {
        $db = \Database::getInstance();
        $stmt = $db->prepare("SELECT attempts, block_count FROM login_attempts WHERE identificador = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        if (!$row) {
            $db->prepare("INSERT INTO login_attempts (identificador, attempts, first_attempt, block_count) VALUES (?, 1, NOW(), 0)")
               ->execute([$key]);
            return;
        }

        $attempts = (int)$row['attempts'] + 1;

        if ($attempts >= $maxAttempts) {
            // Se alcanzó el límite de la ventana: escala al siguiente peldaño del backoff.
            $blockCount = (int)$row['block_count'] + 1;
            $seconds = self::BACKOFF_SCHEDULE[min($blockCount, count(self::BACKOFF_SCHEDULE)) - 1];
            $blockedUntil = date('Y-m-d H:i:s', time() + $seconds);
            $db->prepare("UPDATE login_attempts SET attempts = ?, block_count = ?, blocked_until = ? WHERE identificador = ?")
               ->execute([$attempts, $blockCount, $blockedUntil, $key]);
        } else {
            $db->prepare("UPDATE login_attempts SET attempts = ? WHERE identificador = ?")->execute([$attempts, $key]);
        }
    }

    public static function resetAttempts(string $key): void
    {
        $db = \Database::getInstance();
        $db->prepare("DELETE FROM login_attempts WHERE identificador = ?")->execute([$key]);
    }
}