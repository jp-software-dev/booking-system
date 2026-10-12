-- =============================================================================
-- PREPARACIÓN DE SUPABASE PARA MEDIAGENDA
--
-- Crea un esquema propio ("mediagenda") y un usuario de base de datos que solo
-- tiene acceso a ese esquema. Así la demo puede vivir dentro de un proyecto de
-- Supabase que ya se usa para otra cosa sin tocar sus tablas.
--
-- El esquema "mediagenda" no se publica en la API REST de Supabase (solo se
-- publica "public"), así que sus tablas no son accesibles con las llaves anon
-- o service del proyecto: solo con este usuario, desde PHP.
--
-- Uso:
--   1. Reemplaza CAMBIA_ESTA_CONTRASEÑA por una contraseña larga y aleatoria.
--      ¡No subas al repositorio la contraseña real!
--   2. Ejecuta este archivo en el editor SQL de Supabase.
--   3. Ejecuta schema_pgsql.sql con "SET search_path TO mediagenda;" al inicio.
--   4. En el .env: DB_USER=mediagenda_app.<referencia_del_proyecto> (formato del pooler).
-- =============================================================================

CREATE SCHEMA IF NOT EXISTS mediagenda;

CREATE ROLE mediagenda_app WITH LOGIN PASSWORD 'CAMBIA_ESTA_CONTRASEÑA';

-- Solo puede usar su esquema (sin crear tablas): ni public ni ningún otro.
REVOKE ALL ON SCHEMA public FROM mediagenda_app;
GRANT USAGE ON SCHEMA mediagenda TO mediagenda_app;

-- Al conectarse, sus consultas buscan las tablas en "mediagenda" y usan la hora de México.
ALTER ROLE mediagenda_app SET search_path TO mediagenda;
ALTER ROLE mediagenda_app SET timezone TO 'America/Mexico_City';

-- Permisos sobre las tablas que se creen en el esquema (las crea el dueño del proyecto).
ALTER DEFAULT PRIVILEGES IN SCHEMA mediagenda GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO mediagenda_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA mediagenda GRANT USAGE, SELECT ON SEQUENCES TO mediagenda_app;
