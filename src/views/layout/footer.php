<?php
/**
 * LAYOUT: PIE DE PÁGINA
 *
 * Cierra el contenido principal, dibuja el pie de página y carga Bootstrap.
 * (api_client.js se carga en el <head>, ver header.php.)
 *
 * Variables opcionales que la página puede definir antes de incluirlo:
 * @var list<string> $scriptsExtra Scripts adicionales dentro de public/assets
 *                                 (ej. ['js/calendar.js']).
 */
?>
</main>

<footer class="footer py-4 mt-auto bg-white border-top">
    <div class="container text-center text-muted small">
        &copy; <?= date('Y') ?> <span class="fw-bold text-primary"><?= e(APP_NAME) ?></span>. Todos los derechos reservados.
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php foreach ($scriptsExtra ?? [] as $script): ?>
    <script src="<?= e(asset($script)) ?>"></script>
<?php endforeach; ?>

</body>
</html>
