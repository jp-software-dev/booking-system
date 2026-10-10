<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Consultorio;
use App\Models\Especialidad;
use App\Models\Personal;

/**
 * VALIDADOR DE DATOS DEL PERSONAL MÉDICO
 *
 * Reglas comunes al alta (add_personal.php) y a la edición (update_personal.php)
 * de médicos y enfermeros.
 */
final class PersonalValidator
{
    /** Longitud máxima de nombre y apellido (VARCHAR(50) en la base). */
    private const MAX_NOMBRE = 50;

    /**
     * Valida y normaliza los datos recibidos.
     *
     * @param array<string, mixed> $entrada           Datos enviados por el formulario.
     * @param int|null             $idActual          Id del médico que se edita (null en un alta).
     * @param int|null             $consultorioActual Consultorio que ya tenía el médico editado.
     *                                                Si no cambia, no se revisa la ocupación, para
     *                                                no bloquear la edición de asignaciones previas.
     * @return array{0: string|null, 1: array{tipo: string, nombre: string, apellido: string,
     *               id_especialidad: int|null, turno: string|null, id_consultorio: int|null}}
     *         [mensaje de error o null, datos normalizados]
     */
    public static function validar(array $entrada, ?int $idActual = null, ?int $consultorioActual = null): array
    {
        $datos = [
            'tipo'            => strtolower(trim((string) ($entrada['tipo'] ?? ''))),
            'nombre'          => trim((string) ($entrada['nombre'] ?? '')),
            'apellido'        => trim((string) ($entrada['apellido_paterno'] ?? '')),
            'id_especialidad' => !empty($entrada['id_especialidad']) ? (int) $entrada['id_especialidad'] : null,
            'turno'           => null,
            'id_consultorio'  => !empty($entrada['id_consultorio']) ? (int) $entrada['id_consultorio'] : null,
        ];

        if (!in_array($datos['tipo'], [Personal::TIPO_DOCTOR, Personal::TIPO_ENFERMERO], true)) {
            return ['Tipo de personal inválido', $datos];
        }

        if ($datos['nombre'] === '' || $datos['apellido'] === '') {
            return ['Nombre y apellido paterno son obligatorios', $datos];
        }

        if (ValidationHelper::excedeLongitud($datos['nombre'], self::MAX_NOMBRE)
            || ValidationHelper::excedeLongitud($datos['apellido'], self::MAX_NOMBRE)) {
            return ['Nombre y apellido pueden tener como máximo ' . self::MAX_NOMBRE . ' caracteres', $datos];
        }

        $consultorios = new Consultorio();

        // --- Médico: especialidad obligatoria y reglas de asignación de consultorio ---
        if ($datos['tipo'] === Personal::TIPO_DOCTOR) {
            if ($datos['id_especialidad'] === null || !(new Especialidad())->existe($datos['id_especialidad'])) {
                return ['Selecciona una especialidad válida', $datos];
            }

            if ($datos['id_consultorio'] !== null) {
                $mismoConsultorio = $consultorioActual !== null && $datos['id_consultorio'] === $consultorioActual;
                $error = $consultorios->validarAsignacion($datos['id_consultorio'], $datos['id_especialidad'], $idActual, $mismoConsultorio);
                if ($error !== null) {
                    return [$error, $datos];
                }
            }

            return [null, $datos];
        }

        // --- Enfermero: turno válido y consultorio existente (varios enfermeros pueden compartirlo) ---
        $datos['id_especialidad'] = null;
        $datos['turno'] = (string) ($entrada['turno'] ?? 'Matutino');

        if (!in_array($datos['turno'], Personal::TURNOS, true)) {
            return ['Turno inválido', $datos];
        }

        if ($datos['id_consultorio'] !== null && $consultorios->obtenerPorId($datos['id_consultorio']) === null) {
            return ['El consultorio seleccionado no existe', $datos];
        }

        return [null, $datos];
    }
}
