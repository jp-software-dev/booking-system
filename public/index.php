<?php

/**
 * VISTA: PÁGINA DE INICIO
 *
 * Presenta MediAgenda mostrando el producto en lugar de describirlo:
 *   - Primera pantalla: el mensaje y, a la derecha, un pastillero de ejemplo
 *     cuya tapa se levanta (la firma visual de la app).
 *   - Cómo funciona: agendar, recetar y recordar, con fragmentos reales de la interfaz.
 *   - Los tres roles, con acceso directo a sus cuentas de prueba en modo demo.
 *
 * Los datos del pastillero de ejemplo son ilustrativos (están marcados como ejemplo).
 * Acceso: público. Con sesión, los botones llevan a la página de inicio del rol.
 */

require_once __DIR__ . '/../config/config.php';

use App\Demo\ModoDemo;
use App\Http\Sesion;

// === DATOS ===
$autenticado = Sesion::estaAutenticado();
$esDemo = ModoDemo::activo();
$destinoPrincipal = $autenticado ? Sesion::paginaInicio() : 'login.php';
$textoPrincipal = $autenticado ? 'Ir a mi panel' : ($esDemo ? 'Probar la demo' : 'Iniciar sesión');

// Pastillero de ejemplo: [día, momento, medicamento, hora, ¿tomada?]
$ejemplo = [
    ['lun', 'manana', 'Amoxicilina', '08:00', true], ['mar', 'manana', 'Amoxicilina', '08:00', true], ['mié', 'manana', 'Amoxicilina', '08:00', false],
    ['lun', 'tarde', 'Amoxicilina', '16:00', true], ['mar', 'tarde', 'Amoxicilina', '16:00', true], ['mié', 'tarde', 'Amoxicilina', '16:00', false],
    ['lun', 'noche', 'Loratadina', '22:00', true], ['mar', 'noche', 'Loratadina', '22:00', true], ['mié', 'noche', 'Loratadina', '22:00', false],
];

$roles = [
    ['momento' => 'noche', 'rol' => 'Paciente', 'usuario' => ModoDemo::PACIENTE_EMAIL, 'icono' => 'bi-capsule',
     'texto' => 'Agenda sus citas, ve su semana como un pastillero y recibe un aviso a la hora de cada toma.'],
    ['momento' => 'tarde', 'rol' => 'Médico', 'usuario' => ModoDemo::DOCTOR_USUARIO, 'icono' => 'bi-heart-pulse',
     'texto' => 'Revisa su semana de consultas, receta en segundos y ve si el paciente sigue su tratamiento.'],
    ['momento' => 'manana', 'rol' => 'Clínica', 'usuario' => ModoDemo::ADMIN_USUARIO, 'icono' => 'bi-clipboard2-pulse',
     'texto' => 'Controla citas, equipo, consultorios y métricas, con una bitácora de cada acción.'],
];

// === VISTA ===
$estilosExtra = ['css/calendar.css']; // horarios de ejemplo en "Cómo funciona"
require __DIR__ . '/../src/views/layout/header.php';
?>
<!-- === PRIMERA PANTALLA === -->
<section class="portada-hero">
    <div class="container">
        <div class="row align-items-center g-4 g-lg-5">
            <div class="col-lg-6">
                <h1 class="portada-titulo">Tu salud, en orden como tu pastillero.</h1>
                <p class="portada-lema">Citas, recetas y un aviso a la hora de cada toma, en un solo lugar. El paciente sabe qué le toca, el médico receta y la clínica lo ve todo.</p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="<?= e($destinoPrincipal) ?>" class="btn btn-primary btn-lg px-4"><?= e($textoPrincipal) ?></a>
                    <?php if (!$autenticado): ?>
                        <a href="registro.php" class="btn btn-light btn-lg px-4">Crear una cuenta</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-6">
                <figure class="portada-pastillero mb-0" aria-label="Ejemplo: semana de un paciente en el pastillero de MediAgenda">
                    <figcaption class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fw-bold">Mi semana</span>
                        <span class="ma-chip">Ejemplo</span>
                    </figcaption>
                    <div class="pastillero" style="grid-template-columns: 76px repeat(3, minmax(0, 1fr));">
                        <div></div>
                        <div class="pastillero-dia">lun<b>2</b></div>
                        <div class="pastillero-dia">mar<b>3</b></div>
                        <div class="pastillero-dia hoy">mié<b>4</b></div>
                        <?php foreach (['manana' => 'Mañana', 'tarde' => 'Tarde', 'noche' => 'Noche'] as $momento => $etiqueta): ?>
                            <div class="pastillero-momento <?= $momento ?>"><?= $etiqueta ?></div>
                            <?php foreach (array_filter($ejemplo, static fn (array $t): bool => $t[1] === $momento) as [$dia, , $medicamento, $hora, $tomada]): ?>
                                <div class="compartimento <?= $momento ?><?= $tomada ? ' tomada' : '' ?><?= $dia === 'mié' && $momento === 'manana' ? ' hoy portada-tapa-viva' : '' ?>">
                                    <span class="tapa"></span>
                                    <span class="dosis"><span><?= e($medicamento) ?></span><time><?= e($hora) ?></time></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                    <div class="ma-aviso portada-aviso" aria-hidden="true">
                        <i class="bi bi-bell-fill" style="color: #ffd27a;"></i>
                        <span><strong>Hora de tu medicamento (08:00)</strong><br>Amoxicilina · 1 cápsula de 500 mg</span>
                        <span></span>
                    </div>
                </figure>
            </div>
        </div>
    </div>
