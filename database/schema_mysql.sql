-- =============================================================================
-- ESQUEMA DE MEDIAGENDA PARA MYSQL / MARIADB (XAMPP)
--
-- Solo estructura, sin datos. Para una instalación nueva: crea la base de datos,
-- selecciónala y ejecuta este archivo. Equivale al estado de la base real con
-- todas las migraciones aplicadas (sin la columna curp, que el código ya no usa).
--
-- La versión para PostgreSQL (Supabase) está en schema_pgsql.sql.
-- =============================================================================

SET NAMES utf8mb4;

CREATE TABLE especialidades (
    id_especialidad     INT(11) NOT NULL AUTO_INCREMENT,
    nombre_especialidad VARCHAR(100) NOT NULL,
    PRIMARY KEY (id_especialidad)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE consultorios (
    id_consultorio  INT(11) NOT NULL AUTO_INCREMENT,
    nombre          VARCHAR(100) NOT NULL,
    id_especialidad INT(11) DEFAULT NULL,
    ubicacion       VARCHAR(255) NOT NULL,
    PRIMARY KEY (id_consultorio),
    CONSTRAINT fk_consultorios_especialidad FOREIGN KEY (id_especialidad) REFERENCES especialidades (id_especialidad)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE doctores (
    id_doctor        INT(11) NOT NULL AUTO_INCREMENT,
    nombre           VARCHAR(50) NOT NULL,
    apellido_paterno VARCHAR(50) NOT NULL,
    id_especialidad  INT(11) NOT NULL,
    id_consultorio   INT(11) DEFAULT NULL,
    estado           ENUM('Activo', 'Inactivo') NOT NULL DEFAULT 'Activo',
    PRIMARY KEY (id_doctor),
    CONSTRAINT fk_doctor_especialidad FOREIGN KEY (id_especialidad) REFERENCES especialidades (id_especialidad) ON DELETE CASCADE,
    CONSTRAINT fk_doctor_consultorio FOREIGN KEY (id_consultorio) REFERENCES consultorios (id_consultorio) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE enfermeros (
    id_enfermero     INT(11) NOT NULL AUTO_INCREMENT,
    nombre           VARCHAR(50) NOT NULL,
    apellido_paterno VARCHAR(50) NOT NULL,
    turno            ENUM('Matutino', 'Vespertino', 'Nocturno') NOT NULL,
    id_consultorio   INT(11) DEFAULT NULL,
    PRIMARY KEY (id_enfermero),
    CONSTRAINT fk_enfermero_consultorio FOREIGN KEY (id_consultorio) REFERENCES consultorios (id_consultorio) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE pacientes (
    id_paciente            INT(11) NOT NULL AUTO_INCREMENT,
    created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    nombre                 VARCHAR(50) NOT NULL,
    apellido_paterno       VARCHAR(50) NOT NULL,
    apellido_materno       VARCHAR(50) DEFAULT NULL,
    fecha_nacimiento       DATE NOT NULL,
    genero                 ENUM('Masculino', 'Femenino', 'Otro') NOT NULL,
    email                  VARCHAR(100) NOT NULL,
    telefono               VARCHAR(10) DEFAULT NULL,
    cuenta_eliminada       TINYINT(1) NOT NULL DEFAULT 0,
    recibir_notificaciones TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id_paciente),
    UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE pacientes_credenciales (
    id_paciente       INT(11) NOT NULL,
    password_hash     VARCHAR(255) NOT NULL,
    cuenta_verificada TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id_paciente),
    CONSTRAINT fk_credenciales_paciente FOREIGN KEY (id_paciente) REFERENCES pacientes (id_paciente) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE usuarios (
    id_usuario    INT(11) NOT NULL AUTO_INCREMENT,
    usuario       VARCHAR(50) NOT NULL,
    nombre        VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol           ENUM('admin', 'recepcion', 'doctor') NOT NULL DEFAULT 'admin',
    -- Solo para el rol doctor: médico al que pertenece la cuenta.
    id_doctor     INT(11) DEFAULT NULL,
    PRIMARY KEY (id_usuario),
    UNIQUE KEY usuario (usuario),
    UNIQUE KEY unq_usuarios_doctor (id_doctor),
    CONSTRAINT fk_usuario_doctor FOREIGN KEY (id_doctor) REFERENCES doctores (id_doctor) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE citas (
    id_cita              INT(11) NOT NULL AUTO_INCREMENT,
    id_paciente          INT(11) NOT NULL,
    id_doctor            INT(11) NOT NULL,
    id_consultorio       INT(11) NOT NULL,
    fecha_cita           DATE NOT NULL,
    hora_inicio          TIME NOT NULL,
    motivo_consulta      TEXT NOT NULL,
    estado_cita          ENUM('Pendiente', 'Confirmada', 'En Curso', 'Completada', 'Cancelada') NOT NULL DEFAULT 'Pendiente',
    oculto_para_paciente TINYINT(1) NOT NULL DEFAULT 0,
    -- Contiene médico+fecha+hora solo si la cita está activa (NULL si está cancelada).
    -- Con el índice único impide dos citas activas del mismo médico a la misma hora.
    slot_activo          VARCHAR(50) GENERATED ALWAYS AS (
                             CASE WHEN estado_cita <> 'Cancelada'
                             THEN CONCAT(id_doctor, '_', fecha_cita, '_', hora_inicio) ELSE NULL END
                         ) STORED,
    PRIMARY KEY (id_cita),
    UNIQUE KEY unq_slot_activo (slot_activo),
    KEY idx_cita_doctor (id_doctor),
    KEY idx_cita_paciente (id_paciente),
    KEY idx_cita_fecha (fecha_cita),
    CONSTRAINT fk_cita_paciente FOREIGN KEY (id_paciente) REFERENCES pacientes (id_paciente) ON DELETE CASCADE,
    CONSTRAINT fk_cita_doctor FOREIGN KEY (id_doctor) REFERENCES doctores (id_doctor) ON DELETE CASCADE,
    CONSTRAINT fk_cita_consultorio FOREIGN KEY (id_consultorio) REFERENCES consultorios (id_consultorio) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE bitacora (
    id         INT(11) NOT NULL AUTO_INCREMENT,
    id_usuario INT(11) DEFAULT NULL,
    rol        VARCHAR(50) NOT NULL,
    accion     VARCHAR(100) NOT NULL,
    entidad    VARCHAR(50) NOT NULL,
    id_entidad INT(11) NOT NULL,
    detalle    TEXT DEFAULT NULL,
    fecha      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bitacora_usuario (id_usuario),
    KEY idx_bitacora_fecha (fecha),
    KEY idx_bitacora_accion (accion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE login_attempts (
    id            INT(11) NOT NULL AUTO_INCREMENT,
    identificador VARCHAR(100) NOT NULL,
    attempts      INT(11) NOT NULL DEFAULT 1,
    first_attempt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    block_count   INT(11) NOT NULL DEFAULT 0,
    blocked_until DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_login_attempts_identificador (identificador)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE password_resets (
    id_reset INT(11) NOT NULL AUTO_INCREMENT,
    email    VARCHAR(100) NOT NULL,
    token    VARCHAR(255) NOT NULL,
    expira   DATETIME NOT NULL,
    usado    TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id_reset),
    KEY idx_pwd_reset_email (email),
    KEY idx_pwd_reset_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE cambios_password_pendientes (
    id          INT(11) NOT NULL AUTO_INCREMENT,
    id_paciente INT(11) NOT NULL,
    nuevo_hash  VARCHAR(255) NOT NULL,
    token       VARCHAR(64) NOT NULL,
    expira      DATETIME NOT NULL,
    usado       TINYINT(1) NOT NULL DEFAULT 0,
    creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY token (token),
    KEY idx_cambios_password_paciente (id_paciente),
    CONSTRAINT fk_cambios_password_paciente FOREIGN KEY (id_paciente) REFERENCES pacientes (id_paciente) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Sesiones de PHP (solo se usa con SESSION_DRIVER=database).
CREATE TABLE sesiones (
    id          VARCHAR(128) NOT NULL,
    datos       TEXT NOT NULL,
    actualizado INT(11) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sesiones_actualizado (actualizado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Recetas, tratamientos con sus tomas y suscripciones a notificaciones push.
CREATE TABLE recetas (
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

CREATE TABLE tratamientos (
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

CREATE TABLE tomas (
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
CREATE TABLE suscripciones_push (
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
