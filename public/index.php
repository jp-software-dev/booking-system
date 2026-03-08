<?php
// PÁGINA DE INICIO (INDEX): Vista principal del sistema que combina marketing (Hero/Servicios) con funcionalidad dinámica para pacientes autenticados.

// GESTIÓN DE SESIÓN: Reanuda la sesión del usuario para personalizar la experiencia y verificar permisos de acceso.
session_start();

// DEPENDENCIAS: Carga la cabecera global y la conexión centralizada a la base de datos necesaria para las consultas.
require_once '../src/views/layout/header.php';
require_once '../config/Database.php';

// RUTEO DINÁMICO: Determina el destino del botón principal; si el usuario no ha iniciado sesión, lo redirige al login de pacientes.
$ruta_agendar = isset($_SESSION['user_id']) ? 'agenda.php' : 'login_paciente.php';

// CONTENEDOR DE CITAS: Inicializa un arreglo vacío para almacenar los próximos compromisos médicos del paciente.
$proximas_citas = [];

// LÓGICA DE USUARIO: Si hay una sesión activa de rol 'paciente', procede a extraer sus datos de salud en tiempo real.
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'paciente') {
    
    // CONEXIÓN DB: Obtiene la instancia única de la base de datos (Singleton).
    $db = Database::getInstance();
    
    // CONSULTA FILTRADA: Recupera las próximas 3 citas que aún no han ocurrido (CURDATE), incluyendo el nombre del especialista.
    $stmt = $db->prepare("SELECT c.fecha_cita, c.hora_inicio, d.nombre AS doctor, d.apellido_paterno, c.estado_cita
                           FROM citas c
                           JOIN doctores d ON c.id_doctor = d.id_doctor
                           WHERE c.id_paciente = ? AND c.fecha_cita >= CURDATE()
                           ORDER BY c.fecha_cita ASC, c.hora_inicio ASC
                           LIMIT 3");
                           
    // EJECUCIÓN SEGURA: Utiliza el ID de la sesión para evitar que el paciente pueda ver citas que no le pertenecen.
    $stmt->execute([$_SESSION['user_id']]);
    $proximas_citas = $stmt->fetchAll();
}
?>

<section class="hero">
    <div class="container hero-container">
        <div class="hero-text">
            <h6 class="hero-pre-title">MediAgenda Elite</h6>
            <h1 class="hero-title">Gestión médica <span class="text-primary">de precisión</span><br>al alcance de tu mano</h1>
            <p class="hero-description">Diseñado para profesionales de la salud y pacientes que exigen eficiencia, seguridad y control total en la administración de citas médicas.</p>
            <div class="hero-buttons">
                <a href="<?php echo $ruta_agendar; ?>" class="btn btn-primary">Agendar cita ahora</a>
                <a href="#servicios" class="btn btn-outline-primary">Conoce más</a>
            </div>
        </div>
        <div class="hero-image">
            <img src="assets/img/hero-doctor.jpg" alt="Médico con paciente" class="img-fluid rounded-4 shadow-lg">
        </div>
    </div>
</section>

<?php 
// RENDERIZADO CONDICIONAL: Solo muestra el bloque de "Próximas citas" si el paciente tiene registros vigentes en la base de datos.
if (!empty($proximas_citas)): 
?>
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="fw-bold text-dark-blue mb-4">Tus próximas citas</h2>
        <div class="row g-4">
            <?php 
            // ITERACIÓN DE DATOS: Recorre el arreglo de citas obtenidas para generar dinámicamente las tarjetas informativas.
            foreach ($proximas_citas as $cita): 
            ?>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-calendar-check fs-2 text-primary me-3"></i>
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><?php 
                                // FORMATEO DE FECHA: Convierte el formato YYYY-MM-DD de MySQL a un formato legible (Día/Mes/Año).
                                echo date('d/m/Y', strtotime($cita['fecha_cita'])); 
                                ?></h6>
                                <p class="text-secondary small mb-0"><?php 
                                // FORMATEO DE HORA: Convierte el formato de 24h a 12h con indicador AM/PM.
                                echo date('h:i A', strtotime($cita['hora_inicio'])); 
                                ?></p>
                            </div>
                        </div>
                        <p class="text-secondary">Dr. <?php 
                        // SANITIZACIÓN: Limpia los nombres de los doctores para prevenir inyecciones de código malicioso en el navegador (XSS).
                        echo htmlspecialchars($cita['doctor'] . ' ' . $cita['apellido_paterno']); 
                        ?></p>
                        <span class="badge <?php 
                        // ESTADO DINÁMICO: Aplica clases de Bootstrap (Colores) según el estatus actual de la cita médica.
                        echo $cita['estado_cita'] === 'Pendiente' ? 'bg-warning' : ($cita['estado_cita'] === 'Confirmada' ? 'bg-success' : 'bg-secondary'); 
                        ?>">
                            <?php echo $cita['estado_cita']; ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section id="servicios" class="py-5 bg-white">
    <div class="container py-4">
        <div class="text-center mb-5">
            <h2 class="fw-bold text-dark-blue display-6">¿Por qué elegirnos?</h2>
            <p class="text-secondary">Tecnología médica de vanguardia al servicio de tu bienestar</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 p-4 text-center">
                    <i class="bi bi-calendar-check-fill fs-1 text-primary mb-3"></i>
                    <h5 class="fw-bold text-dark-blue">Gestión Inteligente de Citas</h5>
                    <p class="text-secondary">Optimiza tu tiempo con nuestra agenda dinámica en tiempo real. Visualiza disponibilidad, agenda en segundos y recibe confirmaciones automáticas.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 p-4 text-center">
                    <i class="bi bi-shield-lock-fill fs-1 text-primary mb-3"></i>
                    <h5 class="fw-bold text-dark-blue">Seguridad y Confidencialidad</h5>
                    <p class="text-secondary">Tus datos médicos están protegidos con encriptación de nivel bancario. Cumplimos con las normativas de privacidad para garantizar tu tranquilidad.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 p-4 text-center">
                    <i class="bi bi-bell-fill fs-1 text-primary mb-3"></i>
                    <h5 class="fw-bold text-dark-blue">Recordatorios Automáticos</h5>
                    <p class="text-secondary">Recibe notificaciones por correo electrónico para no olvidar tus citas. Reducimos las ausencias y mejoramos tu experiencia de atención.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="cta-section py-4">
    <div class="container text-center">
        <h2 class="fw-bold text-dark-blue mb-3">¿Listo para transformar tu experiencia médica?</h2>
        <a href="registro.php" class="btn btn-light btn-lg rounded-pill px-5 py-2 fw-bold shadow-sm">Crear cuenta gratuita</a>
    </div>
</section>

<?php 
// PIE DE PÁGINA: Carga el footer para cerrar las etiquetas del documento y cargar scripts de Bootstrap/JS.
require_once '../src/views/layout/footer.php'; 
?>