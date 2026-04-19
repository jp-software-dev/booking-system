<?php
/**
 * PIE DE PÁGINA GLOBAL (FOOTER)
 *
 * Cierra las etiquetas HTML principales del layout, carga los scripts de Bootstrap
 * y el cliente API, y añade condicionalmente el script del calendario si es
 * necesario.
 *
 * @requires basename($_SERVER['PHP_SELF']) para detectar la página actual.
 */

?>
</main>

<footer class="footer py-4 mt-auto bg-white border-top">
    <div class="container text-center text-muted small">
        &copy; <?php echo date('Y'); ?> <span class="fw-bold text-primary">MediAgenda</span>. Todos los derechos reservados.
        
        <?php if(basename($_SERVER['PHP_SELF']) === 'login_paciente.php'): ?>
            <a href="login.php" class="ms-3 text-muted opacity-50 hover-primary" title="Acceso Administrativo">
                <i class="bi bi-shield-lock-fill"></i>
            </a>
        <?php endif; ?>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/api_client.js"></script>

<?php if(basename($_SERVER['PHP_SELF']) === 'agenda.php'): ?>
    <script src="assets/js/calendar.js?v=<?php echo time(); ?>"></script>
<?php endif; ?>
</body>
</html>