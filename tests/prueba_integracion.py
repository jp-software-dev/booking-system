"""
PRUEBAS DE INTEGRACIÓN DE MEDIAGENDA

Recorre la aplicación como lo haría un usuario: registro, inicio de sesión,
agenda, reprogramación, cancelación, perfil, exportación de datos, recuperación
de contraseña, panel de administración, archivos protegidos y modo demo.

Requisitos de la base de datos: datos de demostración recién generados
(php database/sembrar_demo.php --confirmar) y DEMO_MODE=true.

Uso:
    python tests/prueba_integracion.py --base http://127.0.0.1:8000 --log /ruta/php.log

    --base  URL donde corre la aplicación (sin "/" final).
    --log   Log de PHP donde MAIL_DRIVER=log escribe los correos. Sin él se omite
            la parte de recuperación de contraseña que necesita leer el correo.

Solo usa la biblioteca estándar de Python. Termina con código 1 si alguna prueba falla.
¡Crea y borra registros! Nunca la ejecutes contra una base real.
"""

import argparse
import datetime as dt
import http.cookiejar
import json
import re
import sys
import time
import urllib.error
import urllib.request
import uuid

parametros = argparse.ArgumentParser(description="Pruebas de integración de MediAgenda")
parametros.add_argument("--base", required=True, help="URL base de la aplicación")
parametros.add_argument("--log", help="Log de PHP con los correos en modo log")
ARGS = parametros.parse_args()
BASE = ARGS.base.rstrip("/")

PASSWORD_DEMO = "Demo2026!"
resultados = []


# === UTILIDADES ===

def check(nombre, condicion, detalle=""):
    """Registra el resultado de una prueba y lo imprime."""
    resultados.append((nombre, bool(condicion)))
    print(("OK    " if condicion else "FALLA ") + nombre + ("" if condicion else f"  -> {detalle}"))


def sin_errores_php(texto):
    """True si la página no muestra avisos ni errores de PHP."""
    return not re.search(r"(Fatal error|Warning:|Notice:|Deprecated:|Uncaught|Parse error)", texto)


def proximo(dia_semana, semanas=1):
    """Fecha (AAAA-MM-DD) del próximo día de la semana (0 = lunes) dentro de 'semanas' semanas."""
    hoy = dt.date.today()
    dia = hoy + dt.timedelta(days=(dia_semana - hoy.weekday()) % 7 + 7 * semanas)
    return dia.isoformat()


def leer_log():
    """Contenido actual del log de PHP (vacío si no se indicó)."""
    if not ARGS.log:
        return ""
    try:
        with open(ARGS.log, encoding="utf-8", errors="replace") as archivo:
            return archivo.read()
    except FileNotFoundError:
        return ""


class Cliente:
    """Navegador mínimo: conserva cookies y el token CSRF de la última página."""

    def __init__(self):
        self.abridor = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.csrf = ""
        self.ultimas_cabeceras = {}

    def pedir(self, ruta, datos=None, metodo=None, cabeceras=None, multipart=None):
        """Hace una petición y devuelve (código, cuerpo en bytes)."""
        h = dict(cabeceras or {})
        cuerpo = None
        if multipart is not None:
            limite = uuid.uuid4().hex
            partes = [f'--{limite}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n' for k, v in multipart.items()]
            cuerpo = ("".join(partes) + f"--{limite}--\r\n").encode()
            h["Content-Type"] = f"multipart/form-data; boundary={limite}"
        elif datos is not None:
            cuerpo = json.dumps(datos).encode()
            h["Content-Type"] = "application/json"

        peticion = urllib.request.Request(BASE + ruta, data=cuerpo, headers=h, method=metodo)
        try:
            respuesta = self.abridor.open(peticion)
            codigo, contenido, self.ultimas_cabeceras = respuesta.status, respuesta.read(), respuesta.headers
        except urllib.error.HTTPError as e:
            codigo, contenido, self.ultimas_cabeceras = e.code, e.read(), e.headers
        return codigo, contenido

    def pagina(self, ruta):
        """Abre una página y guarda su token CSRF."""
        codigo, contenido = self.pedir(ruta)
        texto = contenido.decode("utf-8", "replace")
        token = re.search(r'name="csrf-token" content="([^"]+)"', texto)
        if token:
            self.csrf = token.group(1)
        return codigo, texto

    def api(self, endpoint, datos=None, csrf=True, metodo=None):
        """Llama a un endpoint de /api y decodifica el JSON."""
        cabeceras = {"X-CSRF-Token": self.csrf} if csrf else {}
        codigo, contenido = self.pedir("/api/" + endpoint, datos, metodo, cabeceras)
        try:
            return codigo, json.loads(contenido)
        except ValueError:
            return codigo, {"_crudo": contenido[:300]}


