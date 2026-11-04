<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Http\Sesion;
use PDO;

/**
 * MODELO DE LAS CUENTAS DE ACCESO DE LOS MÉDICOS
 *
 * Un médico (tabla `doctores`) puede tener una cuenta en `usuarios` con rol
 * 'doctor' y la columna id_doctor apuntando a él. La crea, cambia y quita el
 * administrador desde admin_equipo.php; el médico inicia sesión con su usuario.
 */
final class CuentaDoctor
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
     * Usuario de acceso de cada médico que tiene cuenta: [id_doctor => usuario].
     *
     * @return array<int, string>
     */
    public function usuariosPorDoctor(): array
    {
        $filas = $this->db->query("SELECT id_doctor, usuario FROM usuarios WHERE rol = 'doctor' AND id_doctor IS NOT NULL")->fetchAll();

        $mapa = [];
        foreach ($filas as $fila) {
            $mapa[(int) $fila['id_doctor']] = (string) $fila['usuario'];
        }

        return $mapa;
    }

    /**
     * Usuario de acceso del médico, o null si no tiene cuenta.
     */
    public function usuarioDe(int $idDoctor): ?string
    {
        $stmt = $this->db->prepare("SELECT usuario FROM usuarios WHERE rol = 'doctor' AND id_doctor = ?");
        $stmt->execute([$idDoctor]);
        $usuario = $stmt->fetchColumn();

        return $usuario === false ? null : (string) $usuario;
    }

    /**
     * Indica si el nombre de usuario ya está en uso (sin distinguir mayúsculas).
     */
    public function usuarioOcupado(string $usuario): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM usuarios WHERE LOWER(usuario) = LOWER(?)');
        $stmt->execute([$usuario]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Crea la cuenta del médico y devuelve su id_usuario.
     */
    public function crear(int $idDoctor, string $usuario, string $nombre, string $hash): int
    {
        return Database::insertar(
            $this->db,
            'INSERT INTO usuarios (usuario, nombre, password_hash, rol, id_doctor) VALUES (?, ?, ?, ?, ?)',
            [$usuario, $nombre, $hash, Sesion::ROL_DOCTOR, $idDoctor],
            'id_usuario'
        );
    }

    /**
     * Reemplaza la contraseña del médico.
     */
    public function cambiarPassword(int $idDoctor, string $hash): bool
    {
        $stmt = $this->db->prepare("UPDATE usuarios SET password_hash = ? WHERE rol = 'doctor' AND id_doctor = ?");
        $stmt->execute([$hash, $idDoctor]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Quita el acceso del médico (su información y su historial se conservan).
     */
    public function eliminar(int $idDoctor): bool
    {
        $stmt = $this->db->prepare("DELETE FROM usuarios WHERE rol = 'doctor' AND id_doctor = ?");
        $stmt->execute([$idDoctor]);

        return $stmt->rowCount() > 0;
    }
}
