<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * TOKENS DE CONTRASEÑA (RECUPERACIÓN Y CAMBIO CONFIRMADO POR CORREO)
 *
 * Maneja las dos tablas de tokens de un solo uso:
 *   - password_resets: "¿Olvidaste tu contraseña?" (identifica por correo).
 *   - cambios_password_pendientes: cambio solicitado desde "Administrar Cuenta",
 *     que guarda el hash de la nueva contraseña hasta que se confirma.
 *
 * Seguridad: el token en claro solo viaja en el enlace del correo. En la base
 * se guarda su hash SHA-256, de modo que una copia filtrada de la base no
 * permite usar los enlaces pendientes.
 */
final class TokenPassword
{
    /** Vigencia de los enlaces, en el formato de DateTime::modify(). */
    private const VIGENCIA = '+1 hour';

    private PDO $db;

    /**
     * @param PDO|null $db Conexión a usar. Si es null se usa la conexión compartida.
     */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Crea un token de recuperación para el correo e invalida los anteriores.
     *
     * @param string $email Correo del paciente.
     * @return string Token en claro para el enlace del correo.
     */
    public function crearRestablecimiento(string $email): string
    {
        // Solo el enlace más reciente debe funcionar.
        $this->db->prepare('UPDATE password_resets SET usado = 1 WHERE email = ? AND usado = 0')->execute([$email]);

        $token = self::generarToken();
        $this->db
            ->prepare('INSERT INTO password_resets (email, token, expira, usado) VALUES (?, ?, ?, 0)')
            ->execute([$email, self::huella($token), self::fechaExpiracion()]);

        return $token;
    }

    /**
     * Devuelve el correo asociado a un token de recuperación vigente y sin usar.
     *
     * @param string $token    Token recibido en el enlace.
     * @param bool   $bloquear true para bloquear la fila (SELECT ... FOR UPDATE);
     *                         requiere una transacción abierta.
     */
    public function emailDeRestablecimiento(string $token, bool $bloquear = false): ?string
    {
        $sql = 'SELECT email FROM password_resets WHERE token = ? AND expira > NOW() AND usado = 0';
        $stmt = $this->db->prepare($sql . ($bloquear ? ' FOR UPDATE' : ''));
        $stmt->execute([self::huella($token)]);
        $email = $stmt->fetchColumn();

        return $email === false ? null : (string) $email;
    }

    /**
     * Marca como usados todos los tokens de recuperación del correo.
     *
     * @param string $email Correo del paciente.
     */
    public function invalidarRestablecimientos(string $email): void
    {
        $this->db->prepare('UPDATE password_resets SET usado = 1 WHERE email = ?')->execute([$email]);
    }

    /**
     * Guarda un cambio de contraseña pendiente de confirmación e invalida los anteriores.
     *
     * @param int    $idPaciente Paciente que solicita el cambio.
     * @param string $nuevoHash  Hash de la nueva contraseña (password_hash()).
     * @return string Token en claro para el enlace del correo.
     */
    public function crearCambioPendiente(int $idPaciente, string $nuevoHash): string
    {
        $this->db
            ->prepare('UPDATE cambios_password_pendientes SET usado = 1 WHERE id_paciente = ? AND usado = 0')
            ->execute([$idPaciente]);

        $token = self::generarToken();
        $this->db
            ->prepare('INSERT INTO cambios_password_pendientes (id_paciente, nuevo_hash, token, expira) VALUES (?, ?, ?, ?)')
            ->execute([$idPaciente, $nuevoHash, self::huella($token), self::fechaExpiracion()]);

        return $token;
    }

    /**
     * Indica si un token de cambio pendiente es vigente, sin consumirlo.
     *
     * @param string $token Token recibido en el enlace.
     */
    public function cambioPendienteVigente(string $token): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM cambios_password_pendientes WHERE token = ? AND expira > NOW() AND usado = 0'
        );
        $stmt->execute([self::huella($token)]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Consume un cambio pendiente: si el token es válido lo marca como usado y
     * devuelve el paciente y el hash a aplicar. Debe llamarse dentro de una transacción.
     *
     * @param string $token Token recibido en el enlace.
     * @return array{id_paciente: int, nuevo_hash: string}|null
     */
    public function consumirCambioPendiente(string $token): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, id_paciente, nuevo_hash FROM cambios_password_pendientes
             WHERE token = ? AND expira > NOW() AND usado = 0 FOR UPDATE'
        );
        $stmt->execute([self::huella($token)]);
        $pendiente = $stmt->fetch();

        if (!$pendiente) {
            return null;
        }

        $this->db->prepare('UPDATE cambios_password_pendientes SET usado = 1 WHERE id = ?')->execute([$pendiente['id']]);

        return ['id_paciente' => (int) $pendiente['id_paciente'], 'nuevo_hash' => (string) $pendiente['nuevo_hash']];
    }

    /**
     * Genera un token aleatorio de 64 caracteres hexadecimales.
     */
    private static function generarToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Hash SHA-256 del token, que es lo que se guarda y se busca en la base.
     *
     * @param string $token Token en claro.
     */
    private static function huella(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Fecha y hora de vencimiento de un token creado ahora.
     */
    private static function fechaExpiracion(): string
    {
        return date('Y-m-d H:i:s', (int) strtotime(self::VIGENCIA));
    }
}