# === PACIENTE NUEVO ===
p = Cliente()
codigo, html = p.pagina("/public/registro.php")
check("registro.php carga", codigo == 200 and sin_errores_php(html), codigo)

email = f"prueba+ci{int(time.time())}@example.com"
password = "ClaveSegura123"
codigo, cuerpo = p.pedir("/api/auth_registro.php", cabeceras={"X-CSRF-Token": p.csrf}, multipart=dict(
    nombre="Prueba", apellido_paterno="Integracion", apellido_materno="", email=email, telefono="5512345678",
    fecha_nacimiento="1990-05-05", genero="Otro", password=password, confirm_password=password, sitio_web=""))
check("registro con correo con '+'", codigo == 200 and b'"success"' in cuerpo, cuerpo[:200])
cookie = p.ultimas_cabeceras.get("Set-Cookie", "")
check("cookie de sesión con HttpOnly y SameSite=Lax", "HttpOnly" in cookie and "SameSite=Lax" in cookie, cookie)

p = Cliente()
p.pagina("/public/login.php")
codigo, r = p.api("auth.php", {"usuario": email, "password": password, "sitio_web": ""})
check("login con correo con '+'", codigo == 200 and r.get("status") == "success", r)
codigo, r = p.api("auth.php", {"usuario": email, "password": password}, csrf=False)
check("login sin CSRF -> 403", codigo == 403, (codigo, r))
bot = Cliente()
bot.pagina("/public/login.php")
codigo, r = bot.api("auth.php", {"usuario": email, "password": password, "sitio_web": "http://spam"})
check("honeypot en login -> 401", codigo == 401, (codigo, r))

for ruta in ["/public/index.php", "/public/agenda.php", "/public/historial.php", "/public/cuenta.php", "/public/contacto.php"]:
    codigo, html = p.pagina(ruta)
    check(f"paciente: {ruta} carga sin errores", codigo == 200 and sin_errores_php(html), codigo)

codigo, html = p.pagina("/public/agenda.php")
medicos = re.findall(r'<option value="(\d+)" data-consultorio=', html)
check("agenda lista médicos con consultorio", len(medicos) > 0, len(medicos))

codigo, ocupados = p.api("get_citas.php", csrf=False)
check("get_citas devuelve id_doctor, fecha y hora",
      isinstance(ocupados, list) and (not ocupados or {"id_doctor", "fecha", "hora"} <= set(ocupados[0])), str(ocupados)[:150])

# Busca un horario libre (lunes dentro de 2 semanas) con el primer médico.
id_cita, hora_cita, r = None, None, {}
fecha = proximo(0, 2)
for hora in ["10:00", "10:30", "11:00", "11:30", "12:00", "12:30", "13:00"]:
    codigo, r = p.api("create_cita.php", {"id_doctor": medicos[0], "fecha_cita": fecha, "hora_inicio": hora, "motivo": "Prueba automática"})
    if r.get("status") == "success":
        id_cita, hora_cita = r.get("id_cita"), hora
        break
