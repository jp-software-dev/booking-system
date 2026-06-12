<?php
/**
 * PIE DE PÁGINA GLOBAL (FOOTER)
 *
 * Cierra las etiquetas HTML principales del layout, carga los scripts de Bootstrap
 * y el cliente API, y añade condicionalmente el script del calendario si es
 * necesario.
 */
?>
</main>

<footer class="footer py-4 mt-auto bg-white border-top">
    <div class="container text-center text-muted small">
        &copy; <?php echo date('Y'); ?> <span class="fw-bold text-primary">MediAgenda</span>. Todos los derechos reservados.
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/api_client.js"></script>

<?php if(isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) === 'agenda.php'): ?>
    <script src="assets/js/calendar.js?v=<?php echo time(); ?>"></script>
<?php endif; ?>

</body>
</html>