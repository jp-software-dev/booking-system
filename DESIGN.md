---
name: MediAgenda
description: Citas, recetas y recordatorios de medicamentos con la forma de un pastillero semanal.
colors:
  fondo-niebla: "#f2f5fa"
  superficie: "#ffffff"
  superficie-2: "#f7f9fc"
  tinta: "#16233b"
  tinta-2: "#4d5b76"
  tinta-3: "#6b7894"
  linea: "#e1e7f0"
  linea-fuerte: "#cfd8e6"
  accion: "#2f6fe4"
  accion-fuerte: "#1f57c3"
  accion-suave: "#e8f0fd"
  tapa-manana: "#f2b134"
  tapa-manana-suave: "#fdf3dc"
  tapa-tarde: "#3fb489"
  tapa-tarde-suave: "#e0f4ec"
  tapa-noche: "#2f6fe4"
  tapa-noche-suave: "#e8f0fd"
  exito: "#1f8a63"
  aviso: "#a86b00"
  alerta: "#e5677a"
  alerta-texto: "#b8324a"
  alerta-suave: "#fde8ec"
  aviso-oscuro: "#6a4500"
  bruma-clara: "#e1ebfd"
  linea-suave: "#c8d3e6"
  campana: "#ffd27a"
  tomada-clara: "#7fe0b7"
  alerta-clara: "#ffb3c0"
typography:
  display:
    fontFamily: "Nunito Sans, Segoe UI, system-ui, sans-serif"
    fontSize: "clamp(2.3rem, 1.4rem + 3.4vw, 4rem)"
    fontWeight: 800
    lineHeight: 1.04
    letterSpacing: "-0.035em"
  headline:
    fontFamily: "Nunito Sans, Segoe UI, system-ui, sans-serif"
    fontSize: "clamp(1.6rem, 1.2rem + 1.4vw, 2.1rem)"
    fontWeight: 800
    letterSpacing: "-0.025em"
  title:
    fontFamily: "Nunito Sans, Segoe UI, system-ui, sans-serif"
    fontSize: "1.05rem"
    fontWeight: 800
  body:
    fontFamily: "Nunito Sans, Segoe UI, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "Nunito Sans, Segoe UI, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 700
  cifra:
    fontFamily: "Nunito Sans, Segoe UI, system-ui, sans-serif"
    fontWeight: 800
    fontFeature: "tnum"
  micro:
    fontFamily: "Nunito Sans, Segoe UI, system-ui, sans-serif"
    fontSize: "0.72rem"
    fontWeight: 800
  caption:
    fontFamily: "Nunito Sans, Segoe UI, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 700
  small:
    fontFamily: "Nunito Sans, Segoe UI, system-ui, sans-serif"
    fontSize: "0.8rem"
    fontWeight: 700
  chip:
    fontFamily: "Nunito Sans, Segoe UI, system-ui, sans-serif"
    fontSize: "0.78rem"
    fontWeight: 800
  lead:
    fontFamily: "Nunito Sans, Segoe UI, system-ui, sans-serif"
    fontSize: "1.15rem"
    fontWeight: 400
  brand:
    fontFamily: "Nunito Sans, Segoe UI, system-ui, sans-serif"
    fontSize: "1.3rem"
    fontWeight: 800
    letterSpacing: "-0.02em"
rounded:
  sm: "10px"
  md: "14px"
  tarjeta: "16px"
  compartimento: "18px"
  campo: "12px"
  modal: "20px"
  portada: "22px"
  pildora: "999px"
components:
  button-primary:
    backgroundColor: "{colors.accion}"
    textColor: "{colors.superficie}"
    rounded: "{rounded.md}"
    padding: "0.6rem 1.1rem"
  button-primary-hover:
    backgroundColor: "{colors.accion-fuerte}"
  button-light:
    backgroundColor: "{colors.superficie}"
    textColor: "{colors.tinta}"
    rounded: "{rounded.md}"
  button-light-hover:
    backgroundColor: "{colors.superficie-2}"
  input:
    backgroundColor: "{colors.superficie}"
    textColor: "{colors.tinta}"
    rounded: "12px"
    padding: "0.62rem 0.9rem"
  chip:
    backgroundColor: "{colors.superficie-2}"
    textColor: "{colors.tinta-2}"
    rounded: "{rounded.pildora}"
    padding: "0.25rem 0.7rem"
  panel:
    backgroundColor: "{colors.superficie}"
    rounded: "{rounded.tarjeta}"
    padding: "1.4rem"
  compartimento-manana:
    backgroundColor: "{colors.tapa-manana-suave}"
    rounded: "{rounded.compartimento}"
  compartimento-tarde:
    backgroundColor: "{colors.tapa-tarde-suave}"
    rounded: "{rounded.compartimento}"
  compartimento-noche:
    backgroundColor: "{colors.tapa-noche-suave}"
    rounded: "{rounded.compartimento}"
---

# Design System: MediAgenda

## Overview

**Creative North Star: "El pastillero semanal"**

