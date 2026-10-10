<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Helpers\Paginacion;
use PDO;
use Throwable;

/**
 * MODELO DE PACIENTES
 *
 * Acceso a las tablas `pacientes` y `pacientes_credenciales`: registro,
 * credenciales, perfil y baja (anonimización) de la cuenta.
 *
 * Las cuentas eliminadas no se borran físicamente: se anonimizan y se marcan
 * con cuenta_eliminada = 1, porque `citas` depende de id_paciente con
 * ON DELETE CASCADE y un borrado real se llevaría el historial clínico.
 */
final class Paciente
{
    /** Valores válidos del ENUM pacientes.genero. */
    public const GENEROS = ['Masculino', 'Femenino', 'Otro'];

    /** Longitud máxima de nombre y apellidos (VARCHAR(50) en la base). */
    public const MAX_NOMBRE = 50;

    private PDO $db;

    /**
     * @param PDO|null $db Conexión a usar. Si es null se usa la conexión compartida.
     */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Cuenta los pacientes que cumplen la búsqueda del listado administrativo.
     *
     * @param string $busqueda     Texto a buscar en nombre, apellidos o correo.
     * @param string $estadoCuenta 'activa', 'anonimizada' o '' (todas).
     */
    public function contarConFiltro(string $busqueda, string $estadoCuenta): int
    {
        [$where, $params] = self::filtroListado($busqueda, $estadoCuenta);

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM pacientes {$where}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Lista una página de pacientes que cumplen la búsqueda, ordenados por nombre.
     *
     * @param string     $busqueda     Texto a buscar en nombre, apellidos o correo.
     * @param string     $estadoCuenta 'activa', 'anonimizada' o '' (todas).
     * @param Paginacion $paginacion   Página a traer.
     * @return list<array<string, mixed>>
     */
    public function listarConFiltro(string $busqueda, string $estadoCuenta, Paginacion $paginacion): array
    {
        [$where, $params] = self::filtroListado($busqueda, $estadoCuenta);

        $sql = "SELECT id_paciente, nombre, apellido_paterno, apellido_materno, email, telefono, cuenta_eliminada
                FROM pacientes {$where}
                ORDER BY nombre ASC, apellido_paterno ASC";

        return $paginacion->consultar($this->db, $sql, $params);
    }

    /**
     * Construye la cláusula WHERE del listado administrativo de pacientes.
     *
     * @return array{0: string, 1: list<string>}
     */
    private static function filtroListado(string $busqueda, string $estadoCuenta): array
    {
        $condiciones = [];
        $params = [];

        if ($busqueda !== '') {
            $patron = '%' . addcslashes($busqueda, '%_\\') . '%';
            $condiciones[] = '(nombre LIKE ? OR apellido_paterno LIKE ? OR apellido_materno LIKE ? OR email LIKE ?)';
            array_push($params, $patron, $patron, $patron, $patron);
        }

        if ($estadoCuenta === 'activa') {
            $condiciones[] = 'cuenta_eliminada = 0';
        } elseif ($estadoCuenta === 'anonimizada') {
            $condiciones[] = 'cuenta_eliminada = 1';
        }

        return [$condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones), $params];
    }

