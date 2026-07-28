<?php
/**
 * MODELO DE CITA
 *
 * Define la estructura y los métodos principales para gestionar el ciclo de vida
 * de las citas médicas en la base de datos, incluyendo creación, verificación de
 * disponibilidad, cancelación (lógica) y eliminación (física).
 *
 * @requires PDO
 * @declare(strict_types=1)
 */

declare(strict_types=1);

class Cita {
    
    private PDO $conn;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    // VERIFICADOR DE EMPALMES: Consulta si un doctor ya tiene un espacio ocupado en esa fecha y hora.
    // Usa COUNT(*) + fetchColumn() en vez de rowCount(), ya que PDO no garantiza que rowCount()
    // devuelva el número de filas de un SELECT en todos los drivers.
    public function verificarDisponibilidad(int $id_doctor, string $fecha, string $hora): bool {

        $query = "SELECT COUNT(*) FROM citas WHERE id_doctor = ? AND fecha_cita = ? AND hora_inicio = ? AND estado_cita != 'Cancelada'";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id_doctor, $fecha, $hora]);

        return ((int)$stmt->fetchColumn()) === 0;
    }

    // CREADOR DE CITAS: Recibe un arreglo de información validada e inserta un nuevo registro.
    public function agendar(array $data): bool {
        
        $query = "INSERT INTO citas (id_paciente, id_doctor, id_consultorio, fecha_cita, hora_inicio, motivo_consulta, estado_cita) 
                  VALUES (?, ?, ?, ?, ?, ?, 'Pendiente')";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            $data['id_paciente'], 
            $data['id_doctor'], 
            $data['id_consultorio'] ?? null,
            $data['fecha_cita'], 
            $data['hora_inicio'], 
            $data['motivo_consulta'] ?? 'Consulta general'
        ]);
    }

    // BORRADO FÍSICO: Ejecuta una eliminación definitiva (Hard Delete) del registro.
    public function eliminar(int $id_cita): bool {
        $query = "DELETE FROM citas WHERE id_cita = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id_cita]);
    }

    // HISTORIAL DE PACIENTE: Extrae todas las citas asociadas a un ID de paciente.
    public function obtenerPorPaciente(int $id_paciente): array {
        
        $query = "SELECT c.id_cita, c.fecha_cita, c.hora_inicio, c.motivo_consulta, c.estado_cita,
                         d.nombre AS doctor_nombre, d.apellido_paterno AS doctor_ap,
                         co.nombre AS consultorio_nombre
                  FROM citas c
                  INNER JOIN doctores d ON c.id_doctor = d.id_doctor
                  LEFT JOIN consultorios co ON c.id_consultorio = co.id_consultorio
                  WHERE c.id_paciente = ?
                  ORDER BY c.fecha_cita DESC, c.hora_inicio DESC";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id_paciente]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // BORRADO LÓGICO: Modifica el estado de una cita a 'Cancelada' (Soft Delete).
    public function cancelar(int $id_cita, int $id_paciente): bool {
        
        $query = "UPDATE citas SET estado_cita = 'Cancelada' WHERE id_cita = ? AND id_paciente = ?";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id_cita, $id_paciente]);
    }
}
?>