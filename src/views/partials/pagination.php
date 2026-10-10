<?php
/**
 * PARCIAL: PAGINADOR DE LISTADOS
 *
 * Dibuja «Anterior  1 … 4 5 [6] 7 8 … N  Siguiente» y el selector de registros
 * por página. Lo usan admin.php, admin_historial.php, admin_bitacora.php y
 * admin_pacientes.php.
 *
 * Variables que la página debe definir antes de incluirlo:
 * @var \App\Helpers\Paginacion $paginacion     Estado de la paginación.
 * @var int                     $itemsMostrados Registros mostrados en la página actual.
 */

// Parámetros actuales de la URL (filtros) sin "page"; se descartan valores que no sean texto.
$parametrosBase = array_filter($_GET, static fn ($valor, $clave): bool => is_string($valor) && $clave !== 'page', ARRAY_FILTER_USE_BOTH);

/**
 * URL de una página conservando los filtros actuales.
 */
$urlPagina = static function (int $numero) use ($parametrosBase): string {
    return '?' . http_build_query($parametrosBase + ['page' => $numero]);
};

// Ventana de páginas alrededor de la actual.
$ventana = 2;
$inicio = max(1, $paginacion->pagina - $ventana);
$fin = min($paginacion->totalPaginas, $paginacion->pagina + $ventana);
$rango = $fin >= $inicio ? range($inicio, $fin) : [];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-4">
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <div class="text-secondary small fw-medium">
            Mostrando <?= $itemsMostrados ?> de <?= $paginacion->totalRegistros ?> registro(s).
            Página <?= $paginacion->pagina ?> de <?= max(1, $paginacion->totalPaginas) ?>.
        </div>

        <form method="GET" class="d-flex align-items-center gap-2 small mb-0">
            <?php foreach ($parametrosBase as $clave => $valor): ?>
                <?php if ($clave !== 'per_page'): ?>
                    <input type="hidden" name="<?= e($clave) ?>" value="<?= e($valor) ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            <label for="perPageSelect" class="text-secondary mb-0">Por página:</label>
            <select id="perPageSelect" name="per_page" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                <?php foreach (\App\Helpers\Paginacion::OPCIONES_POR_PAGINA as $opcion): ?>
                    <option value="<?= $opcion ?>" <?= $paginacion->porPagina === $opcion ? 'selected' : '' ?>><?= $opcion ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($paginacion->totalPaginas > 1): ?>
        <nav aria-label="Paginación">
            <ul class="pagination mb-0 shadow-sm flex-wrap">
                <li class="page-item <?= $paginacion->pagina <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= e($urlPagina($paginacion->pagina - 1)) ?>">&laquo; Anterior</a>
                </li>

                <?php if ($rango !== [] && $rango[0] > 1): ?>
                    <li class="page-item"><a class="page-link" href="<?= e($urlPagina(1)) ?>">1</a></li>
                    <?php if ($rango[0] > 2): ?>
                        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                    <?php endif; ?>
                <?php endif; ?>

                <?php foreach ($rango as $numero): ?>
                    <li class="page-item <?= $numero === $paginacion->pagina ? 'active' : '' ?>">
                        <a class="page-link" href="<?= e($urlPagina($numero)) ?>"><?= $numero ?></a>
                    </li>
                <?php endforeach; ?>

                <?php if ($rango !== [] && end($rango) < $paginacion->totalPaginas): ?>
                    <?php if (end($rango) < $paginacion->totalPaginas - 1): ?>
                        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                    <?php endif; ?>
                    <li class="page-item"><a class="page-link" href="<?= e($urlPagina($paginacion->totalPaginas)) ?>"><?= $paginacion->totalPaginas ?></a></li>
                <?php endif; ?>

                <li class="page-item <?= $paginacion->pagina >= $paginacion->totalPaginas ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= e($urlPagina($paginacion->pagina + 1)) ?>">Siguiente &raquo;</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