    /**
     * Registra un paciente nuevo junto con sus credenciales, en una transacción.
     *
     * @param array{nombre: string, apellido_paterno: string, apellido_materno: string|null,
     *              fecha_nacimiento: string, genero: string, telefono: string, email: string} $datos
     * @param string $hashPassword Hash generado con password_hash().
     * @return int Id del paciente creado.
     */
    public function registrar(array $datos, string $hashPassword): int
    {
        $this->db->beginTransaction();

        try {
            $this->db->prepare(
                'INSERT INTO pacientes (nombre, apellido_paterno, apellido_materno, fecha_nacimiento, genero, telefono, email)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $datos['nombre'],
                $datos['apellido_paterno'],
                $datos['apellido_materno'],
                $datos['fecha_nacimiento'],
                $datos['genero'],
                $datos['telefono'],
                $datos['email'],
            ]);
            $idPaciente = (int) $this->db->lastInsertId();

            $this->db
                ->prepare('INSERT INTO pacientes_credenciales (id_paciente, password_hash, cuenta_verificada) VALUES (?, ?, 1)')
                ->execute([$idPaciente, $hashPassword]);

            $this->db->commit();

            return $idPaciente;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Indica si un correo ya está registrado.
     *
     * @param string   $email     Correo a buscar.
     * @param int|null $excluirId Paciente a ignorar (el que edita su propio perfil).
     */
    public function existeEmail(string $email, ?int $excluirId = null): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM pacientes WHERE email = ? AND id_paciente <> ?');
        $stmt->execute([$email, $excluirId ?? 0]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Busca el id de un paciente con cuenta activa a partir de su correo.
     *
     * @param string $email Correo del paciente.
     */
    public function buscarIdActivoPorEmail(string $email): ?int
    {
        $stmt = $this->db->prepare('SELECT id_paciente FROM pacientes WHERE email = ? AND cuenta_eliminada = 0');
        $stmt->execute([$email]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * Devuelve los datos de inicio de sesión (id, nombre y hash) de una cuenta activa.
     *
     * @param string $email Correo del paciente.
     * @return array{id_paciente: int, nombre: string, password_hash: string}|null
     */
    public function obtenerCredencialesPorEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.id_paciente, p.nombre, pc.password_hash
             FROM pacientes p
             INNER JOIN pacientes_credenciales pc ON p.id_paciente = pc.id_paciente
             WHERE p.email = ? AND p.cuenta_eliminada = 0'
        );
        $stmt->execute([$email]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Devuelve el hash de contraseña de un paciente.
     *
     * @param int $idPaciente Paciente.
     */
    public function obtenerHash(int $idPaciente): ?string
    {
        $stmt = $this->db->prepare('SELECT password_hash FROM pacientes_credenciales WHERE id_paciente = ?');
        $stmt->execute([$idPaciente]);
        $hash = $stmt->fetchColumn();

        return $hash === false ? null : (string) $hash;
    }

    /**
     * Reemplaza el hash de contraseña de un paciente.
     *
     * @param int    $idPaciente   Paciente.
     * @param string $hashPassword Nuevo hash generado con password_hash().
     */
    public function actualizarHash(int $idPaciente, string $hashPassword): void
    {
        $this->db
            ->prepare('UPDATE pacientes_credenciales SET password_hash = ? WHERE id_paciente = ?')
            ->execute([$hashPassword, $idPaciente]);
    }

    /**
     * Devuelve el perfil completo de un paciente.
     *
     * @param int $idPaciente Paciente.
     * @return array<string, mixed>|null
     */
    public function obtenerPorId(int $idPaciente): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id_paciente, nombre, apellido_paterno, apellido_materno, fecha_nacimiento, genero,
                    telefono, email, cuenta_eliminada, recibir_notificaciones, created_at
             FROM pacientes WHERE id_paciente = ?'
        );
        $stmt->execute([$idPaciente]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Actualiza los datos personales del paciente.
     *
     * @param int         $idPaciente      Paciente.
     * @param string      $nombre          Nombre(s).
     * @param string      $apellidoPaterno Apellido paterno.
     * @param string|null $apellidoMaterno Apellido materno o null.
     * @param string      $telefono        Teléfono de 10 dígitos.
     * @param string      $email           Correo electrónico.
     */
    public function actualizarPerfil(
        int $idPaciente,
        string $nombre,
        string $apellidoPaterno,
        ?string $apellidoMaterno,
        string $telefono,
        string $email
    ): void {
        $this->db->prepare(
            'UPDATE pacientes SET nombre = ?, apellido_paterno = ?, apellido_materno = ?, telefono = ?, email = ?
             WHERE id_paciente = ?'
        )->execute([$nombre, $apellidoPaterno, $apellidoMaterno, $telefono, $email, $idPaciente]);
    }

    /**
     * Activa o desactiva los correos de confirmación y cancelación de citas.
     *
     * @param int  $idPaciente Paciente.
     * @param bool $recibir    true para recibir correos.
     */
    public function actualizarNotificaciones(int $idPaciente, bool $recibir): void
    {
        $this->db
            ->prepare('UPDATE pacientes SET recibir_notificaciones = ? WHERE id_paciente = ?')
            ->execute([$recibir ? 1 : 0, $idPaciente]);
    }

    /**
     * Anonimiza la cuenta (baja solicitada por el paciente).
     *
     * Borra nombre, teléfono y correo reales, marca cuenta_eliminada = 1 e
     * invalida la contraseña. Debe llamarse dentro de una transacción.
     *
     * @param int $idPaciente Paciente.
     * @return bool true si la cuenta estaba activa y se anonimizó.
     */
    public function anonimizar(int $idPaciente): bool
    {
        // El correo debe seguir siendo único, así que se genera uno ficticio.
        $emailAnonimo = 'baja_' . $idPaciente . '_' . bin2hex(random_bytes(6)) . '@cuenta-eliminada.local';

        $stmt = $this->db->prepare(
            "UPDATE pacientes SET nombre = 'Usuario eliminado', apellido_paterno = '', apellido_materno = NULL,
                    telefono = NULL, email = ?, cuenta_eliminada = 1
             WHERE id_paciente = ? AND cuenta_eliminada = 0"
        );
        $stmt->execute([$emailAnonimo, $idPaciente]);

        if ($stmt->rowCount() === 0) {
            return false;
        }

        // Hash de una contraseña aleatoria que nadie conoce: la cuenta ya no puede iniciar sesión.
        $hashInvalido = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $this->db
            ->prepare('UPDATE pacientes_credenciales SET password_hash = ?, cuenta_verificada = 0 WHERE id_paciente = ?')
            ->execute([$hashInvalido, $idPaciente]);

        return true;
    }
}
