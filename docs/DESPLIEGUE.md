# Despliegue de la demo en Vercel + Supabase

Esta guía describe cómo está publicada la demo de MediAgenda en
`https://mediagenda.vanguarddevsec.com` y cómo repetir el proceso desde cero.
La instalación local con XAMPP está en el [README](../README.md).

## Arquitectura

```
Navegador
   │
   ▼
Vercel ──► CDN: /public/assets/(css|js|images)/...   (copia estática de public/assets)
   │        /public/sw.js y /public/manifest.webmanifest (avisos push y app instalable)
   │
   └─────► Función PHP api/vercel.php (runtime vercel-php, PHP 8.3)
              │  lista blanca: solo páginas de public/ y endpoints de api/
              ▼
           Supabase PostgreSQL (pooler de transacciones, puerto 6543)
              esquema "mediagenda" + usuario "mediagenda_app" con acceso solo a ese esquema
              pg_cron + pg_net ──► cada minuto llama a api/enviar_recordatorios.php
                                   └──► servicios push de los navegadores (Web Push / VAPID)
```

Vercel ejecuta PHP como funciones sin servidor: cada petición puede atenderla
una instancia distinta y el disco es de solo lectura (salvo la carpeta temporal).
Por eso la demo usa:

- `SESSION_DRIVER=database`: las sesiones se guardan en la tabla `sesiones`.
- `DB_DRIVER=pgsql`: el SQL de la aplicación es compatible con MySQL y PostgreSQL.
- La carpeta temporal del sistema para los archivos de Dompdf.

## 1. Base de datos en Supabase

La demo vive en un esquema propio (`mediagenda`), así que puede compartir un
proyecto de Supabase que ya se use para otra cosa sin tocar sus tablas. El
esquema no se publica en la API REST de Supabase: solo PHP accede a él, con su
propio usuario.

1. En el editor SQL de Supabase, ejecuta [`database/supabase_setup.sql`](../database/supabase_setup.sql)
   después de reemplazar `CAMBIA_ESTA_CONTRASEÑA` por una contraseña larga y
   aleatoria. Otra opción es pasar un verificador `SCRAM-SHA-256` en lugar de la
   contraseña en texto plano. **No subas la contraseña real al repositorio.**
2. Ejecuta [`database/schema_pgsql.sql`](../database/schema_pgsql.sql) con
   `SET search_path TO mediagenda;` en la primera línea.

   Si la base ya existía antes de las recetas y los recordatorios, aplica solo
   la migración [`database/migration_recetas_tratamientos_pgsql.sql`](../database/migration_recetas_tratamientos_pgsql.sql)
   (también con `SET search_path TO mediagenda;`). Crea las tablas `recetas`,
   `tratamientos`, `tomas` y `suscripciones_push`, y agrega el rol `doctor` a
   `usuarios`. Los permisos de `mediagenda_app` sobre las tablas nuevas salen de
   los privilegios por defecto de `supabase_setup.sql`.
3. Copia los datos de conexión desde el botón **Connect** del panel, en la sección
   **Transaction pooler**:

   | Variable | Valor |
   |----------|-------|
   | `DB_HOST` | `aws-1-<región>.pooler.supabase.com` (cópialo tal cual: puede ser `aws-0` o `aws-1`) |
   | `DB_PORT` | `6543` |
   | `DB_NAME` | `postgres` |
   | `DB_USER` | `mediagenda_app.<referencia_del_proyecto>` |
   | `DB_SSLMODE` | `require` |

4. Llena la base con datos ficticios desde tu equipo. Hace falta la extensión
   `pdo_pgsql` habilitada en el `php.ini`. Configura el `.env` con los datos de
   arriba, `DB_DRIVER=pgsql` y `DEMO_MODE=true`, y ejecuta:

   ```bash
   php database/sembrar_demo.php --confirmar
   ```

   El script borra todos los datos de la base configurada y crea especialidades,
   consultorios, médicos, enfermeros, 25 pacientes ficticios y unas 800 citas con
   fechas relativas al día de hoy.

> El pooler de transacciones no admite sentencias preparadas del lado del
> servidor. `App\Core\Database` usa preparación emulada en PostgreSQL, así que
> no hace falta configurar nada más.

## 2. Proyecto en Vercel

1. **Add New → Project** e importa el repositorio de GitHub. El preset de
   framework se queda en **Other**: todo lo demás lo define [`vercel.json`](../vercel.json).
