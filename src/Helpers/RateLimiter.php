<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Database;
use App\Core\Env;

/**
 * LIMITADOR DE INTENTOS CON BLOQUEO PROGRESIVO
 *
 * Cuenta los intentos por identificador (por ejemplo, "login_<ip>") en la tabla
 * `login_attempts`. Al llegar al máximo dentro de la ventana de tiempo, bloquea
 * el identificador durante un periodo que crece con cada bloqueo consecutivo
 * (1 min, 5 min, 15 min, 30 min y como máximo 1 hora).
 */
final class RateLimiter
{
    /** Duración en segundos de cada bloqueo consecutivo. */
    private const ESCALA_BLOQUEO = [60, 300, 900, 1800, 3600];

    /**
     * Indica si el identificador superó el límite y debe rechazarse.
     *
     * Si un bloqueo anterior ya venció o la ventana de tiempo terminó, reinicia
     * el contador de intentos (pero conserva block_count para que el siguiente
     * bloqueo sea más largo).
     *
     * @param string $clave       Identificador a limitar (ej. "login_127.0.0.1").
     * @param int    $maxIntentos Intentos permitidos dentro de la ventana.
     * @param int    $ventana     Duración de la ventana en segundos.
     */
    public static function tooManyAttempts(string $clave, int $maxIntentos = 5, int $ventana = 300): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT attempts, first_attempt, blocked_until FROM login_attempts WHERE identificador = ?');
        $stmt->execute([$clave]);
        $fila = $stmt->fetch();

        if (!$fila) {
            return false;
        }

        // Bloqueo todavía vigente.
        if (!empty($fila['blocked_until']) && strtotime((string) $fila['blocked_until']) > time()) {
            return true;
        }

        // El bloqueo venció o la ventana terminó: se abre una ventana nueva.
        $ventanaVencida = (time() - (int) strtotime((string) $fila['first_attempt'])) > $ventana;
        if (!empty($fila['blocked_until']) || $ventanaVencida) {
            $db->prepare('UPDATE login_attempts SET attempts = 0, first_attempt = ?, blocked_until = NULL WHERE identificador = ?')
               ->execute([date('Y-m-d H:i:s'), $clave]);
            return false;
        }

        return (int) $fila['attempts'] >= $maxIntentos;
    }

    /**
     * Suma un intento. Si con él se alcanza el máximo, aplica el siguiente
     * escalón de bloqueo.
     *
     * @param string $clave       Identificador a limitar.
     * @param int    $maxIntentos Intentos que disparan el bloqueo.
     */
    public static function registerAttempt(string $clave, int $maxIntentos = 5): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT attempts, block_count FROM login_attempts WHERE identificador = ?');
        $stmt->execute([$clave]);
        $fila = $stmt->fetch();

        if (!$fila) {
            $db->prepare('INSERT INTO login_attempts (identificador, attempts, first_attempt, block_count) VALUES (?, 1, ?, 0)')
               ->execute([$clave, date('Y-m-d H:i:s')]);
            return;
        }

        $intentos = (int) $fila['attempts'] + 1;

        if ($intentos < $maxIntentos) {
            $db->prepare('UPDATE login_attempts SET attempts = ? WHERE identificador = ?')
               ->execute([$intentos, $clave]);
            return;
        }

        $bloqueos = (int) $fila['block_count'] + 1;
        $segundos = self::ESCALA_BLOQUEO[min($bloqueos, count(self::ESCALA_BLOQUEO)) - 1];

        $db->prepare('UPDATE login_attempts SET attempts = ?, block_count = ?, blocked_until = ? WHERE identificador = ?')
           ->execute([$intentos, $bloqueos, date('Y-m-d H:i:s', time() + $segundos), $clave]);
    }

    /**
     * Borra el historial del identificador (por ejemplo, tras un inicio de sesión correcto).
     *
     * @param string $clave Identificador a reiniciar.
     */
    public static function resetAttempts(string $clave): void
    {
        Database::getInstance()
            ->prepare('DELETE FROM login_attempts WHERE identificador = ?')
            ->execute([$clave]);
    }

    /**
     * Construye la clave de limitación para una acción y la IP del cliente.
     *
     * @param string $accion Nombre corto de la acción (ej. "login", "registro").
     */
    public static function claveParaIp(string $accion): string
    {
        return $accion . '_' . self::ipCliente();
    }

    /**
     * IP del cliente que hace la petición.
     *
     * Con TRUST_PROXY=true en el .env (por ejemplo en Vercel, donde todas las
     * peticiones llegan a través de su proxy) se toma la IP que el proxy envía
     * en X-Real-IP o X-Forwarded-For. Activarlo solo detrás de un proxy de
     * confianza: sin proxy, el cliente podría falsificar esas cabeceras.
     */
    public static function ipCliente(): string
    {
        if (Env::get('TRUST_PROXY', 'false') === 'true') {
            $reenviada = (string) ($_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
            $ip = trim(explode(',', $reenviada)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }

        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'desconocida');
    }
}
