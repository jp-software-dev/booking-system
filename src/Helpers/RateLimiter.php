<?php
namespace Helpers;

class RateLimiter
{
    public static function tooManyAttempts(string $key, int $maxAttempts = 5, int $windowSeconds = 300): bool
    {
        $db = \Database::getInstance();
        $stmt = $db->prepare("SELECT attempts, first_attempt FROM login_attempts WHERE identificador = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        if (!$row) {
            return false;
        }

        if ((time() - strtotime($row['first_attempt'])) > $windowSeconds) {
            $db->prepare("DELETE FROM login_attempts WHERE identificador = ?")->execute([$key]);
            return false;
        }

        return (int)$row['attempts'] >= $maxAttempts;
    }

    public static function registerAttempt(string $key): void
    {
        $db = \Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM login_attempts WHERE identificador = ?");
        $stmt->execute([$key]);

        if ($stmt->fetch()) {
            $db->prepare("UPDATE login_attempts SET attempts = attempts + 1 WHERE identificador = ?")->execute([$key]);
        } else {
            $db->prepare("INSERT INTO login_attempts (identificador, attempts, first_attempt) VALUES (?, 1, NOW())")->execute([$key]);
        }
    }

    public static function resetAttempts(string $key): void
    {
        $db = \Database::getInstance();
        $db->prepare("DELETE FROM login_attempts WHERE identificador = ?")->execute([$key]);
    }
}