2. En **Settings → Build and Deployment**, la versión de Node.js es **22.x**.
3. En **Settings → Environment Variables**, agrega estas variables para
   *Production* y *Preview*. Marca como **Sensitive** las tres que son secretas:

   | Variable | Valor |
   |----------|-------|
   | `APP_ENV` | `production` |
   | `URL_BASE` | `https://mediagenda.vanguarddevsec.com` |
   | `DB_DRIVER` | `pgsql` |
   | `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_SSLMODE` | los del paso 1.3 |
   | `DB_PASS` | contraseña de `mediagenda_app` (**Sensitive**) |
   | `SESSION_DRIVER` | `database` |
   | `TRUST_PROXY` | `true` |
   | `MAIL_DRIVER` | `log` (la demo no envía correos) |
   | `DEMO_MODE` | `true` |
   | `CRON_SECRET` | texto aleatorio de 32 caracteres o más (**Sensitive**) |
   | `VAPID_PUBLIC_KEY` | clave pública para los avisos push (ver sección 6) |
   | `VAPID_PRIVATE_KEY` | clave privada para los avisos push (**Sensitive**) |
   | `VAPID_SUBJECT` | `https://mediagenda.vanguarddevsec.com` |
   | `CONTACTO_TELEFONO`, `CONTACTO_EMAIL`, `CONTACTO_UBICACION` | opcionales; sin ellas se ven textos de ejemplo |

4. En **Settings → Deployment Protection**, deja **Standard Protection**: las
   vistas previas piden iniciar sesión en Vercel y el dominio propio es público.

### Qué hace `vercel.json`

| Clave | Para qué |
|-------|----------|
| `functions` | Ejecuta `api/vercel.php` con el runtime `vercel-php@0.7.4` (PHP 8.3), que también instala las dependencias de Composer desde `composer.lock`. |
| `buildCommand` / `outputDirectory` | Copia a `dist/` solo `public/assets`, el service worker (`public/sw.js`) y el manifiesto. Así el CDN sirve CSS, JS e imágenes en las mismas URLs que en XAMPP, y ningún archivo `.php` llega al CDN. |
| `routes` | Los archivos estáticos se sirven desde el CDN (el service worker sin caché, para que los cambios lleguen de inmediato); cualquier otra ruta va a `api/vercel.php`, con cabeceras de seguridad. |
| `crons` | Llama a `api/demo_reset.php` todos los días a las 09:00 UTC (03:00 en Ciudad de México). |
| `regions` | `pdx1` (Oregón), la región más cercana a la base de Supabase (`us-west-2`). |

[`api/vercel.php`](../api/vercel.php) es el único punto de entrada en Vercel:
solo ejecuta páginas de `public/` y endpoints de `api/` que existen. Todo lo
demás (`config/`, `src/`, `vendor/`, `.env`, `composer.json`...) responde 404.
[`.vercelignore`](../.vercelignore) además deja fuera del despliegue `database/` y `.env`.

## 3. Dominio propio

En **Settings → Domains** agrega `mediagenda.vanguarddevsec.com`. Como el DNS de
`vanguarddevsec.com` lo administra Vercel, el registro y el certificado HTTPS se
crean solos. Si el DNS estuviera en otro proveedor, habría que crear un `CNAME`
hacia `cname.vercel-dns.com`.

## 4. Flujo de trabajo

| Rama | Despliegue |
|------|-----------|
| `developer` | Vista previa en una URL `*.vercel.app` protegida (solo con sesión de Vercel). |
| `main` | Producción en `mediagenda.vanguarddevsec.com`. |

Los cambios se suben a `developer`, se revisan en la vista previa y se integran a
`main` con un pull request.

## 5. Reinicio diario de la demo

Cualquier visitante puede crear citas, pacientes o personal, así que la base se
regenera cada día. Vercel llama a `api/demo_reset.php` con la cabecera
`Authorization: Bearer <CRON_SECRET>`; sin ese secreto el endpoint responde 401.
En el plan Hobby la tarea se ejecuta una vez al día, en algún momento dentro de
la hora programada.

Para reiniciar a mano:

```bash
curl -H "Authorization: Bearer $CRON_SECRET" https://mediagenda.vanguarddevsec.com/api/demo_reset.php
```

## 6. Recordatorios de medicamentos (avisos push)

Cada toma de un tratamiento se guarda con su hora exacta. Un proceso revisa
cada minuto las tomas que ya llegaron a su hora y manda un aviso push a los
navegadores donde el paciente los activó. Las tareas de Vercel en el plan Hobby
solo corren una vez al día, así que quien llama cada minuto es Supabase, con
las extensiones `pg_cron` y `pg_net`.

