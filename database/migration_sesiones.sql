-- Migración: tabla de sesiones de PHP en la base de datos
--
-- Solo es necesaria si se usa SESSION_DRIVER=database (por ejemplo, al desplegar
-- en Vercel, donde los archivos de sesión no se conservan entre peticiones).
-- Con SESSION_DRIVER=files (XAMPP) la tabla no se usa.
-- Las instalaciones nuevas ya la tienen en schema_mysql.sql / schema_pgsql.sql.

CREATE TABLE IF NOT EXISTS sesiones (
    id          VARCHAR(128) NOT NULL,
    datos       TEXT NOT NULL,
    actualizado INT(11) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sesiones_actualizado (actualizado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
