/**
 * CLIENTE DE LA API Y UTILIDADES DE INTERFAZ
 *
 * Se carga en todas las páginas desde el layout (footer.php).
 *
 * - apiClient: funciones para llamar a los endpoints de /api. Las peticiones
 *   POST envían el token CSRF en la cabecera X-CSRF-Token.
 * - mostrarAlerta / limpiarAlerta: avisos de Bootstrap con texto seguro.
 * - bloquearBoton: estado "procesando" de los botones de envío.
 *
 * Todos los endpoints responden JSON con la forma { status, message, ... }
 * incluso cuando el código HTTP es de error (400, 403, 409...), así que la
 * respuesta se lee siempre para mostrar el motivo real al usuario.
 */

const API_URL = '../api/';
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

/** Mensaje para errores de red o respuestas que no son JSON. */
const MENSAJE_ERROR_CONEXION = 'Error de conexión. Intenta de nuevo.';

const apiClient = {
    /**
     * Hace una petición GET.
     *
     * @param {string} endpoint Archivo de /api con su query string (ej. 'get_citas.php').
     * @returns {Promise<object|null>} JSON de la respuesta, o null si falló la conexión.
     */
    async get(endpoint) {
        return apiClient.enviar(endpoint, { method: 'GET' });
    },

    /**
     * Envía un objeto como JSON por POST, con el token CSRF.
     *
     * @param {string} endpoint Archivo de /api (ej. 'create_cita.php').
     * @param {object} datos    Datos a enviar.
     * @returns {Promise<object|null>} JSON de la respuesta, o null si falló la conexión.
     */
    async post(endpoint, datos) {
        return apiClient.enviar(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            body: JSON.stringify(datos),
        });
    },

    /**
     * Envía un formulario (FormData) por POST, con el token CSRF.
     *
     * @param {string}   endpoint Archivo de /api.
     * @param {FormData} formData Campos del formulario.
     * @returns {Promise<object|null>} JSON de la respuesta, o null si falló la conexión.
     */
    async postFormulario(endpoint, formData) {
        return apiClient.enviar(endpoint, {
            method: 'POST',
            headers: { 'X-CSRF-Token': CSRF_TOKEN },
            body: formData,
        });
    },

    /**
     * Ejecuta la petición y decodifica el JSON. Uso interno.
     *
     * @param {string}      endpoint Archivo de /api.
     * @param {RequestInit} opciones Opciones de fetch().
     * @returns {Promise<object|null>}
     */
    async enviar(endpoint, opciones) {
        try {
            const respuesta = await fetch(`${API_URL}${endpoint}`, opciones);
            return await respuesta.json();
        } catch (error) {
            console.error(`Error al llamar a ${endpoint}:`, error);
            return null;
        }
    },
};

/**
 * Muestra un aviso de Bootstrap dentro de un contenedor, reemplazando el anterior.
 * El mensaje se inserta como texto (no como HTML) para evitar inyección de código.
 *
 * @param {string|HTMLElement} contenedor Id del contenedor o el elemento.
 * @param {string}             tipo       success, danger, warning o info.
 * @param {string}             mensaje    Texto a mostrar.
 */
function mostrarAlerta(contenedor, tipo, mensaje) {
    const elemento = typeof contenedor === 'string' ? document.getElementById(contenedor) : contenedor;
    if (!elemento) {
        return;
    }

    const alerta = document.createElement('div');
    alerta.className = `alert alert-${tipo} py-2 small`;
    alerta.setAttribute('role', 'alert');
    alerta.textContent = mensaje;
    elemento.replaceChildren(alerta);
}

/**
 * Quita el aviso de un contenedor.
 *
 * @param {string|HTMLElement} contenedor Id del contenedor o el elemento.
 */
function limpiarAlerta(contenedor) {
    const elemento = typeof contenedor === 'string' ? document.getElementById(contenedor) : contenedor;
    elemento?.replaceChildren();
}

/**
 * Deshabilita un botón y muestra un texto de "procesando".
 *
 * @param {HTMLButtonElement} boton        Botón a bloquear.
 * @param {string}            textoProceso Texto mientras se procesa (ej. 'Guardando...').
 * @returns {Function} Función que restaura el botón a su estado original.
 */
function bloquearBoton(boton, textoProceso) {
    const contenidoOriginal = boton.innerHTML;
    boton.disabled = true;
    boton.textContent = textoProceso;

    return () => {
        boton.disabled = false;
        boton.innerHTML = contenidoOriginal;
    };
}

/**
 * Mensaje de error de una respuesta de la API, o el de conexión si no hubo respuesta.
 *
 * @param {object|null} respuesta Respuesta de apiClient.
 * @param {string}      [porDefecto] Mensaje si la respuesta no trae uno.
 * @returns {string}
 */
function mensajeDeError(respuesta, porDefecto = MENSAJE_ERROR_CONEXION) {
    return respuesta?.message || porDefecto;
}