1. **Claves VAPID.** Se generan una sola vez desde tu equipo (en Windows con
   XAMPP hace falta `OPENSSL_CONF=C:/xampp/php/extras/ssl/openssl.cnf`):

   ```bash
   php -r 'require "vendor/autoload.php"; print_r(Minishlink\WebPush\VAPID::createVapidKeys());'
   ```

   Guarda `publicKey` en `VAPID_PUBLIC_KEY` y `privateKey` en
   `VAPID_PRIVATE_KEY` (**Sensitive**) en Vercel. Si cambias las claves, las
   suscripciones existentes dejan de funcionar y cada paciente debe activar los
   avisos otra vez.

2. **Secreto en Vault.** En el editor SQL de Supabase, guarda el mismo
   `CRON_SECRET` de Vercel (pégalo en el editor, nunca en el repositorio):

   ```sql
   select vault.create_secret('<CRON_SECRET>', 'mediagenda_cron_secret');
   ```

3. **Tarea cada minuto.** Con `pg_cron` y `pg_net` habilitadas en
   **Database → Extensions**:

   ```sql
   select cron.schedule(
     'mediagenda-recordatorios',
     '* * * * *',
     $$
     select net.http_post(
       url := 'https://mediagenda.vanguarddevsec.com/api/enviar_recordatorios.php',
       headers := jsonb_build_object(
         'Authorization', 'Bearer ' || (
           select decrypted_secret from vault.decrypted_secrets
           where name = 'mediagenda_cron_secret'
         )
       ),
       body := '{}'::jsonb
     );
     $$
   );
   ```

   Para revisar las últimas llamadas: `select * from net._http_response order by created desc limit 5;`.
   Para quitar la tarea: `select cron.unschedule('mediagenda-recordatorios');`.

El endpoint responde 401 sin el secreto. Cada toma se avisa una sola vez (queda
marcada como notificada) y las suscripciones que el navegador da de baja se
borran solas. Los avisos solo funcionan en HTTPS (o `localhost`) y el paciente
debe permitirlos en su navegador; desde **Mi semana** puede mandarse un aviso de
prueba.

En XAMPP no hace falta Supabase: programa
`php database/enviar_recordatorios.php` cada minuto en el Programador de tareas
de Windows.

## 7. Cuentas de prueba

| Rol | Usuario | Contraseña |
|-----|---------|------------|
| Administrador | `demo.admin` | `Demo2026!` |
| Médico | `demo.doctor` | `Demo2026!` |
| Paciente | `paciente@example.com` | `Demo2026!` |

Son públicas a propósito: aparecen en la pantalla de inicio de sesión. Con
`DEMO_MODE=true` nadie puede cambiar los datos, la contraseña ni eliminar la
cuenta del paciente de prueba, ni quitar o cambiar el acceso del médico de
prueba.

## 8. Límites de los planes gratuitos

- **Vercel Hobby:** solo para uso personal y no comercial (una demo de portafolio
  entra en ese uso). Los logs de ejecución se conservan una hora.
- **Supabase Free:** máximo dos proyectos activos. Los proyectos sin actividad se
  pausan; el reinicio diario mantiene actividad en la base.
- **Protección automática de Vercel:** si un mismo equipo hace muchas peticiones
  seguidas con un script (por ejemplo, pruebas automatizadas), Vercel responde
  con su página *Security Checkpoint* durante unos minutos. A los visitantes con
  navegador no les afecta.

## 9. Solución de problemas

| Síntoma | Causa probable |
|---------|----------------|
| `Tenant or user not found` | Host del pooler incorrecto (`aws-0` en lugar de `aws-1`, o al revés) o falta `.<referencia>` en `DB_USER`. |
| La página se ve sin estilos (CSS 404) | Faltan `buildCommand` / `outputDirectory` en `vercel.json`. |
| La sesión se pierde entre páginas | `SESSION_DRIVER` no es `database` o falta la tabla `sesiones`. |
| Todos los visitantes comparten el límite de intentos de inicio de sesión | Falta `TRUST_PROXY=true`. |
| `prepared statement ... already exists` | Conexión al pooler sin preparación emulada; revisa `App\Core\Database`. |
| No aparece el botón para activar avisos | Falta `VAPID_PUBLIC_KEY` en Vercel o el navegador no admite push (en iPhone, solo con la app agregada a la pantalla de inicio). |
| Los avisos no llegan a su hora | La tarea de `pg_cron` no corre o el secreto de Vault no coincide con `CRON_SECRET`: revisa `net._http_response` (un 401 indica el secreto). |
| `column "id_doctor" does not exist` al iniciar sesión | Falta la migración de recetas y tratamientos. |
