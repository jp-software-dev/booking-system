# Base de datos

## Instalación nueva

| Motor | Archivo | Cuándo usarlo |
|-------|---------|---------------|
| MySQL / MariaDB | `schema_mysql.sql` | XAMPP o cualquier hosting con MySQL. |
| PostgreSQL | `schema_pgsql.sql` | Supabase u otro PostgreSQL. En Supabase ejecuta antes `supabase_setup.sql`. |

Ambos crean solo la estructura, sin datos. Para llenar la base con datos ficticios
(demo), pon `DEMO_MODE=true` en el `.env` y ejecuta:

```bash
php database/sembrar_demo.php --confirmar
```

El script **borra todos los datos** de la base del `.env`. Por eso exige
`DEMO_MODE=true` y `--confirmar`: nunca lo uses con una base real.

## Migraciones de la base existente (MySQL)

El volcado original de la base real (`citas.sql`) **no está versionado** porque
contiene datos reales; `.gitignore` lo excluye. Estos archivos son los cambios
que se le aplicaron después, en el orden en que se agregaron al repositorio:

| # | Archivo | Qué hace |
|---|---------|----------|
| 1 | `migration_autogestion_cuenta.sql` | `pacientes.cuenta_eliminada`; `telefono` y `curp` admiten NULL. Asume que `telefono` ya existe (ver #9). |
| 2 | `migration_bitacora.sql` | Crea la tabla `bitacora`. |
| 3 | `migration_oculto_historial_paciente.sql` | `citas.oculto_para_paciente`. |
| 4 | `migration_dashboard_pacientes_doctores.sql` | `pacientes.created_at`. |
| 5 | `migration_notificaciones_paciente.sql` | `pacientes.recibir_notificaciones`. |
| 6 | `migration_backoff_progresivo.sql` | `login_attempts.block_count` y `blocked_until`. |
| 7 | `migration_confirmacion_password.sql` | Crea `cambios_password_pendientes`. |
| 8 | `migration_nullable_id_consultorio.sql` | `doctores.id_consultorio` admite NULL. |
| 9 | `migration_restaurar_telefono_pacientes.sql` | Agrega `pacientes.telefono` (para bases donde #1 no aplicó). |
| 10 | `migration_especialidad_consultorio.sql` | `consultorios.id_especialidad`. |
| 11 | `migration_nullable_id_consultorio_enfermeros.sql` | `enfermeros.id_consultorio` admite NULL. |
| 12 | `migration_quitar_fk_bitacora.sql` | Quita la llave foránea de `bitacora.id_usuario`. |
| 13 | `migration_slot_activo_real.sql` | Columna generada `citas.slot_activo` con índice único (impide dos citas activas del mismo médico a la misma hora). |
| 14 | `migration_eliminar_curp.sql` | Elimina `pacientes.curp`. **Irreversible.** |
| 15 | `migration_sesiones.sql` | Tabla `sesiones`. Solo si se usa `SESSION_DRIVER=database`. |

## Antes de aplicar una migración

- La base real no siempre coincide con lo que estos archivos suponen (por
  ejemplo, `bitacora.id_entidad` es `NOT NULL` en la base real). Revisa la tabla
  con `SHOW CREATE TABLE` antes de aplicar cualquier archivo.
- Al 2026-10-09 la base real todavía tiene la columna `curp`: #14 no se ha aplicado.
  El código ya no la usa, así que aplicarla es opcional.
- `migration_unique_slot.sql` se eliminó: estaba marcado como "NO APLICAR" y lo
  reemplaza #13.
