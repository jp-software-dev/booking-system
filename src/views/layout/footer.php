<?php
/**
 * LAYOUT: PIE DE PÁGINA
 *
 * Cierra el contenido principal, dibuja el pie de página, el contenedor de los
 * avisos flotantes y carga Bootstrap. (api_client.js se carga en el <head>.)
 *
 * Variables opcionales que la página puede definir antes de incluirlo:
 * @var list<string> $scriptsExtra Scripts adicionales dentro de public/assets
 *                                 (ej. ['js/calendar.js']).
 */
?>
</main>

<footer class="ma-footer py-4 mt-auto">
    <div class="<?= e($anchoContenedor ?? 'container') ?> text-center">
        &copy; <?= date('Y') ?> <strong class="text-dark-blue"><?= e(APP_NAME) ?></strong>. Todos los derechos reservados.
    </div>
</footer>

<!-- Avisos flotantes (los crea notificar() de api_client.js). -->
<div class="ma-avisos" id="maAvisos" aria-live="polite" aria-atomic="false"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php foreach ($scriptsExtra ?? [] as $script): ?>
    <script src="<?= e(asset($script)) ?>"></script>
<?php endforeach; ?>

</body>
</html>