Toda la app se lee como el pastillero de plástico que un paciente tiene en la cocina: una cuadrícula de días por momentos (mañana, tarde, noche), cada compartimento con una tapa de color que se levanta cuando la dosis ya se tomó. Esa misma gramática ordena la semana del paciente, la agenda del médico, los horarios para agendar y hasta el horario de la clínica en Contacto. Lo que no cabe en una cuadrícula (listas, tablas, formularios) vive en paneles blancos sobre un fondo de niebla azulada.

La sensación es de clínica moderna y cálida: tipografía redonda y gruesa (Nunito Sans 800 en títulos), esquinas generosas, color solo donde significa algo. El azul es la única voz de acción; el ámbar, el verde y el azul de las tapas dicen *cuándo*, nunca *qué hacer*. El movimiento es corto y con salida exponencial, sin rebotes, y desaparece con `prefers-reduced-motion`.

**Key Characteristics:**
- Cuadrícula día × momento como estructura principal; los compartimentos son la unidad visual.
- Tres colores de tapa ligados a la hora (05–11 mañana, 12–18 tarde, resto noche).
- Un solo color de acción (azul) para botones, enlaces, foco y selección.
- Paneles blancos planos con sombra muy difusa sobre fondo niebla.
- Cifras siempre tabulares (horas, folios, contadores).

## Colors

Neutros azulados fríos con tres tapas cálidas/frescas y un único azul de acción.

### Primary
- **Azul consulta** (accion): botones primarios, enlaces, anillo de foco, día seleccionado, pestaña activa. Su variante **Azul firme** (accion-fuerte) es el hover y el texto sobre fondos azul suave; **Azul bruma** (accion-suave) es el fondo de selección.

### Secondary
- **Ámbar mañana** (tapa-manana) y su fondo **Crema mañana**: tomas y horarios de 05:00 a 11:59.
- **Verde tarde** (tapa-tarde) y **Menta tarde**: de 12:00 a 18:59.
- **Azul noche** (tapa-noche) y **Bruma noche**: de 19:00 a 04:59. Comparte valor con la acción, pero solo aparece como tapa o tinte, nunca como botón.

### Tertiary
- **Verde tomada** (exito): toma registrada, estados completados, chips de éxito.
- **Ocre aviso** (aviso): pendientes y advertencias; solo como texto o chip, nunca como relleno grande.
- **Rosa alerta** (alerta) con texto **Granate alerta** (alerta-texto): cancelaciones, errores, horarios ocupados y días sin citas.

### Neutral
- **Niebla** (fondo-niebla): fondo de página.
- **Blanco panel** (superficie) y **Blanco hueso** (superficie-2): paneles, campos, tablas; hover de botones claros y chips neutros.
- **Tinta noche** (tinta): texto principal. **Tinta media** (tinta-2, 7:1 sobre blanco): texto secundario y etiquetas. **Tinta suave** (tinta-3, 4.6:1): solo texto terciario y placeholders.
- **Línea** y **Línea firme**: divisores y bordes de campos.

### Named Rules
**La regla de la única voz.** Solo el azul de acción invita a hacer clic. Si algo es interactivo y no es azul, es un botón claro con borde de línea.

**La regla del reloj.** Ámbar, verde y azul noche significan hora del día. No se reutilizan para categorías, estados ni decoración.

## Typography

**Display Font:** Nunito Sans (con Segoe UI, system-ui)
**Body Font:** Nunito Sans

**Character:** Una sola familia redonda y amable; la jerarquía sale del peso (400 → 800) y del tamaño, no de mezclar familias.

### Hierarchy
- **Display** (800, clamp 2.3–4rem, 1.04): solo el titular de la portada.
- **Headline** (800, clamp 1.6–2.1rem, tracking −0.025em): el `h1` de cada pantalla.
- **Title** (800, 1.05rem): títulos de panel y de tarjeta.
- **Body** (400, 1rem, 1.5): texto corrido y subtítulos en tinta media.
- **Label** (700, 0.875rem): etiquetas de formulario y metadatos, en tinta media, en minúsculas normales.

Los textos pequeños tienen escala propia: **micro** (0.72rem) para rótulos dentro de compartimentos y fechas, **caption** (0.75rem) y **small** (0.8rem) para metadatos y encabezados de tabla, y **chip** (0.78rem) para las etiquetas. **Lead** (1.15rem) es el texto de apoyo de la portada y **brand** (1.3rem) el nombre en el encabezado.

### Named Rules
**La regla de las cifras.** Horas, fechas, folios y contadores usan números tabulares para que las columnas no bailen.

**La regla sin gritos.** Nada de mayúsculas forzadas ni texto en versales; los títulos van en tipo oración ("Citas por estado", no "CITAS POR ESTADO").

## Layout

Contenedor Bootstrap con la clase de página (`ma-pagina`) y un encabezado de página con `h1`, subtítulo y acciones a la derecha, que pasa a columna en pantallas angostas. Las vistas de trabajo (admin) usan contenedor fluido con márgenes amplios en escritorio. La cuadrícula del pastillero vive dentro de un contenedor con scroll horizontal propio; la página nunca desborda a 390 px. En móvil el paciente navega con una barra inferior de pestañas (74 px) y el resto de roles con menú lateral. Ritmo de espaciado en múltiplos de 4 px, con 1.4rem de relleno interno en paneles.

