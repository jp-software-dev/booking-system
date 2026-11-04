/**
 * MI SEMANA (tratamientos.php): TOMAS, RECORDATORIOS Y TRATAMIENTOS
 *
 * - Marcar una toma de hoy como tomada (o deshacer): el cambio se ve al
 *   instante y, si el servidor lo rechaza, se revierte. Cuando todas las tomas
 *   de un compartimento quedan tomadas, su tapa se levanta (firma visual).
 * - Agregar un recordatorio propio, con la vista previa de cuántas tomas tendrá
 *   y a qué horas (mismo cálculo que App\Models\Tratamiento::calcularTomas).
 * - Pausar, reanudar o eliminar un tratamiento.
 */
(() => {
    const dosDigitos = (n) => String(n).padStart(2, '0');
    const horaDe = (fecha) => `${dosDigitos(fecha.getHours())}:${dosDigitos(fecha.getMinutes())}`;
    const DIAS = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
    const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    // === MARCAR TOMAS DE HOY ===

    /** Recalcula el contador "N de M" y el estado de la tapa del compartimento. */
    function actualizarResumen(clave) {
        const lista = document.getElementById('listaHoy');
        if (lista) {
            const total = lista.querySelectorAll('[data-toma]').length;
            const tomadas = lista.querySelectorAll('[data-toma].tomada').length;
            document.getElementById('contadorHoy').textContent = `${tomadas} de ${total}`;
        }

        const compartimento = document.querySelector(`.compartimento[data-compartimento="${CSS.escape(clave)}"]`);
        if (!compartimento) return;
        const dosis = [...compartimento.querySelectorAll('[data-toma-dosis]')];
        const todasTomadas = dosis.length > 0 && dosis.every((d) => d.dataset.estado === 'Tomada');
        compartimento.classList.toggle('tomada', todasTomadas);
    }

    /** Refleja el estado de una toma en la lista de hoy y en el pastillero. */
    function aplicarEstado(item, estado) {
        const tomada = estado === 'Tomada';
        item.classList.toggle('tomada', tomada);
        const boton = item.querySelector('[data-marcar-toma]');
        boton.setAttribute('aria-pressed', String(tomada));
        boton.setAttribute('aria-label', boton.getAttribute('aria-label').replace(/^(Deshacer|Marcar como tomada): /, tomada ? 'Deshacer: ' : 'Marcar como tomada: '));

        const dosis = document.querySelector(`[data-toma-dosis="${item.dataset.toma}"]`);
        if (dosis) dosis.dataset.estado = estado;
        actualizarResumen(item.dataset.compartimento);
    }

    document.addEventListener('click', async (evento) => {
        const boton = evento.target.closest('[data-marcar-toma]');
        if (!boton) return;

        const item = boton.closest('[data-toma]');
        const anterior = item.classList.contains('tomada') ? 'Tomada' : 'Pendiente';
        const nuevo = anterior === 'Tomada' ? 'Pendiente' : 'Tomada';

        // Respuesta inmediata; se revierte si el servidor rechaza el cambio.
        aplicarEstado(item, nuevo);
        boton.classList.add('ma-pop');
        boton.addEventListener('animationend', () => boton.classList.remove('ma-pop'), { once: true });
        boton.disabled = true;

        const respuesta = await apiClient.post('registrar_toma.php', { id_toma: Number(boton.dataset.marcarToma), estado: nuevo });
        boton.disabled = false;

        if (respuesta?.status === 'success') {
            notificar('exito', respuesta.message, 2500);
        } else {
            aplicarEstado(item, anterior);
            notificar('error', mensajeDeError(respuesta, 'No se pudo registrar la toma'));
        }
    });

    // === AGREGAR RECORDATORIO ===

    const formulario = document.getElementById('formRecordatorio');
    if (formulario) {
        const modal = document.getElementById('modalRecordatorio');
        const vistaPrevia = document.getElementById('recordatorioVistaPrevia');
        const campo = (nombre) => formulario.elements[nombre];

        const valorLocal = (fecha) => `${fecha.getFullYear()}-${dosDigitos(fecha.getMonth() + 1)}-${dosDigitos(fecha.getDate())}T${horaDe(fecha)}`;

        function actualizarVistaPrevia() {
            const frecuencia = Number(campo('frecuencia_horas').value);
            const dias = Number(campo('duracion_dias').value);
            const inicio = new Date(campo('fecha_inicio').value);
            if (!frecuencia || !dias || dias < 1 || Number.isNaN(inicio.getTime())) {
                vistaPrevia.textContent = '';
                return;
            }

            const total = Math.ceil((dias * 24) / frecuencia);
            const horas = [];
            for (let i = 0; i < Math.min(total, Math.ceil(24 / frecuencia)); i++) {
                horas.push(horaDe(new Date(inicio.getTime() + i * frecuencia * 3600000)));
            }
            const fin = new Date(inicio.getTime() + dias * 86400000);
            const lista = horas.length <= 1 ? horas.join('') : `${horas.slice(0, -1).join(', ')} y ${horas.at(-1)}`;
            vistaPrevia.textContent = `${total} tomas · a las ${lista} · termina el ${DIAS[fin.getDay()]} ${fin.getDate()} ${MESES[fin.getMonth()]}`;
        }

        modal.addEventListener('show.bs.modal', () => {
            formulario.reset();
            limpiarAlerta('recordatorioAlerta');
            const siguienteHora = new Date();
            siguienteHora.setMinutes(0, 0, 0);
            siguienteHora.setHours(siguienteHora.getHours() + 1);
            campo('fecha_inicio').value = valorLocal(siguienteHora);
            actualizarVistaPrevia();
        });
        modal.addEventListener('shown.bs.modal', () => campo('medicamento').focus());
        formulario.addEventListener('input', actualizarVistaPrevia);

        formulario.addEventListener('submit', async (evento) => {
            evento.preventDefault();
            limpiarAlerta('recordatorioAlerta');

            const restaurar = bloquearBoton(document.getElementById('btnGuardarRecordatorio'), 'Guardando...');
            const respuesta = await apiClient.post('crear_tratamiento.php', Object.fromEntries(new FormData(formulario)));

            if (respuesta?.status === 'success') {
                bootstrap.Modal.getOrCreateInstance(modal).hide();
                notificar('exito', respuesta.message);
                setTimeout(() => location.reload(), 900);
                return;
            }

            restaurar();
            mostrarAlerta('recordatorioAlerta', 'danger', mensajeDeError(respuesta, 'No se pudo crear el recordatorio'));
        });
    }

    // === PAUSAR, REANUDAR O ELIMINAR UN TRATAMIENTO ===

    document.addEventListener('click', async (evento) => {
        const boton = evento.target.closest('[data-accion-tratamiento]');
        if (!boton) return;

        const accion = boton.dataset.accionTratamiento;
        if (accion === 'eliminar' && !confirm(`¿Eliminar el recordatorio de ${boton.dataset.nombre}? Dejarás de ver sus tomas.`)) {
            return;
        }

        const respuesta = await apiClient.post('actualizar_tratamiento.php', { id_tratamiento: Number(boton.dataset.id), accion });
        if (respuesta?.status === 'success') {
            notificar('exito', respuesta.message);
            setTimeout(() => location.reload(), 900);
        } else {
            notificar('error', mensajeDeError(respuesta));
        }
    });
})();

