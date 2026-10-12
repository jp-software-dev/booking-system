<?php

declare(strict_types=1);

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use InvalidArgumentException;

/**
 * GENERACIÓN DE DOCUMENTOS PDF (DOMPDF)
 *
 * Renderiza una plantilla de src/views/pdf con los datos recibidos y la envía
 * al navegador como descarga.
 */
final class PdfService
{
    /** Carpeta de las plantillas HTML de los PDF. */
    private const CARPETA_VISTAS = __DIR__ . '/../views/pdf/';

    /**
     * Renderiza una plantilla PHP y devuelve el HTML resultante.
     *
     * @param string               $vista Nombre del archivo sin extensión (ej. "historial_citas").
     * @param array<string, mixed> $datos Variables disponibles dentro de la plantilla.
     * @throws InvalidArgumentException Si la plantilla no existe.
     */
    public static function renderizarVista(string $vista, array $datos): string
    {
        $archivo = self::CARPETA_VISTAS . basename($vista) . '.php';
        if (!is_file($archivo)) {
            throw new InvalidArgumentException("No existe la plantilla PDF: {$vista}");
        }

        extract($datos, EXTR_SKIP);
        ob_start();
        require $archivo;

        return (string) ob_get_clean();
    }

    /**
     * Convierte el HTML en PDF y lo envía como descarga.
     *
     * Las imágenes y hojas de estilo remotas quedan deshabilitadas: todo el
     * contenido sale de la base de datos y de estilos en línea.
     *
     * @param string $html          Documento HTML completo.
     * @param string $nombreArchivo Nombre del archivo descargado.
     * @param string $orientacion   'portrait' (vertical) o 'landscape' (horizontal).
     */
    public static function descargar(string $html, string $nombreArchivo, string $orientacion = 'portrait'): void
    {
        $opciones = new Options();
        $opciones->set('isHtml5ParserEnabled', true);
        $opciones->set('isRemoteEnabled', false);
        // Archivos temporales y caché de fuentes en la carpeta temporal del sistema:
        // en Vercel el resto del disco (incluido vendor/) es de solo lectura.
        $opciones->set('tempDir', sys_get_temp_dir());
        $opciones->set('fontCache', sys_get_temp_dir());

        $dompdf = new Dompdf($opciones);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', $orientacion);
        $dompdf->render();
        $dompdf->stream($nombreArchivo, ['Attachment' => true]);
    }
}
