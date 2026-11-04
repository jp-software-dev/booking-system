<?php

declare(strict_types=1);

namespace App\Demo;

use App\Core\Env;
use App\Models\Paciente;

/**
 * MODO DEMOSTRACIÓN
 *
 * Con DEMO_MODE=true en el .env la aplicación funciona como demo pública:
 *   - muestra un aviso en todas las páginas y las credenciales de prueba en el login;
 *   - protege las cuentas de prueba (paciente y médico): no se puede cambiar su
 *     contraseña, sus datos ni eliminarlas, para que un visitante no las deje inutilizables;
 *   - permite reiniciar los datos con api/demo_reset.php o database/sembrar_demo.php.
 *
 * Las credenciales de prueba son públicas a propósito: solo existen en la base
 * de demostración, que se llena con datos ficticios.
 */
final class ModoDemo
{
    /** Usuario administrador de prueba. */
    public const ADMIN_USUARIO = 'demo.admin';

    /** Usuario del médico de prueba. */
    public const DOCTOR_USUARIO = 'demo.doctor';

    /** Correo del paciente de prueba (dominio reservado para ejemplos). */
    public const PACIENTE_EMAIL = 'paciente@example.com';

    /** Contraseña de las cuentas de prueba. */
    public const PASSWORD = 'Demo2026!';

    /** Mensaje para las acciones bloqueadas en la cuenta de prueba. */
    public const MENSAJE_BLOQUEO = 'Esta acción está deshabilitada en la cuenta de demostración.';

    /**
     * Indica si la aplicación corre en modo demostración.
     */
    public static function activo(): bool
    {
        return Env::get('DEMO_MODE', 'false') === 'true';
    }

    /**
     * Indica si el paciente es la cuenta de prueba protegida (solo en modo demo).
     *
     * @param int $idPaciente Paciente a revisar.
     */
    public static function esPacienteDemo(int $idPaciente): bool
    {
        if (!self::activo()) {
            return false;
        }

        $paciente = (new Paciente())->obtenerPorId($idPaciente);

        return $paciente !== null && strcasecmp((string) $paciente['email'], self::PACIENTE_EMAIL) === 0;
    }
}