/**
 * AVISOS PUSH DE LAS TOMAS
 *
 * Muestra el aviso #avisoNotificaciones según el estado del navegador:
 *   - sin activar: botón "Activar avisos" (pide permiso, suscribe y manda una prueba);
 *   - activos: "Enviar prueba" y "Desactivar";
 *   - bloqueados: cómo volver a permitirlos;
 *   - iPhone sin instalar: iOS solo permite avisos en apps agregadas a la pantalla de inicio.
 */
(() => {
    const aviso = document.getElementById('avisoNotificaciones');
    const clave = document.querySelector('meta[name="vapid-public-key"]')?.content;
    if (!aviso || !clave) return;

    const texto = document.getElementById('avisoNotificacionesTexto');
    const acciones = document.getElementById('avisoNotificacionesAcciones');
    const soporta = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    const esIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
    const instalada = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    let registro = null;

    /** Clave VAPID en base64url a los bytes que pide PushManager.subscribe(). */
    function claveEnBytes(base64Url) {
        const relleno = '='.repeat((4 - (base64Url.length % 4)) % 4);
        const binario = atob((base64Url + relleno).replace(/-/g, '+').replace(/_/g, '/'));
        return Uint8Array.from(binario, (c) => c.charCodeAt(0));
    }

    /** Crea un botón para la zona de acciones del aviso. */
    function boton(etiqueta, clase, alHacerClic) {
        const nuevo = document.createElement('button');
        nuevo.type = 'button';
        nuevo.className = `btn ${clase}`;
        nuevo.textContent = etiqueta;
        nuevo.addEventListener('click', () => alHacerClic(nuevo));
        return nuevo;
    }

    /** Muestra el aviso con su texto y sus botones. */
    function mostrar(mensaje, botones = []) {
        texto.textContent = mensaje;
        acciones.replaceChildren(...botones);
        aviso.classList.remove('d-none');
        aviso.classList.add('d-flex');
    }

    async function enviarPrueba(elemento) {
        const restaurar = elemento ? bloquearBoton(elemento, 'Enviando...') : () => {};
        const respuesta = await apiClient.post('push_prueba.php', {});
        restaurar();
        notificar(respuesta?.status === 'success' ? 'exito' : 'error', mensajeDeError(respuesta, respuesta?.message || 'No se pudo enviar la prueba'));
    }

    async function activar(elemento) {
        const restaurar = bloquearBoton(elemento, 'Activando...');
        const permiso = await Notification.requestPermission();
        if (permiso !== 'granted') {
            restaurar();
            await revisar();
            return;
        }

        try {
            const suscripcion = await registro.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: claveEnBytes(clave) });
            const respuesta = await apiClient.post('push_suscribir.php', suscripcion.toJSON());
            if (respuesta?.status !== 'success') throw new Error(mensajeDeError(respuesta));
            notificar('exito', 'Avisos activados. Te enviamos uno de prueba.');
            await revisar();
            enviarPrueba(null);
        } catch (error) {
            restaurar();
            notificar('error', error.message || 'No se pudieron activar los avisos');
        }
    }

    async function desactivar(elemento) {
        const restaurar = bloquearBoton(elemento, 'Desactivando...');
        const suscripcion = await registro.pushManager.getSubscription();
        if (suscripcion) {
            await apiClient.post('push_desuscribir.php', { endpoint: suscripcion.endpoint });
            await suscripcion.unsubscribe();
        }
        restaurar();
        notificar('info', 'Avisos desactivados en este navegador');
        await revisar();
    }

    /** Revisa el estado actual y dibuja el aviso que corresponde. */
    async function revisar() {
        if (Notification.permission === 'denied') {
            mostrar('Bloqueaste las notificaciones de este sitio. Permítelas desde el candado de la barra de direcciones y recarga la página.');
            return;
        }
        const suscripcion = await registro.pushManager.getSubscription();
        if (suscripcion) {
            mostrar('Los avisos están activos en este navegador.', [
                boton('Enviar prueba', 'btn-light', enviarPrueba),
                boton('Desactivar', 'btn-outline-secondary', desactivar),
            ]);
            return;
        }
        mostrar('Aunque tengas MediAgenda cerrada, en este navegador o en tu celular.', [boton('Activar avisos', 'btn-primary', activar)]);
    }

    if (!soporta) {
        if (esIOS && !instalada) {
            mostrar('En iPhone: toca Compartir y luego "Agregar a inicio". Abre MediAgenda desde ese ícono y activa los avisos ahí.');
        }
        return;
    }

    navigator.serviceWorker.register('sw.js').then((r) => {
        registro = r;
        return revisar();
    }).catch(() => { /* Sin service worker (por ejemplo, sin HTTPS): no se ofrecen avisos. */ });
})();
