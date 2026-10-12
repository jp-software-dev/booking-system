<?php

declare(strict_types=1);

namespace App\Demo;

use App\Core\Database;
use App\Core\Env;
use App\Helpers\Horario;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * GENERADOR DE DATOS DE DEMOSTRACIÓN
 *
 * Vacía la base y la llena con datos ficticios: especialidades, consultorios,
 * médicos, enfermeros, pacientes y unas 800 citas repartidas entre los últimos
 * 4 meses y las próximas 3 semanas (fechas relativas al día en que se ejecuta).
 *
 * Crea también las cuentas de prueba de ModoDemo (administrador y paciente).
 * Todas las citas respetan las reglas del sistema: horario de Horario, días
 * hábiles y un solo turno activo por médico, fecha y hora.
 *
 * ¡Borra todos los datos! Por eso solo se ejecuta en modo demo (ver
 * database/sembrar_demo.php y api/demo_reset.php).
 */
final class SembradorDemo
{
    /** Días hacia atrás y hacia adelante que cubren las citas. */
    private const DIAS_PASADOS = 120;
    private const DIAS_FUTUROS = 21;

    /** Tablas en el orden en que se vacían (primero las que dependen de otras). */
    private const TABLAS = [
        'citas', 'cambios_password_pendientes', 'password_resets', 'pacientes_credenciales', 'pacientes',
        'enfermeros', 'doctores', 'consultorios', 'especialidades', 'usuarios', 'bitacora', 'login_attempts',
    ];

    private const ESPECIALIDADES = [
        'Medicina General', 'Pediatría', 'Cardiología', 'Dermatología', 'Ginecología', 'Traumatología y Ortopedia',
    ];

    /** Ubicación de cada consultorio (uno por especialidad, en el mismo orden). */
    private const UBICACIONES = [
        'Planta baja, ala norte', 'Planta baja, ala sur', 'Primer piso, ala norte',
        'Primer piso, ala sur', 'Segundo piso, ala norte', 'Segundo piso, ala sur',
    ];

    /**
     * Médicos: [nombre, apellido, índice de especialidad, ¿tiene consultorio?].
     * Los dos últimos quedan sin consultorio para que el dashboard muestre ese aviso.
     */
    private const DOCTORES = [
        ['Laura', 'Méndez', 0, true], ['Ricardo', 'Salinas', 1, true], ['Sofía', 'Navarro', 2, true],
        ['Andrés', 'Fuentes', 3, true], ['Valeria', 'Ortega', 4, true], ['Tomás', 'Ibarra', 5, true],
        ['Emilio', 'Cárdenas', 0, false], ['Paula', 'Rentería', 1, false],
    ];

    /** Enfermeros: [nombre, apellido, turno, índice de consultorio o null]. */
    private const ENFERMEROS = [
        ['Mariana', 'Lozano', 'Matutino', 0], ['Jorge', 'Peña', 'Vespertino', 1],
        ['Lucía', 'Herrera', 'Nocturno', null], ['Diego', 'Ramos', 'Matutino', 2],
    ];

    private const NOMBRES_F = ['Ana', 'María', 'Carmen', 'Fernanda', 'Gabriela', 'Daniela', 'Patricia', 'Elena', 'Isabel', 'Regina', 'Camila', 'Rosa'];
    private const NOMBRES_M = ['Luis', 'Carlos', 'Miguel', 'Javier', 'Alejandro', 'Fernando', 'Raúl', 'Héctor', 'Pablo', 'Sergio', 'Mateo', 'Óscar'];
    private const APELLIDOS = ['García', 'Hernández', 'López', 'Martínez', 'González', 'Pérez', 'Rodríguez', 'Sánchez', 'Ramírez', 'Torres', 'Flores', 'Rivera', 'Gómez', 'Díaz', 'Vargas', 'Castillo'];

    private const MOTIVOS = [
        'Revisión general', 'Dolor de cabeza persistente', 'Control de presión arterial', 'Chequeo anual',
        'Seguimiento de tratamiento', 'Dolor de espalda', 'Erupción en la piel', 'Control prenatal',
        'Fiebre y tos', 'Consulta de resultados de laboratorio', 'Dolor en la rodilla', 'Vacunación',
    ];

    /** Pacientes ficticios además del paciente de prueba. */
    private const TOTAL_PACIENTES = 24;

