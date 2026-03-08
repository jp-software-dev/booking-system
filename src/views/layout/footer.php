<?php

?>
</main>

<footer class="footer py-4 mt-auto bg-white border-top">
    <div class="container text-center text-muted small">
        &copy; <?php 
        // AÑO DINÁMICO: Imprime automáticamente el año actual del servidor para mantener el copyright siempre actualizado sin hacerlo manualmente.
        echo date('Y'); 
        ?> <span class="fw-bold text-primary">MediAgenda</span>. Todos los derechos reservados.
        
        <?php 
        // ENLACE OCULTO: Evalúa si la página actual es el login público para renderizar un ícono discreto que lleva al panel de administradores.
        if(basename($_SERVER['PHP_SELF']) === 'login_paciente.php'): 
        ?>
            <a href="login.php" class="ms-3 text-muted opacity-50 hover-primary" title="Acceso Administrativo">
                <i class="bi bi-shield-lock-fill"></i>
            </a>
        <?php endif; ?>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/api_client.js"></script>

<?php 
// CARGA CONDICIONAL: Verifica si la vista actual es la agenda para importar el script pesado del calendario solo donde realmente se necesita.
if(basename($_SERVER['PHP_SELF']) === 'agenda.php'): 
?>
    <script src="assets/js/calendar.js?v=<?php 
    // PREVENCIÓN DE CACHÉ: Concatena la marca de tiempo (timestamp) en la URL para forzar al navegador a descargar siempre tu última versión del JS.
    echo time(); 
    ?>"></script>
<?php endif; ?>
</body>
</html>