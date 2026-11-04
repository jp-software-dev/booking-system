<?php
/**
 * ESTILOS COMUNES DE LOS REPORTES PDF
 *
 * Se incluyen dentro del <head> de cada plantilla de src/views/pdf.
 * Dompdf solo entiende CSS 2.1, por eso se usan estilos sencillos.
 */
?>
<style>
    body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #16233b; }
    .encabezado { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #2f6fe4; padding-bottom: 10px; }
    .encabezado h1 { color: #16233b; margin: 0; font-size: 24px; }
    .encabezado p { margin: 5px 0 0 0; color: #666666; }
    h2 { font-size: 15px; color: #1f57c3; margin-top: 25px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th { background-color: #f2f5fa; font-weight: bold; text-align: left; padding: 8px; border: 1px solid #e1e7f0; }
    td { padding: 8px; border: 1px solid #e1e7f0; }
    .tabla-datos td:first-child { font-weight: bold; width: 220px; background-color: #f7f9fc; }
    .estado-pendiente { color: #d97706; font-weight: bold; }
    .estado-confirmada { color: #0f766e; font-weight: bold; }
    .estado-en-curso { color: #1d4ed8; font-weight: bold; }
    .estado-completada { color: #15803d; font-weight: bold; }
    .estado-cancelada { color: #dc2626; font-weight: bold; }
    .vacio { text-align: center; color: #666666; }
</style>
