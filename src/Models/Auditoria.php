<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Helpers\Paginacion;
use PDO;

/**
 * CONSULTA DE LA BITÁCORA DE AUDITORÍA
 *
 * Lectura filtrada de la tabla `bitacora` para admin_bitacora.php. (La
 * escritura se hace con App\Helpers\Bitacora.)
 *
 * bitacora.id_usuario apunta a pacientes.id_paciente cuando rol = 'paciente'
 * y a usuarios.id_usuario cuando rol = 'admin'; por eso el nombre del usuario
 * se resuelve con una subconsulta que une ambas tablas.
 */
final class Auditoria
{
    /** Une pacientes y usuarios para poder mostrar y buscar por nombre. */
    private const FROM = "FROM bitacora b
        LEFT JOIN (
            SELECT id_paciente AS id_usuario, 'paciente' AS rol, CONCAT(nombre, ' ', apellido_paterno) AS nombre_usuario FROM pacientes
            UNION ALL
            SELECT id_usuario, 'admin', nombre FROM usuarios
        ) u ON u.id_usuario = b.id_usuario AND u.rol = b.rol";

    private PDO $db;

    /**
     * @param PDO|null $db Conexión a usar. Si es null se usa la conexión compartida.
     */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Lista los tipos de acción registrados (para el filtro).
     *
     * @return list<string>
     */
    public function accionesRegistradas(): array
    {
        return $this->db->query('SELECT DISTINCT accion FROM bitacora ORDER BY accion')->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Cuenta los registros que cumplen los filtros.
     *
     * @param array{accion: string, usuario: string, desde: string, hasta: string} $filtros
     */
    public function contarConFiltro(array $filtros): int
    {
        [$where, $params] = self::construirFiltro($filtros);

        $stmt = $this->db->prepare('SELECT COUNT(*) ' . self::FROM . ' ' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Lista una página de registros que cumplen los filtros, del más reciente al más antiguo.
     *
     * @param array{accion: string, usuario: string, desde: string, hasta: string} $filtros
     * @param Paginacion $paginacion Página a traer.
     * @return list<array<string, mixed>>
     */
    public function listarConFiltro(array $filtros, Paginacion $paginacion): array
    {
        [$where, $params] = self::construirFiltro($filtros);

        $sql = 'SELECT b.id, b.id_usuario, b.rol, b.accion, b.entidad, b.id_entidad, b.detalle, b.fecha, u.nombre_usuario '
            . self::FROM . ' ' . $where . ' ORDER BY b.fecha DESC, b.id DESC';

        return $paginacion->consultar($this->db, $sql, $params);
    }

    /**
     * Construye la cláusula WHERE. Las fechas ya deben venir validadas (Y-m-d) o vacías.
     *
     * - usuario numérico: busca por id exacto.
     * - usuario con texto: busca en el nombre o en el rol.
     *
     * @param array{accion: string, usuario: string, desde: string, hasta: string} $filtros
     * @return array{0: string, 1: list<mixed>}
     */
    private static function construirFiltro(array $filtros): array
    {
        $condiciones = [];
        $params = [];

        if ($filtros['accion'] !== '') {
            $condiciones[] = 'b.accion = ?';
            $params[] = $filtros['accion'];
        }

        if ($filtros['usuario'] !== '') {
            if (ctype_digit($filtros['usuario'])) {
                $condiciones[] = 'b.id_usuario = ?';
                $params[] = (int) $filtros['usuario'];
            } else {
                $patron = '%' . addcslashes($filtros['usuario'], '%_\\') . '%';
                // LOWER() en ambos lados: en PostgreSQL LIKE distingue mayúsculas, en MySQL no.
                $condiciones[] = '(LOWER(u.nombre_usuario) LIKE LOWER(?) OR LOWER(b.rol) LIKE LOWER(?))';
                array_push($params, $patron, $patron);
            }
        }

        if ($filtros['desde'] !== '') {
            $condiciones[] = 'b.fecha >= ?';
            $params[] = $filtros['desde'] . ' 00:00:00';
        }

        if ($filtros['hasta'] !== '') {
            $condiciones[] = 'b.fecha <= ?';
            $params[] = $filtros['hasta'] . ' 23:59:59';
        }

        return [$condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones), $params];
    }
}
