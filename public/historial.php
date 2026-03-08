<?php
// VISTA DE HISTORIAL: Interfaz privada que permite al paciente consultar el registro histórico de todas sus citas médicas, pasadas y futuras.

// INICIALIZACIÓN: Reanuda la sesión para identificar al usuario y validar su nivel de acceso al sistema.
session_start();

// DEPENDENCIAS: Importa la conexión a la base de datos y la cabecera visual del portal.
require_once '../config/Database.php';
require_once '../src/views/layout/header.php';

// CONTROL DE ACCESO: Verifica estrictamente que exista una sesión activa y que pertenezca al rol 'paciente' para evitar accesos no autorizados.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'paciente') {
    header("Location: login_paciente.php");
    exit();
}

// CONEXIÓN DB: Obtiene la instancia única de la base de datos mediante el patrón Singleton.
$db = Database::getInstance();

// CONSULTA DE REGISTROS: Extrae la información detallada de las citas vinculando la tabla de doctores para mostrar nombres reales.
// ORDENAMIENTO: Organiza los resultados de forma descendente (DESC) para mostrar primero las citas más recientes.
$stmt = $db->prepare("SELECT c.fecha_cita, c.hora_inicio, d.nombre AS doctor, d.apellido_paterno, c.motivo_consulta, c.estado_cita
                       FROM citas c
                       JOIN doctores d ON c.id_doctor = d.id_doctor
                       WHERE c.id_paciente = ?
                       ORDER BY c.fecha_cita DESC, c.hora_inicio DESC");

// EJECUCIÓN SEGURA: Filtra los resultados exclusivamente por el ID del usuario en sesión para garantizar la privacidad de los datos médicos.
$stmt->execute([$_SESSION['user_id']]);
$historial = $stmt->fetchAll();
?>

<div class="container py-5">
    <h2 class="fw-bold text-dark-blue mb-4">Mi Historial Médico</h2>
    <?php 
    // VALIDACIÓN DE CONTENIDO: Comprueba si el arreglo de resultados está vacío para mostrar un mensaje informativo en lugar de una tabla vacía.
    if (empty($historial)): 
    ?>
        <div class="alert alert-info">No hay citas registradas en tu historial.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Doctor</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // ITERACIÓN DINÁMICA: Recorre cada registro del historial para generar las filas de la tabla de forma automática.
                    foreach ($historial as $cita): 
                    ?>
                    <tr>
                        <td><?php 
                        // FORMATEO DE FECHA: Convierte el dato crudo de la base de datos a un formato legible por humanos (DD/MM/YYYY).
                        echo date('d/m/Y', strtotime($cita['fecha_cita'])); 
                        ?></td>
                        <td><?php 
                        // FORMATEO DE HORA: Transforma el formato de 24 horas a un formato de 12 horas con indicador AM/PM.
                        echo date('h:i A', strtotime($cita['hora_inicio'])); 
                        ?></td>
                        <td>Dr. <?php 
                        // SANITIZACIÓN: Protege la salida de datos contra ataques XSS al imprimir el nombre del especialista.
                        echo htmlspecialchars($cita['doctor'] . ' ' . $cita['apellido_paterno']); 
                        ?></td>
                        <td><?php 
                        // FALLBACK DE TEXTO: Muestra el motivo de la consulta o un indicador de "No aplica" si el campo está vacío.
                        echo htmlspecialchars($cita['motivo_consulta'] ?? 'N/A'); 
                        ?></td>
                        <td>
                            <span class="badge <?php 
                            // LÓGICA DE ESTILOS: Asigna una clase de color de Bootstrap diferente para cada estado posible de la cita.
                            echo $cita['estado_cita'] === 'Pendiente' ? 'bg-warning' : ($cita['estado_cita'] === 'Confirmada' ? 'bg-success' : ($cita['estado_cita'] === 'Cancelada' ? 'bg-danger' : 'bg-secondary')); 
                            ?>">
                                <?php echo $cita['estado_cita']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <div class="mt-3">
        <a href="agenda.php" class="btn btn-primary rounded-pill px-4">Agendar nueva cita</a>
    </div>
</div>

<?php 
// PIE DE PÁGINA: Carga el layout del footer para cerrar el documento y cargar los scripts globales necesarios.
require_once '../src/views/layout/footer.php'; 
?>