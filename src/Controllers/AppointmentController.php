<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Helpers\AppointmentValidator;
use App\Helpers\Bitacora;
use App\Helpers\Horario;
use App\Http\Sesion;
use App\Models\Cita;
use App\Models\Doctor;
use App\Models\Paciente;
use App\Services\EmailService;
use PDO;
use PDOException;
use Throwable;

/**
 * CONTROLADOR DE CITAS
 *
 * Contiene la lógica de negocio de las citas: crear, reprogramar, cancelar,
 * cambiar de estado y eliminar. Los endpoints de /api solo validan la petición
 * HTTP y delegan aquí.
 *
 * Cada método devuelve un arreglo con:
 *   - status:  'success' o 'error'
 *   - message: texto para el usuario
 *   - codigo:  código HTTP sugerido (Api::responderResultado() lo aplica)
 */
final class AppointmentController
{
    /** Código de error de MySQL para una llave única duplicada (horario ya ocupado). */
    private const MYSQL_LLAVE_DUPLICADA = 1062;

    private PDO $db;
    private Cita $citas;
    private Doctor $doctores;
    private Paciente $pacientes;

    /**
     * @param PDO|null $db Conexión a usar. Si es null se usa la conexión compartida.
     */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->citas = new Cita($this->db);
        $this->doctores = new Doctor($this->db);
        $this->pacientes = new Paciente($this->db);
    }

    /**
     * Crea una cita.
     *
     * - Paciente: la cita siempre es para él mismo (se ignora cualquier
     *   id_paciente enviado) y se aplica el límite MAX_CITAS_ACTIVAS.
     * - Administrador: agenda a nombre del paciente cuyo correo viene en 'email'.
     *
     * El consultorio se toma del médico en el servidor; nunca del cliente.
     *
     * @param array<string, mixed> $datos     id_doctor, fecha_cita, hora_inicio, motivo y, para el admin, email.
     * @param int                  $idUsuario Usuario autenticado.
     * @param string               $rol       Rol del usuario autenticado.
     * @return array<string, mixed>
     */
    public function create(array $datos, int $idUsuario, string $rol): array
    {
        // --- Resolver el paciente de la cita ---
        if ($rol === Sesion::ROL_ADMIN) {
            $email = trim((string) ($datos['email'] ?? ''));
            if ($email === '') {
                return self::error('El correo del paciente es obligatorio');
            }

            $idPaciente = $this->pacientes->buscarIdActivoPorEmail($email);
            if ($idPaciente === null) {
                return self::error('No se encontró ningún paciente activo con ese correo electrónico', 404);
            }
        } elseif ($rol === Sesion::ROL_PACIENTE) {
            $idPaciente = $idUsuario;
        } else {
            return self::error('No autorizado', 403);
        }

        // --- Validar los datos de la cita ---
        $error = AppointmentValidator::validarCreacion($datos);
        if ($error !== null) {
            return self::error($error);
        }

        $idDoctor = (int) $datos['id_doctor'];
        $fecha = (string) $datos['fecha_cita'];
        $hora = substr((string) $datos['hora_inicio'], 0, 5);
        $motivo = AppointmentValidator::normalizarMotivo($datos['motivo'] ?? null);

        $idConsultorio = $this->doctores->obtenerConsultorioAsignado($idDoctor);
        if ($idConsultorio === null) {
            return self::error('El médico seleccionado no está disponible o no tiene consultorio asignado');
        }

        if ($rol === Sesion::ROL_PACIENTE && $this->citas->contarActivasPorPaciente($idPaciente) >= \MAX_CITAS_ACTIVAS) {
            return self::error(sprintf(
                'Ya tienes %d citas activas, el máximo permitido. Cancela o espera a que concluya alguna para agendar otra.',
                \MAX_CITAS_ACTIVAS
            ), 409);
        }

        // --- Guardar ---
        try {
            $this->db->beginTransaction();

            if (!$this->citas->verificarDisponibilidad($idDoctor, $fecha, $hora)) {
                $this->db->rollBack();
                return self::error('El horario ya no está disponible. Elige otro.', 409);
            }

            $idCita = $this->citas->agendar($idPaciente, $idDoctor, $idConsultorio, $fecha, $hora, $motivo);
            $this->db->commit();
        } catch (PDOException $e) {
            $this->deshacerTransaccion();
            if (self::esHorarioDuplicado($e)) {
                return self::error('El horario acaba de ser ocupado por otra persona. Elige otro.', 409);
            }
            throw $e;
        }

        Bitacora::registrar('agendar_cita', 'cita', $idCita, "Dr. #{$idDoctor}, {$fecha} {$hora}");
        $this->notificarNuevaCita($idCita);

        return self::exito('Cita agendada. Te avisaremos cuando sea confirmada.', ['id_cita' => $idCita]);
    }

    /**
     * Cambia la fecha y hora de una cita pendiente o confirmada.
     *
     * - Paciente: solo sus propias citas; puede cambiar también el motivo y la
     *   cita vuelve a Pendiente para que el administrador la confirme otra vez.
     * - Administrador: cualquier cita; el estado se conserva.
     *
     * @param int                  $idCita    Cita a reprogramar.
     * @param array<string, mixed> $datos     fecha_cita, hora_inicio y, opcionalmente, motivo.
     * @param int                  $idUsuario Usuario autenticado.
     * @param string               $rol       Rol del usuario autenticado.
     * @return array<string, mixed>
     */
    public function reschedule(int $idCita, array $datos, int $idUsuario, string $rol): array
    {
        if ($idCita <= 0) {
            return self::error('Cita no especificada');
        }

        $fecha = (string) ($datos['fecha_cita'] ?? '');
        $hora = substr((string) ($datos['hora_inicio'] ?? ''), 0, 5);

        $error = AppointmentValidator::validarHorario($fecha, $hora);
        if ($error !== null) {
            return self::error($error);
        }

        $esPaciente = $rol === Sesion::ROL_PACIENTE;

        // El motivo solo lo cambia el paciente, y solo si lo envía.
        $motivo = null;
        if ($esPaciente && array_key_exists('motivo', $datos)) {
            $error = AppointmentValidator::validarMotivo((string) $datos['motivo']);
            if ($error !== null) {
                return self::error($error);
            }
            $motivo = AppointmentValidator::normalizarMotivo($datos['motivo']);
        }

        try {
            $this->db->beginTransaction();

            $cita = $this->citas->obtenerParaActualizar($idCita, $esPaciente ? $idUsuario : null);
            if ($cita === null) {
                $this->db->rollBack();
                return self::error('Cita no encontrada', 404);
            }

            if (!in_array($cita['estado_cita'], Cita::ESTADOS_MODIFICABLES, true)) {
                $this->db->rollBack();
                return self::error('Solo se pueden reprogramar citas pendientes o confirmadas', 409);
            }

            if (!$this->citas->verificarDisponibilidad((int) $cita['id_doctor'], $fecha, $hora, $idCita)) {
                $this->db->rollBack();
                return self::error('El horario seleccionado ya no está disponible', 409);
            }

            $this->citas->reprogramar($idCita, $fecha, $hora, $motivo, $esPaciente);
            $this->db->commit();
        } catch (PDOException $e) {
            $this->deshacerTransaccion();
            if (self::esHorarioDuplicado($e)) {
                return self::error('El horario acaba de ser ocupado por otra cita. Elige otro.', 409);
            }
            throw $e;
        }

        Bitacora::registrar('reprogramar_cita', 'cita', $idCita, "Nueva fecha: {$fecha} {$hora}");

        $mensaje = $esPaciente
            ? 'Cita reprogramada. Quedó pendiente de confirmación.'
            : 'Cita actualizada correctamente';

        return self::exito($mensaje);
    }

    /**
     * El paciente cancela una de sus citas pendientes o confirmadas que aún no ocurre.
     *
     * @param int $idCita     Cita a cancelar.
     * @param int $idPaciente Paciente autenticado (dueño de la cita).
     * @return array<string, mixed>
     */
    public function cancel(int $idCita, int $idPaciente): array
    {
        if ($idCita <= 0) {
            return self::error('Cita no especificada');
        }

        try {
            $this->db->beginTransaction();

            $cita = $this->citas->obtenerParaActualizar($idCita, $idPaciente);
            if ($cita === null) {
                $this->db->rollBack();
                return self::error('Cita no encontrada', 404);
            }

            if (!in_array($cita['estado_cita'], Cita::ESTADOS_MODIFICABLES, true)) {
                $this->db->rollBack();
                return self::error('Solo se pueden cancelar citas pendientes o confirmadas', 409);
            }

            if (Horario::yaPaso((string) $cita['fecha_cita'], substr((string) $cita['hora_inicio'], 0, 5))) {
                $this->db->rollBack();
                return self::error('No se puede cancelar una cita que ya pasó', 409);
            }

            $this->citas->cambiarEstado($idCita, 'Cancelada');
            $this->db->commit();
        } catch (Throwable $e) {
            $this->deshacerTransaccion();
            throw $e;
        }

        Bitacora::registrar('cancelar_cita_paciente', 'cita', $idCita);

        return self::exito('Cita cancelada');
    }

    /**
     * El administrador cambia el estado de una cita. Si la confirma o la
     * cancela, se notifica al paciente por correo.
     *
     * @param int    $idCita Cita.
     * @param string $estado Nuevo estado (uno de Cita::ESTADOS).
     * @return array<string, mixed>
     */
    public function changeStatus(int $idCita, string $estado): array
    {
        if ($idCita <= 0) {
            return self::error('Cita no especificada');
        }

        if (!in_array($estado, Cita::ESTADOS, true)) {
            return self::error('Estado no válido');
        }

        try {
            $this->db->beginTransaction();

            $cita = $this->citas->obtenerParaActualizar($idCita);
            if ($cita === null) {
                $this->db->rollBack();
                return self::error('Cita no encontrada', 404);
            }

            $estadoAnterior = (string) $cita['estado_cita'];
            if ($estadoAnterior === $estado) {
                $this->db->rollBack();
                return self::exito('La cita ya tenía ese estado');
            }

            $this->citas->cambiarEstado($idCita, $estado);
            $this->db->commit();
        } catch (PDOException $e) {
            $this->deshacerTransaccion();
            // Reactivar una cita cancelada choca si otra cita activa ya ocupa ese horario.
            if (self::esHorarioDuplicado($e)) {
                return self::error('No se puede reactivar: otra cita activa ya ocupa ese horario', 409);
            }
            throw $e;
        }

        Bitacora::registrar('cambiar_estado_cita', 'cita', $idCita, "{$estadoAnterior} -> {$estado}");

        // El correo se envía después del commit para no mantener la fila bloqueada
        // mientras se espera al servidor SMTP.
        if ($estado === 'Confirmada' || $estado === 'Cancelada') {
            $detalle = $this->citas->obtenerDetalleNotificacion($idCita);
            if ($detalle !== null) {
                EmailService::notificarCambioEstado($detalle, $estado);
            }
        }

        return self::exito('Estado actualizado correctamente');
    }

    /**
     * Borra físicamente una cita (solo administradores).
     *
     * @param int $idCita Cita a eliminar.
     * @return array<string, mixed>
     */
    public function delete(int $idCita): array
    {
        if ($idCita <= 0) {
            return self::error('Cita no especificada');
        }

        if (!$this->citas->eliminar($idCita)) {
            return self::error('Cita no encontrada', 404);
        }

        Bitacora::registrar('eliminar_cita', 'cita', $idCita);

        return self::exito('Cita eliminada correctamente');
    }

    /**
     * Envía los correos de una cita recién creada: confirmación al paciente y
     * aviso al administrador. Los fallos solo se registran en el log.
     *
     * @param int $idCita Cita creada.
     */
    private function notificarNuevaCita(int $idCita): void
    {
        $detalle = $this->citas->obtenerDetalleNotificacion($idCita);
        if ($detalle === null) {
            return;
        }

        EmailService::enviarConfirmacionCita($detalle);
        EmailService::notificarAdminNuevaCita($detalle);
    }

    /**
     * Revierte la transacción en curso, si la hay.
     */
    private function deshacerTransaccion(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    /**
     * Indica si la excepción se debe al índice único de horarios (slot_activo).
     *
     * @param PDOException $e Excepción de la base de datos.
     */
    private static function esHorarioDuplicado(PDOException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === self::MYSQL_LLAVE_DUPLICADA;
    }

    /**
     * Arma un resultado exitoso.
     *
     * @param string               $mensaje Mensaje para el usuario.
     * @param array<string, mixed> $extra   Campos adicionales.
     * @return array<string, mixed>
     */
    private static function exito(string $mensaje, array $extra = []): array
    {
        return ['status' => 'success', 'message' => $mensaje, 'codigo' => 200] + $extra;
    }

    /**
     * Arma un resultado de error.
     *
     * @param string $mensaje Mensaje para el usuario.
     * @param int    $codigo  Código HTTP sugerido.
     * @return array<string, mixed>
     */
    private static function error(string $mensaje, int $codigo = 400): array
    {
        return ['status' => 'error', 'message' => $mensaje, 'codigo' => $codigo];
    }
}