    /**
     * Horarios ya usados: "idMedico|fecha|hora" => true.
     *
     * @var array<string, bool>
     */
    private array $ocupados = [];

    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * Vacía la base y la llena con los datos de demostración.
     *
     * @return array<string, int> Cantidad de registros creados por tipo.
     */
    public function sembrar(): array
    {
        // Semilla fija: los nombres y la distribución se repiten en cada reinicio.
        mt_srand(2026);
        $this->ocupados = [];

        $this->db->beginTransaction();

        try {
            $this->vaciar();

            $especialidades = $this->crearEspecialidades();
            $consultorios = $this->crearConsultorios($especialidades);
            $medicos = $this->crearDoctores($especialidades, $consultorios);
            $this->crearEnfermeros($consultorios);
            $this->crearAdministrador();
            [$idPacienteDemo, $pacientes] = $this->crearPacientes();
            $totalCitas = $this->crearCitas($medicos, $idPacienteDemo, $pacientes);

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return [
            'especialidades' => count($especialidades),
            'consultorios'   => count($consultorios),
            'medicos'        => count(self::DOCTORES),
            'enfermeros'     => count(self::ENFERMEROS),
            'pacientes'      => count($pacientes) + 1,
            'citas'          => $totalCitas,
        ];
    }

    /**
     * Borra todos los registros. Las sesiones solo se borran si se guardan en la base.
     */
    private function vaciar(): void
    {
        $tablas = self::TABLAS;
        if (Env::get('SESSION_DRIVER', 'files') === 'database') {
            $tablas[] = 'sesiones';
        }

        foreach ($tablas as $tabla) {
            $this->db->exec("DELETE FROM {$tabla}");
        }
    }

    /**
     * @return list<int> Ids de las especialidades, en el orden de ESPECIALIDADES.
     */
    private function crearEspecialidades(): array
    {
        return array_map(
            fn (string $nombre): int => Database::insertar($this->db, 'INSERT INTO especialidades (nombre_especialidad) VALUES (?)', [$nombre], 'id_especialidad'),
            self::ESPECIALIDADES
        );
    }

    /**
     * Un consultorio por especialidad.
     *
     * @param list<int> $especialidades Ids de especialidades.
     * @return list<int> Ids de los consultorios.
     */
    private function crearConsultorios(array $especialidades): array
    {
        $ids = [];
        foreach ($especialidades as $i => $idEspecialidad) {
            $ids[] = Database::insertar(
                $this->db,
                'INSERT INTO consultorios (nombre, id_especialidad, ubicacion) VALUES (?, ?, ?)',
                ['Consultorio ' . (101 + $i), $idEspecialidad, self::UBICACIONES[$i]],
                'id_consultorio'
            );
        }

        return $ids;
    }

    /**
     * @param list<int> $especialidades Ids de especialidades.
     * @param list<int> $consultorios   Ids de consultorios.
     * @return list<array{id: int, consultorio: int}> Médicos con consultorio (los que reciben citas).
     */
    private function crearDoctores(array $especialidades, array $consultorios): array
    {
        $conConsultorio = [];

        foreach (self::DOCTORES as [$nombre, $apellido, $indiceEspecialidad, $tieneConsultorio]) {
            // El médico ocupa el consultorio de su especialidad, si le corresponde uno.
            $idConsultorio = $tieneConsultorio ? $consultorios[$indiceEspecialidad] : null;

            $id = Database::insertar(
                $this->db,
                "INSERT INTO doctores (nombre, apellido_paterno, id_especialidad, id_consultorio, estado) VALUES (?, ?, ?, ?, 'Activo')",
                [$nombre, $apellido, $especialidades[$indiceEspecialidad], $idConsultorio],
                'id_doctor'
            );

            if ($idConsultorio !== null) {
                $conConsultorio[] = ['id' => $id, 'consultorio' => $idConsultorio];
            }
        }

        return $conConsultorio;
    }

    /**
     * @param list<int> $consultorios Ids de consultorios.
     */
    private function crearEnfermeros(array $consultorios): void
    {
        $filas = array_map(
            static fn (array $e): array => [$e[0], $e[1], $e[2], $e[3] === null ? null : $consultorios[$e[3]]],
            self::ENFERMEROS
        );

        $this->insertarVarios('enfermeros', ['nombre', 'apellido_paterno', 'turno', 'id_consultorio'], $filas);
    }

    /**
     * Cuenta de administrador de prueba.
     */
    private function crearAdministrador(): void
    {
        $this->db
            ->prepare("INSERT INTO usuarios (usuario, nombre, password_hash, rol) VALUES (?, ?, ?, 'admin')")
            ->execute([ModoDemo::ADMIN_USUARIO, 'Administrador Demo', password_hash(ModoDemo::PASSWORD, PASSWORD_DEFAULT)]);
    }

    /**
     * Crea el paciente de prueba y los pacientes ficticios.
     *
     * @return array{0: int, 1: list<int>} [id del paciente de prueba, ids de los demás pacientes]
     */
    private function crearPacientes(): array
    {
        $idDemo = $this->insertarPaciente('Paciente', 'Demo', 'Prueba', '1990-05-15', 'Otro', ModoDemo::PACIENTE_EMAIL, $this->haceDias(90));
        $this->guardarCredencial($idDemo, password_hash(ModoDemo::PASSWORD, PASSWORD_DEFAULT));

        // Los pacientes ficticios no inician sesión: comparten el hash de una contraseña aleatoria.
        $hashSinAcceso = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $ids = [];

        for ($i = 1; $i <= self::TOTAL_PACIENTES; $i++) {
            $esMujer = $i % 2 === 0;
            $nombres = $esMujer ? self::NOMBRES_F : self::NOMBRES_M;
            $nombre = $nombres[mt_rand(0, count($nombres) - 1)];
            $paterno = self::APELLIDOS[mt_rand(0, count(self::APELLIDOS) - 1)];
            $materno = self::APELLIDOS[mt_rand(0, count(self::APELLIDOS) - 1)];
            $email = sprintf('%s.%s%d@example.com', self::sinAcentos($nombre), self::sinAcentos($paterno), $i);
            $nacimiento = sprintf('%04d-%02d-%02d', mt_rand(1950, 2005), mt_rand(1, 12), mt_rand(1, 28));

            $id = $this->insertarPaciente($nombre, $paterno, $materno, $nacimiento, $esMujer ? 'Femenino' : 'Masculino', $email, $this->haceDias(mt_rand(1, 365)));
            $this->guardarCredencial($id, $hashSinAcceso);
            $ids[] = $id;
        }

        return [$idDemo, $ids];
    }

    /**
     * Crea las citas: primero las del paciente de prueba (para que su historial
     * tenga ejemplos de cada estado) y después las del resto.
     *
     * @param list<array{id: int, consultorio: int}> $medicos        Médicos con consultorio.
     * @param int                                    $idPacienteDemo Paciente de prueba.
     * @param list<int>                              $pacientes      Demás pacientes.
     * @return int Total de citas creadas.
     */
    private function crearCitas(array $medicos, int $idPacienteDemo, array $pacientes): int
    {
        $filas = [];
        $ahora = new DateTimeImmutable();

        // --- Citas del paciente de prueba: dos próximas y tres pasadas ---
        $citasDemo = [
            [2, 0, '10:00', 'Pendiente'], [5, 2, '16:30', 'Confirmada'],
            [-10, 1, '09:00', 'Completada'], [-30, 3, '11:30', 'Completada'], [-60, 4, '13:00', 'Cancelada'],
        ];
        foreach ($citasDemo as [$desplazamiento, $indiceMedico, $hora, $estado]) {
            $fecha = $this->diaHabil($desplazamiento);
            $medico = $medicos[$indiceMedico];
            $this->ocupados["{$medico['id']}|{$fecha}|{$hora}"] = true;
            $filas[] = [$idPacienteDemo, $medico['id'], $medico['consultorio'], $fecha, $hora, self::MOTIVOS[mt_rand(0, count(self::MOTIVOS) - 1)], $estado];
        }

        // --- Citas del resto de los pacientes ---
        $horarios = Horario::slots();

        for ($d = -self::DIAS_PASADOS; $d <= self::DIAS_FUTUROS; $d++) {
            $dia = $ahora->modify("{$d} days");
            $fecha = $dia->format('Y-m-d');
            if (!Horario::esDiaHabil($fecha)) {
                continue;
            }

            foreach ($medicos as $medico) {
                $cantidad = $d < 0 ? mt_rand(0, 2) : mt_rand(0, 3);

                for ($n = 0; $n < $cantidad; $n++) {
                    $hora = $horarios[mt_rand(0, count($horarios) - 1)];
                    $clave = "{$medico['id']}|{$fecha}|{$hora}";
                    if (isset($this->ocupados[$clave])) {
                        continue;
                    }
                    $this->ocupados[$clave] = true;

                    $yaPaso = new DateTimeImmutable("{$fecha} {$hora}") <= $ahora;
                    $filas[] = [
                        $pacientes[mt_rand(0, count($pacientes) - 1)],
                        $medico['id'],
                        $medico['consultorio'],
                        $fecha,
                        $hora,
                        self::MOTIVOS[mt_rand(0, count(self::MOTIVOS) - 1)],
                        $this->estadoAleatorio($yaPaso),
                    ];
                }
            }
        }

        $this->insertarVarios(
            'citas',
            ['id_paciente', 'id_doctor', 'id_consultorio', 'fecha_cita', 'hora_inicio', 'motivo_consulta', 'estado_cita'],
            $filas
        );

        return count($filas);
    }

    /**
     * Estado aleatorio con una distribución realista según si la cita ya pasó.
     *
     * @param bool $yaPaso true si la fecha y hora de la cita ya pasaron.
     */
    private function estadoAleatorio(bool $yaPaso): string
    {
        $azar = mt_rand(1, 100);

        if ($yaPaso) {
            return $azar <= 75 ? 'Completada' : ($azar <= 95 ? 'Cancelada' : 'Pendiente');
        }

        return $azar <= 55 ? 'Pendiente' : ($azar <= 95 ? 'Confirmada' : 'Cancelada');
    }

    /**
     * Fecha (Y-m-d) del día hábil más cercano al desplazamiento indicado,
     * avanzando en la misma dirección si ese día no es hábil.
     *
     * @param int $desplazamiento Días desde hoy (negativo = pasado).
     */
    private function diaHabil(int $desplazamiento): string
    {
        $paso = $desplazamiento >= 0 ? 1 : -1;
        $dia = (new DateTimeImmutable())->modify("{$desplazamiento} days");

        while (!Horario::esDiaHabil($dia->format('Y-m-d'))) {
            $dia = $dia->modify("{$paso} day");
        }

        return $dia->format('Y-m-d');
    }

    /**
     * Fecha y hora (Y-m-d H:i:s) de hace la cantidad de días indicada.
     *
     * @param int $dias Días hacia atrás.
     */
    private function haceDias(int $dias): string
    {
        return (new DateTimeImmutable())->modify("-{$dias} days")->format('Y-m-d H:i:s');
    }

    /**
     * Inserta un paciente y devuelve su id.
     */
    private function insertarPaciente(
        string $nombre,
        string $paterno,
        string $materno,
        string $nacimiento,
        string $genero,
        string $email,
        string $creado
    ): int {
        $telefono = '55' . sprintf('%08d', mt_rand(0, 99999999));

        return Database::insertar(
            $this->db,
            'INSERT INTO pacientes (nombre, apellido_paterno, apellido_materno, fecha_nacimiento, genero, telefono, email, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$nombre, $paterno, $materno, $nacimiento, $genero, $telefono, $email, $creado],
            'id_paciente'
        );
    }

    /**
     * Guarda las credenciales de un paciente.
     *
     * @param int    $idPaciente Paciente.
     * @param string $hash       Hash de la contraseña.
     */
    private function guardarCredencial(int $idPaciente, string $hash): void
    {
        $this->db
            ->prepare('INSERT INTO pacientes_credenciales (id_paciente, password_hash, cuenta_verificada) VALUES (?, ?, 1)')
            ->execute([$idPaciente, $hash]);
    }

    /**
     * Inserta muchas filas en lotes de 100 con un solo INSERT por lote
     * (VALUES (...), (...)), sintaxis válida en MySQL y PostgreSQL.
     *
     * @param string            $tabla    Tabla destino (valor fijo del código).
     * @param list<string>      $columnas Columnas en el orden de cada fila.
     * @param list<list<mixed>> $filas    Valores.
     */
    private function insertarVarios(string $tabla, array $columnas, array $filas): void
    {
        $marcadorFila = '(' . implode(', ', array_fill(0, count($columnas), '?')) . ')';

        foreach (array_chunk($filas, 100) as $lote) {
            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES %s',
                $tabla,
                implode(', ', $columnas),
                implode(', ', array_fill(0, count($lote), $marcadorFila))
            );
            $this->db->prepare($sql)->execute(array_merge(...$lote));
        }
    }

    /**
     * Quita acentos y pasa a minúsculas (para armar correos ficticios).
     *
     * @param string $texto Texto con posibles acentos.
     */
    private static function sinAcentos(string $texto): string
    {
        return strtolower(strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'ñ' => 'n', 'Ñ' => 'n']));
    }
}