check("paciente agenda una cita", id_cita is not None, r)
codigo, r = p.api("create_cita.php", {"id_doctor": medicos[0], "fecha_cita": fecha, "hora_inicio": hora_cita, "motivo": "dup"})
check("mismo horario otra vez -> 409", codigo == 409, (codigo, r))
codigo, r = p.api("create_cita.php", {"id_doctor": medicos[0], "fecha_cita": "2020-01-06", "hora_inicio": "10:00"})
check("fecha pasada rechazada", codigo == 400, r)
codigo, r = p.api("create_cita.php", {"id_doctor": medicos[0], "fecha_cita": proximo(6, 2), "hora_inicio": "10:00"})
check("domingo rechazado", codigo == 400, r)
codigo, r = p.api("create_cita.php", {"id_doctor": medicos[0], "fecha_cita": fecha, "hora_inicio": "10:15"})
check("hora fuera de la rejilla rechazada", codigo == 400, r)

codigo, ocupados = p.api("get_citas.php", csrf=False)
claves = {(o["id_doctor"], o["fecha"], o["hora"]) for o in ocupados}
check("get_citas incluye la cita nueva con su médico", (int(medicos[0]), fecha, hora_cita) in claves)

codigo, r = p.api("update_cita_user.php", {"id_cita": id_cita, "fecha_cita": fecha, "hora_inicio": "14:00", "motivo": "Reprogramada"})
check("paciente reprograma su cita", r.get("status") == "success", r)
codigo, r = p.api("cancel_cita_user.php", {"id_cita": id_cita})
check("paciente cancela su cita", r.get("status") == "success", r)
codigo, r = p.api("cancel_cita_user.php", {"id_cita": id_cita})
check("cancelar dos veces -> 409", codigo == 409, (codigo, r))
codigo, r = p.api("cancel_cita_user.php", {"id_cita": 1})
check("cancelar cita ajena -> 404", codigo == 404, (codigo, r))

nuevo_email = email.replace("@", ".nuevo@")
perfil = {"nombre": "Prueba", "apellido_paterno": "Integracion", "apellido_materno": "", "telefono": "5512345678", "email": nuevo_email}
codigo, r = p.api("update_perfil.php", perfil)
check("cambiar correo sin contraseña -> rechazado", r.get("status") == "error", r)
codigo, r = p.api("update_perfil.php", {**perfil, "password_actual": password})
check("cambiar correo con contraseña", r.get("status") == "success", r)
email = nuevo_email

codigo, cuerpo = p.pedir("/api/exportar_datos_paciente.php?formato=json")
check("exportar datos en JSON", codigo == 200 and b"historial_citas" in cuerpo, codigo)
codigo, cuerpo = p.pedir("/api/exportar_datos_paciente.php?formato=pdf")
check("exportar datos en PDF", codigo == 200 and cuerpo[:4] == b"%PDF", cuerpo[:20])
codigo, r = p.api("update_status.php", {"id_cita": 1, "estado": "Confirmada"})
check("paciente no puede usar endpoint de admin -> 403", codigo == 403, (codigo, r))

# === RECUPERACIÓN DE CONTRASEÑA (necesita el log de PHP) ===
rec = Cliente()
rec.pagina("/public/recuperar.php")
tam_log = len(leer_log())
codigo, r = rec.api("recuperar.php", {"email": email})
check("solicitar recuperación", r.get("status") == "success", r)

