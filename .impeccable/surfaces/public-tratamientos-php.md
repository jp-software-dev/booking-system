---
version: 1
slug: "public-tratamientos-php"
primary_target: "public/tratamientos.php"
related_targets: ["src/views/layout/header.php","public/index.php"]
---

# Superficie: MediAgenda (aplicación completa)

Modo: Operate en paneles de paciente, doctor y administrador; la portada pública (index.php) es Persuade dentro del mismo mundo.

Audiencia y tarea: visitante del portafolio que prueba la demo; paciente que agenda y sigue su tratamiento (celular); doctor que receta; recepción que administra.

Momento memorable: la semana del paciente como pastillero; al marcar una toma, su tapa se levanta.

## Direction contract

THESIS: La salud del paciente se organiza como su pastillero de 7 días: compartimentos por día y momento (mañana, tarde, noche) con tapas de color. Rechaza el tablero genérico de tarjetas blancas con barra lateral azul.

OWN-WORLD: Fondo claro azul grisáceo #f2f5fa, tinta #16233b, acción azul #2f6fe4; tapas por momento: mañana ámbar #f2b134, tarde verde #3fb489, noche azul #2f6fe4; alerta rosa-rojo #e5677a. Compartimentos redondeados (18-24 px), plástico translúcido, píldoras de navegación. Nunito Sans; cifras tabulares.

STORY: El paciente entiende de un vistazo qué le toca hoy y esta semana, marca sus tomas y agenda; el doctor receta y el tratamiento aparece en el pastillero del paciente; recepción opera con la misma claridad.

FIRST VIEWPORT: Encabezado blanco con marca a la izquierda, navegación en píldoras (la activa rellena en azul) y usuario a la derecha. Columna principal: título "Tu pastillero de la semana", subtítulo con la próxima toma, y la cuadrícula días × momentos con tapas de color (hoy resaltado). Columna derecha de 380 px: próxima cita y lista de hoy con el botón "Agregar recordatorio".

FORM: Pastillero semanal, candidato 1 de 7 de la lista propia (elección del usuario, IMPECCABLE'S PICK); seed:42e7a15045c176c6

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