## Elevation & Depth

Casi plano. Los paneles se separan del fondo niebla por color y por una sombra ambiental muy difusa; solo lo que flota (menús, modales, avisos) usa la sombra alta.

### Shadow Vocabulary
- **Reposo** (`0 1px 2px rgba(22,35,59,.05), 0 6px 18px -10px rgba(22,35,59,.18)`): paneles y tarjetas.
- **Flotante** (`0 2px 6px rgba(22,35,59,.06), 0 22px 48px -20px rgba(22,35,59,.32)`): menús desplegables, modales y avisos.

### Named Rules
**La regla de lo que flota.** Solo lo que está por encima del contenido lleva la sombra alta; nada se eleva en hover salvo un desplazamiento de 1 px.

## Shapes

Esquinas redondas y escalonadas por tamaño: 10 px en controles pequeños, 12 px en campos de formulario, 14 px en botones, 16 px en paneles, 18 px en compartimentos, 20 px en modales y 22 px en el pastillero de la portada; píldora completa solo en chips. La tapa del compartimento tiene esquinas superiores de 16 px e inferiores de 7 px, como una tapa real. Los bordes son de 1 px en línea; los compartimentos usan 1.5 px teñido con el color de su tapa. No hay franjas de color laterales.

## Components

### Buttons
- **Shape:** esquinas suaves (14 px; 10 px en pequeño).
- **Primary:** azul consulta con texto blanco, relleno 0.6rem × 1.1rem.
- **Hover / Focus:** pasa a azul firme; foco con halo azul de 4 px; al presionar escala a 0.97.
- **Light:** blanco con borde de línea, hover blanco hueso. **Outline primary** para acciones secundarias y exportaciones (CSV, PDF).

### Chips
- **Style:** píldora pequeña (0.78rem, 800) sobre blanco hueso con tinta media; variantes de éxito, aviso, info y alerta usan su fondo suave con texto oscuro del mismo tono.
- **State:** informativos, no clicables; resumen de conteos en los encabezados.

### Cards / Containers
- **Corner Style:** 16 px.
- **Background:** blanco panel.
- **Shadow Strategy:** sombra de reposo.
- **Border:** ninguno.
- **Internal Padding:** 1.4rem.

### Inputs / Fields
- **Style:** blanco, borde línea firme, 12 px de radio, relleno 0.62rem × 0.9rem.
- **Focus:** borde azul consulta y halo azul translúcido de 4 px.
- **Error / Disabled:** validación de Bootstrap con los tonos de alerta.

### Navigation
- **Escritorio:** barra superior blanca translúcida (92 %) con desenfoque de fondo; enlaces en peso 700 con hover blanco hueso y el activo en texto blanco sobre azul consulta.
- **Móvil (paciente):** barra inferior fija con cuatro pestañas de icono + texto; la activa en azul sobre azul bruma.
- **Móvil (médico y clínica):** menú lateral (offcanvas).

### Compartimento del pastillero
La pieza firma. Fondo con el tinte suave del momento, borde teñido, altura mínima 92 px y una tapa de 22 px del color del momento. Al registrar la toma la tapa gira sobre su bisagra superior (`rotateX(-74deg)`, 520 ms, salida exponencial) y el texto queda tachado. Variantes: **hoy** (anillo azul de 2 px), **vacío** (borde punteado, sin tapa), **omitida** (opacidad 55 %, tapa gris).

### Horario para agendar
Píldora del ancho de la columna con el tinte suave del momento y borde teñido; en hover se vuelve blanca con borde del color del momento y sube 1 px. Los horarios ocupados usan rosa alerta y los no disponibles quedan transparentes en tinta suave.

### Toma en lista
Fila con punto de color del momento, hora tabular en 800, medicamento y dosis en tinta media, y un botón circular de check que se rellena de verde tomada al marcarla.

## Do's and Don'ts

### Do:
- **Do** colorear por momento del día solo con las tres tapas (ámbar, verde, azul noche) y sus fondos suaves.
- **Do** usar el azul de acción (#2f6fe4) para todo lo clicable principal y para el foco.
- **Do** usar números tabulares en horas, fechas, folios y contadores.
- **Do** mantener cada animación entre 160 y 520 ms con la curva `cubic-bezier(0.16, 1, 0.3, 1)` y anularla con `prefers-reduced-motion`.
- **Do** comprobar que cada pantalla quepa en 390 px sin desbordar la página.

### Don't:
- **Don't** usar franjas de color en un costado de tarjetas o botones; el color va como tinte, tapa o punto.
- **Don't** forzar mayúsculas en títulos, etiquetas o botones.
- **Don't** usar degradados en texto ni rótulos pequeños sobre los títulos ("eyebrows").
- **Don't** usar ámbar, verde o azul noche para categorías o estados que no sean la hora del día.
- **Don't** mostrar datos reales: la demo usa nombres, correos y teléfonos ficticios.