</section>

<!-- === CÓMO FUNCIONA === -->
<section class="portada-seccion">
    <div class="container">
        <h2 class="portada-subtitulo">De la cita a la última toma</h2>
        <ol class="portada-pasos list-unstyled">
            <li>
                <span class="portada-paso-numero">1</span>
                <h3>El paciente agenda</h3>
                <p>Elige médico, día y hora entre los horarios libres de ese médico.</p>
                <div class="portada-muestra d-flex gap-2">
                    <span class="time-slot manana" style="width: auto; padding-inline: 14px; margin: 0;">10:00 AM</span>
                    <span class="time-slot tarde" style="width: auto; padding-inline: 14px; margin: 0;">4:30 PM</span>
                </div>
            </li>
            <li>
                <span class="portada-paso-numero">2</span>
                <h3>El médico receta</h3>
                <p>Cada medicamento con su dosis, cada cuántas horas y por cuántos días.</p>
                <div class="portada-muestra small fw-semibold text-secondary">
                    <div class="fw-bold text-dark-blue">Amoxicilina · cada 8 horas · 7 días</div>
                    21 tomas · a las 08:00, 16:00 y 00:00
                </div>
            </li>
            <li>
                <span class="portada-paso-numero">3</span>
                <h3>Llega el aviso</h3>
                <p>A la hora de cada toma, aunque la app esté cerrada. Un toque y queda registrada.</p>
                <div class="portada-muestra d-flex align-items-center gap-3">
                    <span class="toma-boton" style="background: var(--ma-exito); border-color: var(--ma-exito); color: #fff;"><i class="bi bi-check-lg" aria-hidden="true"></i></span>
                    <span class="small fw-bold">08:00 · Amoxicilina<br><span class="text-secondary fw-semibold">Tomada</span></span>
                </div>
            </li>
        </ol>
    </div>
</section>

<!-- === LOS TRES ROLES === -->
<section class="portada-seccion portada-seccion-clara">
    <div class="container">
        <h2 class="portada-subtitulo"><?= $esDemo && !$autenticado ? 'Pruébala desde cualquier lado de la consulta' : 'Una plataforma, tres miradas' ?></h2>
        <div class="row g-3">
            <?php foreach ($roles as $rol): ?>
                <div class="col-md-4">
                    <div class="compartimento <?= e($rol['momento']) ?> h-100 justify-content-start p-4 pt-5">
                        <span class="tapa" style="height: 12px;"></span>
                        <i class="bi <?= e($rol['icono']) ?> fs-2 mb-2" style="color: var(--tapa);" aria-hidden="true"></i>
                        <h3 class="fs-5 fw-bold mb-1"><?= e($rol['rol']) ?></h3>
                        <p class="text-secondary mb-3"><?= e($rol['texto']) ?></p>
                        <?php if ($esDemo && !$autenticado): ?>
                            <code class="mt-auto"><?= e($rol['usuario']) ?></code>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($esDemo && !$autenticado): ?>
            <p class="text-secondary mt-3 mb-0">Contraseña de las tres cuentas: <code><?= e(ModoDemo::PASSWORD) ?></code> · también puedes entrar con un clic desde <a href="login.php" class="fw-bold text-decoration-none">Iniciar sesión</a>.</p>
        <?php endif; ?>
    </div>
</section>

<!-- === CIERRE === -->
<section class="portada-cierre">
    <div class="container d-flex flex-wrap align-items-center justify-content-between gap-4">
        <div>
            <h2 class="fw-bold mb-1 text-white">Tu próxima cita y tu próxima toma, a la vista.</h2>
            <p class="mb-0 portada-cierre-texto">Sin llamadas para agendar y sin olvidar un medicamento.</p>
        </div>
        <a href="<?= e($autenticado ? $destinoPrincipal : 'registro.php') ?>" class="btn btn-light btn-lg px-4"><?= $autenticado ? 'Ir a mi panel' : 'Crear mi cuenta' ?></a>
    </div>
</section>

<?php require __DIR__ . '/../src/views/layout/footer.php'; ?>
