<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * MODELO DE SUSCRIPCIONES A NOTIFICACIONES PUSH
 *
 * Cada navegador o teléfono en el que el paciente aceptó notificaciones queda
 * guardado con su endpoint (la URL del servicio push del navegador) y sus
 * claves de cifrado. El endpoint se identifica por su SHA-256 (endpoint_hash):
 * si el mismo navegador se vuelve a suscribir, se actualiza en lugar de duplicarse.
 */
final class SuscripcionPush
{
    private PDO $db;

    /**
     * @param PDO|null $db Conexión a usar. Si es null se usa la conexión compartida.
     */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Guarda o actualiza la suscripción de un navegador.
     */
    public function guardar(int $idPaciente, string $endpoint, string $p256dh, string $auth, ?string $agente): void
    {
        $hash = hash('sha256', $endpoint);
        $existe = $this->db->prepare('SELECT id_suscripcion FROM suscripciones_push WHERE endpoint_hash = ?');
        $existe->execute([$hash]);
        $id = $existe->fetchColumn();

        if ($id !== false) {
            $this->db->prepare(
                'UPDATE suscripciones_push SET id_paciente = ?, endpoint = ?, clave_p256dh = ?, clave_auth = ?, agente = ?
                 WHERE id_suscripcion = ?'
            )->execute([$idPaciente, $endpoint, $p256dh, $auth, $agente, (int) $id]);

            return;
        }

        $this->db->prepare(
            'INSERT INTO suscripciones_push (id_paciente, endpoint, endpoint_hash, clave_p256dh, clave_auth, agente, creada_en)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$idPaciente, $endpoint, $hash, $p256dh, $auth, $agente, date('Y-m-d H:i:s')]);
    }

    /**
     * Elimina la suscripción de un navegador del paciente.
     */
    public function eliminar(int $idPaciente, string $endpoint): void
    {
        $this->db->prepare('DELETE FROM suscripciones_push WHERE endpoint_hash = ? AND id_paciente = ?')
            ->execute([hash('sha256', $endpoint), $idPaciente]);
    }

    /**
     * Elimina una suscripción que el servicio push reportó como vencida (404/410).
     */
    public function eliminarVencida(string $endpoint): void
    {
        $this->db->prepare('DELETE FROM suscripciones_push WHERE endpoint_hash = ?')->execute([hash('sha256', $endpoint)]);
    }

    /**
     * Suscripciones activas de un paciente.
     *
     * @return list<array{endpoint: string, clave_p256dh: string, clave_auth: string}>
     */
    public function listarPorPaciente(int $idPaciente): array
    {
        $stmt = $this->db->prepare('SELECT endpoint, clave_p256dh, clave_auth FROM suscripciones_push WHERE id_paciente = ?');
        $stmt->execute([$idPaciente]);

        return $stmt->fetchAll();
    }

    /**
     * Cantidad de navegadores suscritos del paciente.
     */
    public function contarPorPaciente(int $idPaciente): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM suscripciones_push WHERE id_paciente = ?');
        $stmt->execute([$idPaciente]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Registra la fecha del último aviso enviado a un navegador.
     */
    public function marcarEnvio(string $endpoint): void
    {
        $this->db->prepare('UPDATE suscripciones_push SET ultimo_envio = ? WHERE endpoint_hash = ?')
            ->execute([date('Y-m-d H:i:s'), hash('sha256', $endpoint)]);
    }
}