if ARGS.log:
    time.sleep(0.5)
    token = re.search(r"restablecer\.php\?token=([0-9a-f]{64})", leer_log()[tam_log:])
    check("correo de recuperación escrito en el log", token is not None, leer_log()[-300:])
    if token:
        codigo, html = rec.pagina("/public/restablecer.php?token=" + token.group(1))
        check("restablecer.php muestra el formulario", codigo == 200 and "formRestablecer" in html, codigo)
        password = "OtraClave456"
        codigo, r = rec.api("restablecer.php", {"token": token.group(1), "password": password})
        check("restablecer contraseña", r.get("status") == "success", r)
        codigo, r = rec.api("restablecer.php", {"token": token.group(1), "password": "Reuso12345"})
        check("token usado dos veces -> rechazado", r.get("status") == "error", r)
        nuevo = Cliente()
        nuevo.pagina("/public/login.php")
        codigo, r = nuevo.api("auth.php", {"usuario": email, "password": password})
        check("login con la contraseña nueva", r.get("status") == "success", r)
else:
    print("(sin --log: se omite la lectura del correo de recuperación)")

# === ADMINISTRADOR ===
a = Cliente()
a.pagina("/public/login.php")
codigo, r = a.api("auth.php", {"usuario": "demo.admin", "password": PASSWORD_DEMO})
check("login de administrador", r.get("status") == "success" and r.get("redirect") == "admin.php", r)

for ruta in ["/public/admin.php", "/public/admin.php?estado=Pendiente&paciente=a", "/public/admin_dashboard.php",
             "/public/admin_historial.php", "/public/admin_historial.php?page=3&per_page=100",
             "/public/admin_consultorios.php", "/public/admin_equipo.php", "/public/admin_pacientes.php",
             "/public/admin_pacientes.php?q=a&estado_cuenta=activa", "/public/admin_pacientes.php?ver=1",
             "/public/admin_bitacora.php", "/public/admin_bitacora.php?usuario=admin&desde=2026-01-01", "/public/agenda.php"]:
    codigo, html = a.pagina(ruta)
    check(f"admin: {ruta} carga sin errores", codigo == 200 and sin_errores_php(html), codigo)

codigo, r = a.api("get_totales.php?rango=30d", csrf=False)
check("dashboard: get_totales", r.get("status") == "success" and "kpis" in r.get("data", {}), str(r)[:200])
codigo, r = a.api("get_totales.php?rango=xx", csrf=False)
check("dashboard: rango inválido -> 400", codigo == 400, codigo)

id_admin = None
fecha2 = proximo(1, 2)
for hora in ["09:00", "09:30", "15:00", "15:30", "16:00"]:
    codigo, r = a.api("create_cita.php", {"email": email, "id_doctor": medicos[0], "fecha_cita": fecha2, "hora_inicio": hora, "motivo": "Alta por admin"})
    if r.get("status") == "success":
        id_admin = r["id_cita"]
        break
check("admin agenda a nombre de un paciente", id_admin is not None, r)
codigo, r = a.api("update_status.php", {"id_cita": id_admin, "estado": "Confirmada"})
check("admin confirma la cita", r.get("status") == "success", r)
codigo, r = a.api("update_status.php", {"id_cita": id_admin, "estado": "Confirmada"})
check("mismo estado otra vez no da 'no encontrada'", r.get("status") == "success", r)
codigo, r = a.api("update_cita_admin.php", {"id_cita": id_admin, "fecha_cita": fecha2, "hora_inicio": "17:00"})
check("admin reprograma la cita", r.get("status") == "success", r)
codigo, cuerpo = a.pedir(f"/api/generar_pdf.php?id_cita={id_admin}")
check("PDF del panel", codigo == 200 and cuerpo[:4] == b"%PDF", cuerpo[:30])
codigo, r = a.api("delete_cita.php", {"id_cita": id_admin})
check("admin elimina la cita", r.get("status") == "success", r)

