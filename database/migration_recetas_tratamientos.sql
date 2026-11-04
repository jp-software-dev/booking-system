-- =============================================================================
-- MIGRACIÓN 16 (MYSQL / MARIADB): DOCTORES CON ACCESO, RECETAS Y RECORDATORIOS
--
-- 1. usuarios: nuevo rol 'doctor' y columna id_doctor (cuenta ligada a un médico).
-- 2. recetas: receta emitida por un médico a un paciente (opcionalmente en una cita).
-- 3. tratamientos: medicamento con su frecuencia y duración. Lo crea el médico al
--    recetar (origen 'doctor') o el propio paciente (origen 'paciente').
-- 4. tomas: cada dosis programada de un tratamiento, con su hora exacta. Se
--    calculan al crear el tratamiento (ver App\Models\Tratamiento).
-- 5. suscripciones_push: navegadores del paciente que aceptaron notificaciones.
--
-- Las fechas se guardan en la hora local de la aplicación (America/Mexico_City),
-- igual que el resto de la base. La versión para PostgreSQL está en
-- migration_recetas_tratamientos_pgsql.sql. Las instalaciones nuevas ya traen
-- todo esto en schema_mysql.sql.
-- =============================================================================

ALTER TABLE usuarios
    MODIFY rol ENUM('admin', 'recepcion', 'doctor') NOT NULL DEFAULT 'admin',
    ADD COLUMN id_doctor INT(11) DEFAULT NULL AFTER rol,
    ADD UNIQUE KEY unq_usuarios_doctor (id_doctor),
    ADD CONSTRAINT fk_usuario_doctor FOREIGN KEY (id_doctor) REFERENCES doctores (id_doctor) ON DELETE CASCADE;

CREATE TABLE IF NOT EXISTS recetas (
    id_receta     INT(11) NOT NULL AUTO_INCREMENT,
    id_paciente   INT(11) NOT NULL,
    id_doctor     INT(11) NOT NULL,
    id_cita       INT(11) DEFAULT NULL,
    diagnostico   VARCHAR(255) NOT NULL,
    indicaciones  TEXT DEFAULT NULL,
    fecha_emision DATETIME NOT NULL,
    PRIMARY KEY (id_receta),
    KEY idx_recetas_paciente (id_paciente, fecha_emision),
    KEY idx_recetas_doctor (id_doctor, fecha_emision),
    CONSTRAINT fk_receta_paciente FOREIGN KEY (id_paciente) REFERENCES pacientes (id_paciente) ON DELETE CASCADE,
    CONSTRAINT fk_receta_doctor FOREIGN KEY (id_doctor) REFERENCES doctores (id_doctor) ON DELETE CASCADE,
    CONSTRAINT fk_receta_cita FOREIGN KEY (id_cita) REFERENCES citas (id_cita) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tratamientos (
    id_tratamiento   INT(11) NOT NULL AUTO_INCREMENT,
    id_paciente      INT(11) NOT NULL,
    id_receta        INT(11) DEFAULT NULL,
    origen           ENUM('doctor', 'paciente') NOT NULL,
    medicamento      VARCHAR(120) NOT NULL,
    dosis            VARCHAR(120) NOT NULL,
    frecuencia_horas SMALLINT NOT NULL,
    duracion_dias    SMALLINT NOT NULL,
    fecha_inicio     DATETIME NOT NULL,
    fecha_fin        DATETIME NOT NULL,
    notas            VARCHAR(255) DEFAULT NULL,
    estado           ENUM('Activo', 'Pausado', 'Cancelado') NOT NULL DEFAULT 'Activo',
    creado_en        DATETIME NOT NULL,
    PRIMARY KEY (id_tratamiento),
    KEY idx_tratamientos_paciente (id_paciente, estado),
    KEY idx_tratamientos_receta (id_receta),
    CONSTRAINT fk_tratamiento_paciente FOREIGN KEY (id_paciente) REFERENCES pacientes (id_paciente) ON DELETE CASCADE,
    CONSTRAINT fk_tratamiento_receta FOREIGN KEY (id_receta) REFERENCES recetas (id_receta) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tomas (
    id_toma        INT(11) NOT NULL AUTO_INCREMENT,
    id_tratamiento INT(11) NOT NULL,
    programada_en  DATETIME NOT NULL,
    estado         ENUM('Pendiente', 'Tomada', 'Omitida') NOT NULL DEFAULT 'Pendiente',
    registrada_en  DATETIME DEFAULT NULL,
    notificada_en  DATETIME DEFAULT NULL,
    PRIMARY KEY (id_toma),
    UNIQUE KEY unq_tomas_tratamiento_hora (id_tratamiento, programada_en),
    KEY idx_tomas_aviso (programada_en, notificada_en),
    CONSTRAINT fk_toma_tratamiento FOREIGN KEY (id_tratamiento) REFERENCES tratamientos (id_tratamiento) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- endpoint_hash (SHA-256 del endpoint) evita indexar una URL larga y detecta duplicados.
CREATE TABLE IF NOT EXISTS suscripciones_push (
    id_suscripcion INT(11) NOT NULL AUTO_INCREMENT,
    id_paciente    INT(11) NOT NULL,
    endpoint       TEXT NOT NULL,
    endpoint_hash  CHAR(64) NOT NULL,
    clave_p256dh   VARCHAR(255) NOT NULL,
    clave_auth     VARCHAR(100) NOT NULL,
    agente         VARCHAR(255) DEFAULT NULL,
    creada_en      DATETIME NOT NULL,
    ultimo_envio   DATETIME DEFAULT NULL,
    PRIMARY KEY (id_suscripcion),
    UNIQUE KEY unq_suscripciones_endpoint (endpoint_hash),
    KEY idx_suscripciones_paciente (id_paciente),
    CONSTRAINT fk_suscripcion_paciente FOREIGN KEY (id_paciente) REFERENCES pacientes (id_paciente) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
