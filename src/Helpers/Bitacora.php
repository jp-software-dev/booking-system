<?php
/**
 * HELPER DE BITÁCORA DE AUDITORÍA
 *
 * Registra eventos administrativos sensibles (bajas, reasignaciones,
 * cancelaciones) con el usuario/rol tomados automáticamente de la sesión
 * activa. No lanza excepciones hacia el flujo principal: un fallo al
 * registrar la bitácora nunca debe romper la operación que la origina.
 */

namespace Helpers;

class Bitacora
{
    public static function registrar(string $accion, string $entidad, ?int $id_entidad = null, ?string $detalle = null): void
    {
        if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
            return;
        }

        try {
            $db = \Database::getInstance();
            $stmt = $db->prepare(
                "INSERT INTO bitacora (id_usuario, rol, accion, entidad, id_entidad, detalle) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$_SESSION['user_id'], $_SESSION['role'], $accion, $entidad, $id_entidad, $detalle]);
        } catch (\Exception $e) {
            error_log("Error en Bitacora::registrar: " . $e->getMessage());
        }
    }
}