codigo, r = a.api("add_personal.php", {"tipo": "enfermero", "nombre": "Enfermero", "apellido_paterno": "Prueba", "turno": "Nocturno", "id_consultorio": ""})
check("alta de enfermero", r.get("status") == "success", r)
codigo, html = a.pagina("/public/admin_equipo.php")
ids = re.findall(r'data-baja-personal\s+data-id="(\d+)" data-tipo="enfermero" data-nombre="Enfermero Prueba"', html)
check("el enfermero aparece en el equipo", len(ids) > 0)
if ids:
    codigo, r = a.api("update_personal.php", {"id": ids[-1], "tipo": "enfermero", "nombre": "Enfermero", "apellido_paterno": "Editado", "turno": "Matutino", "id_consultorio": ""})
    check("edición de enfermero", r.get("status") == "success", r)
    codigo, r = a.api("delete_personal.php", {"id": ids[-1], "tipo": "Enfermero"})
    check("baja de enfermero", r.get("status") == "success", r)
codigo, r = a.api("delete_personal.php", {"id": 0, "tipo": "xyz"})
check("baja con tipo inválido -> 400", codigo == 400, (codigo, r))

# === ARCHIVOS INTERNOS (403 en Apache, 404 con el enrutador de Vercel) ===
anonimo = Cliente()
for ruta in ["/.git/HEAD", "/.env", "/vendor/autoload.php", "/composer.json", "/src/funciones.php",
             "/config/config.php", "/database/schema_mysql.sql", "/tests/prueba_integracion.py", "/api/vercel.php"]:
    codigo, _ = anonimo.pedir(ruta)
    check(f"bloqueado {ruta}", codigo in (403, 404), codigo)
codigo, _ = anonimo.pedir("/public/login")
check("URL sin extensión /public/login", codigo == 200, codigo)
codigo, _ = anonimo.api("get_citas.php", csrf=False)
check("get_citas sin sesión -> 401", codigo == 401, codigo)
codigo, _ = anonimo.pedir("/")
check("la raíz del sitio abre la aplicación", codigo == 200, codigo)

# === MODO DEMO ===
d = Cliente()
codigo, html = d.pagina("/public/login.php")
check("login muestra las cuentas de prueba", "Cuentas de prueba" in html and "demo.admin" in html, codigo)
check("aviso de demo en las páginas", "datos son ficticios" in html)
codigo, html = d.pagina("/public/registro.php")
check("registro pide datos ficticios en modo demo", "Usa datos ficticios" in html, codigo)
codigo, r = d.api("auth.php", {"usuario": "paciente@example.com", "password": PASSWORD_DEMO})
check("login del paciente de prueba", r.get("status") == "success", r)
codigo, r = d.api("update_perfil.php", {"nombre": "Hackeado", "apellido_paterno": "X", "apellido_materno": "", "telefono": "5500000000", "email": "paciente@example.com"})
check("demo: no se cambian los datos del paciente de prueba (403)", codigo == 403 and r.get("status") == "error", (codigo, r))
codigo, r = d.api("update_perfil.php", {"recibir_notificaciones": 1})
check("demo: sí se cambia la preferencia de notificaciones", r.get("status") == "success", r)
codigo, r = d.api("solicitar_cambio_password.php", {"password_actual": PASSWORD_DEMO, "password_nueva": "OtraClave99", "password_confirmar": "OtraClave99"})
check("demo: no se cambia la contraseña del paciente de prueba (403)", codigo == 403 and r.get("status") == "error", (codigo, r))
codigo, r = d.api("delete_cuenta.php", {"confirmar": True, "password": PASSWORD_DEMO})
check("demo: no se elimina la cuenta de prueba (403)", codigo == 403 and r.get("status") == "error", (codigo, r))
codigo, html = d.pagina("/public/historial.php")
check("historial del paciente de prueba con citas", codigo == 200 and "Reprogramar" in html and "Completada" in html, codigo)
codigo, r = Cliente().api("demo_reset.php", csrf=False)
check("reinicio sin secreto -> 401", codigo == 401, (codigo, r))

# === RESUMEN ===
fallas = [nombre for nombre, paso in resultados if not paso]
print(f"\nTOTAL: {len(resultados)} pruebas, {len(fallas)} fallas")
sys.exit(1 if fallas else 0)
