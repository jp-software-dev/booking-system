<?php

declare(strict_types=1);

namespace App\Helpers;

use PDO;

/**
 * PAGINACIÓN DE LISTADOS
 *
 * Calcula la página actual, el tamaño de página y el desplazamiento (OFFSET) a
 * partir de los parámetros "page" y "per_page" de la URL, y ejecuta la consulta
 * paginada. La usan admin.php, admin_historial.php, admin_pacientes.php y
 * admin_bitacora.php junto con la vista src/views/partials/pagination.php.
 */
final class Paginacion
{
    /** Tamaños de página que el usuario puede elegir. */
    public const OPCIONES_POR_PAGINA = [50, 100, 200, 500];

    /** Registros por página. */
    public readonly int $porPagina;

    /** Página actual, siempre dentro del rango [1, totalPaginas]. */
    public readonly int $pagina;

    /** Total de páginas (0 si no hay registros). */
    public readonly int $totalPaginas;

    /** Cantidad de registros a saltar en la consulta. */
    public readonly int $offset;

    /**
     * @param int                  $totalRegistros Registros que cumplen el filtro actual.
     * @param array<string, mixed> $consulta       Parámetros de la URL (normalmente $_GET).
     */
    public function __construct(public readonly int $totalRegistros, array $consulta)
    {
        $porPagina = (int) ($consulta['per_page'] ?? 0);
        $this->porPagina = in_array($porPagina, self::OPCIONES_POR_PAGINA, true) ? $porPagina : self::OPCIONES_POR_PAGINA[0];

        $this->totalPaginas = $totalRegistros > 0 ? (int) ceil($totalRegistros / $this->porPagina) : 0;

        $pagina = is_numeric($consulta['page'] ?? null) ? (int) $consulta['page'] : 1;
        $this->pagina = max(1, min($pagina, max(1, $this->totalPaginas)));

        $this->offset = ($this->pagina - 1) * $this->porPagina;
    }

    /**
     * Ejecuta una consulta agregándole LIMIT y OFFSET de la página actual.
     *
     * @param PDO          $db       Conexión.
     * @param string       $sql      Consulta SELECT sin LIMIT ni OFFSET.
     * @param list<mixed>  $params   Parámetros posicionales de la consulta.
     * @return list<array<string, mixed>>
     */
    public function consultar(PDO $db, string $sql, array $params = []): array
    {
        $stmt = $db->prepare($sql . ' LIMIT ? OFFSET ?');

        $indice = 1;
        foreach ($params as $valor) {
            $stmt->bindValue($indice++, $valor);
        }
        // LIMIT y OFFSET deben enlazarse como enteros.
        $stmt->bindValue($indice++, $this->porPagina, PDO::PARAM_INT);
        $stmt->bindValue($indice, $this->offset, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll();
    }
}
