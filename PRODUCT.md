# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Visitante del portafolio (audiencia principal):** cliente potencial de Vanguard DevSec que llega desde vanguarddevsec.com y prueba la demo pública con las cuentas de prueba. Decide en uno o dos minutos si la calidad le convence.
- **Paciente:** agenda, reprograma y cancela citas; consulta su historial; recibe recetas y programa recordatorios de sus medicamentos. Usa sobre todo el celular.
- **Doctor:** consulta sus citas y pacientes, emite recetas con medicamentos y revisa el historial de recetas emitidas.
- **Administrador (recepción de la clínica):** gestiona citas, pacientes, consultorios y personal, crea los accesos de los doctores, revisa métricas y consulta la bitácora de auditoría.

## Product Purpose

Plataforma de gestión de citas médicas para una clínica. Hoy su función principal es servir como demo de portafolio de Vanguard DevSec en https://mediagenda.vanguarddevsec.com: debe verse y comportarse como un producto real y terminado. Los datos de la demo son ficticios y se regeneran cada día.

## Operating Context

- Demo pública con tres cuentas de prueba (administrador, paciente y doctor) visibles en el inicio de sesión.
- Pacientes en celular la mayor parte del tiempo; recepción y doctores en escritorio.
- Los recordatorios de medicamentos llegan como notificaciones push del navegador, aunque la página esté cerrada.

## Capabilities and Constraints

- PHP puro (sin framework) con Bootstrap 5, JavaScript sin framework y Chart.js; dependencias con Composer (Dompdf, PHPMailer, web-push).
- SQL compatible con MariaDB/MySQL (XAMPP) y PostgreSQL (Supabase, demo en Vercel). Cada cambio de base lleva migración para ambos.
- Despliegue en Vercel con el runtime vercel-php; un solo punto de entrada (`api/vercel.php`) con lista blanca de rutas.
- Roles: admin, doctor y paciente. La bitácora es de solo lectura; solo se exporta a CSV y PDF.
- Horario de citas: lunes a sábado, cada 30 minutos (`App\Helpers\Horario`).
- Todo el texto de la interfaz y del código está en español.

## Brand Commitments

- El nombre **MediAgenda** se conserva. El resto de la identidad visual (colores, tipografía, video de portada) puede reemplazarse.
- Personalidad: clínica moderna y cálida. Confiable y profesional, cercana y humana con el paciente; nunca fría ni burocrática.

## Evidence on Hand

- Ninguna clínica, testimonio, cifra o certificación real. No inventar pacientes reales, reseñas, premios, normativas cumplidas ni estadísticas de uso.
- Datos de contacto de la demo: ficticios (variables `CONTACTO_*` en Vercel).

## Product Principles

1. Primero debe sentirse real: cada pantalla tiene que poder mostrarse a un cliente sin explicaciones.
2. El paciente es humano, no un expediente: lenguaje claro y cercano, sin jerga administrativa.
3. Las tareas frecuentes (agendar, tomar el medicamento, revisar el día) en el menor número de pasos.
4. Nada de datos personales reales en la demo: el registro lo recuerda.
5. Seguridad visible pero discreta: roles, auditoría y protección de datos sin estorbar.

## Accessibility & Inclusion

- Pacientes de todas las edades: texto legible, contraste suficiente, objetivos táctiles cómodos en celular.
- Respetar `prefers-reduced-motion` en todas las animaciones.
