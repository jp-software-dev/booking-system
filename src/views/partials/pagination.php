<?php
/**
 * Paginador compacto reutilizable: « Anterior  1 … 4 5 [6] 7 8 … N  Siguiente »
 * más selector de tamaño de página. Usado por admin.php y admin_historial.php.
 *
 * Variables esperadas en scope antes del include:
 *   $page              int    página actual, ya acotada a [1, $total_pages]
 *   $total_pages       int
 *   $total_records     int    total de registros que coinciden con el filtro
 *   $items_count       int    registros mostrados en la página actual
 *   $base_params       array  copia de $_GET SIN la llave 'page' (se agrega al vuelo)
 *   $per_page          int    tamaño de página actual
 *   $per_page_options  array  opciones para el selector, ej. [100, 200, 500]
 *
 * @var int $page
 * @var int $total_pages
 * @var int $total_records
 * @var int $items_count
 * @var array $base_params
 * @var int $per_page
 * @var array $per_page_options
 */
$pagUrl = function (array $params, int $p): string {
    $params['page'] = $p;
    return '?' . http_build_query($params);
};

$ventana = 2;
$rango = [];
for ($i = max(1, $page - $ventana); $i <= min($total_pages, $page + $ventana); $i++) $rango[] = $i;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-4">
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <div class="text-secondary small fw-medium">
            Mostrando <?php echo $items_count; ?> de <?php echo $total_records; ?> registro(s). Página <?php echo $page; ?> de <?php echo $total_pages > 0 ? $total_pages : 1; ?>.
        </div>
        <?php if (!empty($per_page_options)): ?>
        <form method="GET" class="d-flex align-items-center gap-2 small mb-0">
            <?php foreach ($base_params as $k => $v): if ($k === 'per_page') continue; ?>
                <input type="hidden" name="<?php echo htmlspecialchars($k); ?>" value="<?php echo htmlspecialchars($v); ?>">
            <?php endforeach; ?>
            <label for="perPageSelect" class="text-secondary mb-0">Por página:</label>
            <select id="perPageSelect" name="per_page" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                <?php foreach ($per_page_options as $opt): ?>
                    <option value="<?php echo $opt; ?>" <?php echo $per_page == $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <nav aria-label="Paginación">
        <ul class="pagination mb-0 shadow-sm flex-wrap">
            <?php $prevDisabled = $page <= 1 ? 'disabled' : ''; ?>
            <li class="page-item <?php echo $prevDisabled; ?>"><a class="page-link" href="<?php echo htmlspecialchars($pagUrl($base_params, $page - 1)); ?>">&laquo; Anterior</a></li>

            <?php if (!in_array(1, $rango, true)): ?>
                <li class="page-item"><a class="page-link" href="<?php echo htmlspecialchars($pagUrl($base_params, 1)); ?>">1</a></li>
                <?php if ($rango[0] > 2): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
            <?php endif; ?>

            <?php foreach ($rango as $i): $active = $i === $page ? 'active' : ''; ?>
                <li class="page-item <?php echo $active; ?>"><a class="page-link" href="<?php echo htmlspecialchars($pagUrl($base_params, $i)); ?>"><?php echo $i; ?></a></li>
            <?php endforeach; ?>

            <?php if (!in_array($total_pages, $rango, true)): ?>
                <?php if (end($rango) < $total_pages - 1): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                <li class="page-item"><a class="page-link" href="<?php echo htmlspecialchars($pagUrl($base_params, $total_pages)); ?>"><?php echo $total_pages; ?></a></li>
            <?php endif; ?>

            <?php $nextDisabled = $page >= $total_pages ? 'disabled' : ''; ?>
            <li class="page-item <?php echo $nextDisabled; ?>"><a class="page-link" href="<?php echo htmlspecialchars($pagUrl($base_params, $page + 1)); ?>">Siguiente &raquo;</a></li>
        </ul>
    </nav>
    <?php endif; ?>
</div>
