<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Cita;

/**
 * FILTRO DE CITAS PARA LOS LISTADOS DEL PANEL
 *
 * Traduce los filtros de la URL a una cláusula WHERE con parámetros. Lo usan el
 * panel de citas (admin.php), el historial (admin_historial.php) y el reporte
 * PDF (generar_pdf.php), así los tres aplican exactamente los mismos filtros.
 *
 * Parámetros de URL reconocidos: id_cita, paciente, doctor y estado.
 * La cláusula usa los alias c (citas), p (pacientes) y d (doctores).
 */
final class FiltroCitas
{
    /** Estados que muestra el historial de citas cerradas. */
    public const ESTADOS_HISTORIAL = ['Completada', 'Cancelada'];

    /**
     * @param string $idCita        Id de cita buscado (texto vacío = sin filtro).
     * @param string $paciente      Texto a buscar en el nombre del paciente.
     * @param string $doctor        Id del médico (texto vacío = todos).
     * @param string $estado        Estado de la cita (texto vacío = todos).
     * @param bool   $soloHistorial true para limitar a citas completadas o canceladas.
     */
    public function __construct(
        public readonly string $idCita,
        public readonly string $paciente,
        public readonly string $doctor,
        public readonly string $estado,
        public readonly bool $soloHistorial = false
    ) {
    }

    /**
     * Construye el filtro a partir de los parámetros de la URL.
     *
     * Descarta valores que no tienen sentido (ids no numéricos, estados inexistentes).
     *
     * @param array<string, mixed> $consulta      Parámetros de la URL (normalmente $_GET).
     * @param bool                 $soloHistorial true para el alcance del historial.
     */
    public static function desdeConsulta(array $consulta, bool $soloHistorial = false): self
    {
        $leer = static fn (string $clave): string => is_string($consulta[$clave] ?? null) ? trim($consulta[$clave]) : '';

        $estadosPermitidos = $soloHistorial ? self::ESTADOS_HISTORIAL : Cita::ESTADOS;
        $estado = $leer('estado');

        return new self(
            ctype_digit($leer('id_cita')) ? $leer('id_cita') : '',
            mb_substr($leer('paciente'), 0, 100),
            ctype_digit($leer('doctor')) ? $leer('doctor') : '',
            in_array($estado, $estadosPermitidos, true) ? $estado : '',
            $soloHistorial
        );
    }

    /**
     * Devuelve la cláusula WHERE (incluida la palabra WHERE, o texto vacío si no
     * hay filtros) y sus parámetros.
     *
     * @return array{0: string, 1: list<mixed>}
     */
    public function aSql(): array
    {
        $condiciones = [];
        $params = [];

        if ($this->soloHistorial) {
            $condiciones[] = "c.estado_cita IN ('Completada', 'Cancelada')";
        }

        if ($this->idCita !== '') {
            $condiciones[] = 'c.id_cita = ?';
            $params[] = (int) $this->idCita;
        }

        if ($this->paciente !== '') {
            // Se escapan los comodines de LIKE para buscar el texto tal como se escribió.
            $patron = '%' . addcslashes($this->paciente, '%_\\') . '%';
            $condiciones[] = "(p.nombre LIKE ? OR p.apellido_paterno LIKE ? OR CONCAT(p.nombre, ' ', p.apellido_paterno) LIKE ?)";
            array_push($params, $patron, $patron, $patron);
        }

        if ($this->doctor !== '') {
            $condiciones[] = 'c.id_doctor = ?';
            $params[] = (int) $this->doctor;
        }

        if ($this->estado !== '') {
            $condiciones[] = 'c.estado_cita = ?';
            $params[] = $this->estado;
        }

        $where = $condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones);

        return [$where, $params];
    }

    /**
     * Describe en texto los filtros activos (para el subtítulo del reporte PDF).
     *
     * @param string|null $nombreDoctor Nombre del médico filtrado, si se conoce.
     */
    public function descripcion(?string $nombreDoctor = null): string
    {
        $partes = [];

        if ($this->idCita !== '') {
            $partes[] = 'Cita #' . $this->idCita;
        }
        if ($this->paciente !== '') {
            $partes[] = 'Paciente = ' . $this->paciente;
        }
        if ($this->doctor !== '') {
            $partes[] = 'Médico = ' . ($nombreDoctor !== null ? 'Dr. ' . $nombreDoctor : '#' . $this->doctor);
        }
        if ($this->estado !== '') {
            $partes[] = 'Estado = ' . $this->estado;
        }

        if ($partes === []) {
            return $this->soloHistorial ? 'Historial de citas completadas y canceladas' : 'Reporte global de citas';
        }

        return 'Reporte filtrado: ' . implode(', ', $partes);
    }

    /**
     * Parámetros de URL que reproducen este filtro (para el enlace al PDF).
     *
     * @return array<string, string>
     */
    public function parametrosUrl(): array
    {
        $params = array_filter([
            'id_cita'  => $this->idCita,
            'paciente' => $this->paciente,
            'doctor'   => $this->doctor,
            'estado'   => $this->estado,
        ], static fn (string $valor): bool => $valor !== '');

        if ($this->soloHistorial) {
            $params['origen'] = 'historial';
        }

        return $params;
    }
}
