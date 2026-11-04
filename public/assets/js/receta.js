/**
 * RECETAS: EMITIR (DOCTOR) Y VER EL DETALLE (DOCTOR, PACIENTE Y ADMINISTRACIÓN)
 *
 * - Botones con data-recetar="<id_cita>" abren el modal #modalEmitirReceta
 *   (parcial modal_emitir_receta.php). Cada medicamento muestra en vivo cuántas
 *   tomas tendrá, a qué horas y cuándo termina, con el mismo cálculo que el
 *   servidor (App\Models\Tratamiento::calcularTomas).
 * - Botones con data-ver-receta="<id_receta>" abren #modalDetalleReceta
 *   (parcial modal_detalle_receta.php) y cargan la receta desde api/get_receta.php.
 *
 * Todo el texto que viene del servidor se inserta con textContent (nunca como HTML).
 */
(() => {
    const DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    // === UTILIDADES ===

    /** Crea un elemento con clase y texto opcionales. */
    function el(etiqueta, clase = '', texto = '') {
        const nodo = document.createElement(etiqueta);
        if (clase) nodo.className = clase;
        if (texto !== '') nodo.textContent = texto;
        return nodo;
    }

    const dosDigitos = (n) => String(n).padStart(2, '0');
    const horaDe = (fecha) => `${dosDigitos(fecha.getHours())}:${dosDigitos(fecha.getMinutes())}`;
    const fechaCorta = (fecha) => `${DIAS[fecha.getDay()].slice(0, 3)} ${fecha.getDate()} ${MESES[fecha.getMonth()]}`;

    /** Fecha local en el formato de <input type="datetime-local">. */
    function valorLocal(fecha) {
        return `${fecha.getFullYear()}-${dosDigitos(fecha.getMonth() + 1)}-${dosDigitos(fecha.getDate())}T${horaDe(fecha)}`;
    }

    /** Fecha "AAAA-MM-DD HH:MM:SS" del servidor a Date local. */
    const desdeServidor = (texto) => new Date(String(texto).replace(' ', 'T'));

    /** Texto de una frecuencia, igual que Tratamiento::describirFrecuencia(). */
    function describirFrecuencia(horas) {
        if (horas === 24) return 'una vez al día';
        if (horas === 1) return 'cada hora';
        return `cada ${horas} horas`;
    }

    /** Une una lista en español: "a, b y c". */
    function unirLista(items) {
        return items.length <= 1 ? items.join('') : `${items.slice(0, -1).join(', ')} y ${items.at(-1)}`;
    }

    // === EMITIR RECETA (DOCTOR) ===

    const modalEmitir = document.getElementById('modalEmitirReceta');
    const formulario = document.getElementById('formEmitirReceta');
    const lista = document.getElementById('listaMedicamentos');
    const plantilla = document.getElementById('plantillaMedicamento');
    let botonOrigen = null;

    /** Describe el cronograma de un medicamento a partir de sus campos. */
    function actualizarVistaPrevia(fieldset) {
        const frecuencia = Number(fieldset.querySelector('[data-campo="frecuencia_horas"]').value);
        const dias = Number(fieldset.querySelector('[data-campo="duracion_dias"]').value);
        const inicio = new Date(fieldset.querySelector('[data-campo="fecha_inicio"]').value);
        const salida = fieldset.querySelector('[data-vista-previa]');

        if (!frecuencia || !dias || dias < 1 || Number.isNaN(inicio.getTime())) {
            salida.textContent = 'Completa frecuencia, duración y primera toma para ver el cronograma.';
            return;
        }

        const total = Math.ceil((dias * 24) / frecuencia);
        const horasDelDia = [];
        for (let i = 0; i < Math.min(total, Math.ceil(24 / frecuencia)); i++) {
            horasDelDia.push(horaDe(new Date(inicio.getTime() + i * frecuencia * 3600000)));
        }
        const fin = new Date(inicio.getTime() + dias * 86400000);
        salida.textContent = `${total} tomas · a las ${unirLista(horasDelDia)} · termina el ${fechaCorta(fin)}`;
    }

    /** Agrega un medicamento vacío con la primera toma en la siguiente hora en punto. */
    function agregarMedicamento() {
        const fieldset = plantilla.content.firstElementChild.cloneNode(true);
        const siguienteHora = new Date();
        siguienteHora.setMinutes(0, 0, 0);
        siguienteHora.setHours(siguienteHora.getHours() + 1);
        fieldset.querySelector('[data-campo="fecha_inicio"]').value = valorLocal(siguienteHora);

        fieldset.addEventListener('input', () => actualizarVistaPrevia(fieldset));
        fieldset.querySelector('[data-quitar-medicamento]').addEventListener('click', () => {
            if (lista.children.length > 1) {
                fieldset.remove();
                actualizarQuitar();
            }
        });

        lista.appendChild(fieldset);
        fieldset.animate([{ opacity: 0, transform: 'translateY(6px)' }, { opacity: 1, transform: 'none' }], { duration: 260, easing: 'cubic-bezier(0.16, 1, 0.3, 1)' });
        actualizarVistaPrevia(fieldset);
        actualizarQuitar();
        fieldset.querySelector('[data-campo="medicamento"]').focus();
    }

    /** Con un solo medicamento no se muestra "Quitar". */
    function actualizarQuitar() {
        lista.querySelectorAll('[data-quitar-medicamento]').forEach((boton) => {
            boton.hidden = lista.children.length === 1;
        });
    }

    if (modalEmitir && formulario) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEmitir);
        document.getElementById('btnAgregarMedicamento').addEventListener('click', agregarMedicamento);

        document.addEventListener('click', (evento) => {
            const boton = evento.target.closest('[data-recetar]');
            if (!boton) return;

            botonOrigen = boton;
            formulario.reset();
            limpiarAlerta('recetaAlerta');
            lista.replaceChildren();
            document.getElementById('recetaIdCita').value = boton.dataset.recetar;
            document.getElementById('recetaPaciente').textContent = `${boton.dataset.paciente} · ${boton.dataset.motivo}`;
            agregarMedicamento();
            modal.show();
        });

        modalEmitir.addEventListener('shown.bs.modal', () => document.getElementById('recetaDiagnostico').focus());

        formulario.addEventListener('submit', async (evento) => {
            evento.preventDefault();
            limpiarAlerta('recetaAlerta');

            const medicamentos = [...lista.children].map((fieldset) => {
                const datos = {};
                fieldset.querySelectorAll('[data-campo]').forEach((campo) => { datos[campo.dataset.campo] = campo.value.trim(); });
                return datos;
            });

            const restaurar = bloquearBoton(document.getElementById('btnEmitirReceta'), 'Emitiendo...');
            const respuesta = await apiClient.post('emitir_receta.php', {
                id_cita: document.getElementById('recetaIdCita').value,
                diagnostico: document.getElementById('recetaDiagnostico').value.trim(),
                indicaciones: document.getElementById('recetaIndicaciones').value.trim(),
                medicamentos,
            });
            restaurar();

            if (respuesta?.status !== 'success') {
                mostrarAlerta('recetaAlerta', 'danger', mensajeDeError(respuesta, 'No se pudo emitir la receta'));
                modalEmitir.querySelector('.modal-body').scrollTo({ top: 0, behavior: 'smooth' });
                return;
            }

            modal.hide();
            notificar('exito', respuesta.message);

            // El botón "Recetar" se convierte en "Ver receta".
            if (botonOrigen) {
                const verReceta = el('button', 'btn btn-sm btn-light ma-pop');
                verReceta.type = 'button';
                verReceta.dataset.verReceta = respuesta.id_receta;
                verReceta.innerHTML = '<i class="bi bi-file-earmark-medical me-1" aria-hidden="true"></i>';
                verReceta.append('Ver receta');
                botonOrigen.replaceWith(verReceta);
            }
        });
    }

    // === DETALLE DE UNA RECETA ===

    const modalDetalle = document.getElementById('modalDetalleReceta');
    if (!modalDetalle) return;

    const cuerpo = document.getElementById('detalleRecetaCuerpo');
    const subtitulo = document.getElementById('detalleRecetaSubtitulo');
    const enlacePdf = document.getElementById('detalleRecetaPdf');

    /** Esqueleto de carga con la forma del contenido que llegará. */
    function mostrarEsqueleto() {
        cuerpo.replaceChildren();
        [['38%', '1.1rem'], ['100%', '3rem'], ['100%', '4.2rem'], ['100%', '4.2rem'], ['70%', '2.4rem']].forEach(([ancho, alto]) => {
            const bloque = el('div', 'ma-esqueleto mb-3');
            bloque.style.width = ancho;
            bloque.style.height = alto;
            cuerpo.appendChild(bloque);
        });
    }

    /** Dibuja la receta con sus medicamentos y avance. */
    function pintarReceta(receta) {
        const emision = desdeServidor(receta.fecha_emision);
        subtitulo.textContent = `${receta.folio} · ${fechaCorta(emision)} ${emision.getFullYear()} · ${horaDe(emision)}`;
        enlacePdf.href = `../api/generar_receta_pdf.php?id_receta=${encodeURIComponent(receta.id_receta)}`;

        const fragmento = document.createDocumentFragment();

        const personas = el('div', 'row g-3 mb-3');
        [['Paciente', `${receta.paciente_nombre} ${receta.paciente_apellido}`], ['Médico', `Dr(a). ${receta.doctor_nombre} ${receta.doctor_apellido}${receta.nombre_especialidad ? ' · ' + receta.nombre_especialidad : ''}`]]
            .forEach(([etiqueta, valor]) => {
                const col = el('div', 'col-sm-6');
                col.append(el('div', 'small fw-bold text-secondary', etiqueta), el('div', 'fw-bold', valor));
                personas.appendChild(col);
            });
        fragmento.appendChild(personas);

        const diagnostico = el('div', 'rounded-4 p-3 mb-4');
        diagnostico.style.background = 'var(--ma-superficie-2)';
        diagnostico.append(el('div', 'small fw-bold text-secondary', 'Diagnóstico'), el('div', 'fw-bold', receta.diagnostico));
        fragmento.appendChild(diagnostico);

        fragmento.appendChild(el('h3', 'fs-6 fw-bold mb-3', 'Medicamentos'));
        const maxDias = Math.max(...receta.medicamentos.map((m) => Number(m.duracion_dias)), 1);

        receta.medicamentos.forEach((medicamento, indice) => {
            const inicio = desdeServidor(medicamento.fecha_inicio);
            const fila = el('div', 'mb-3');

            const cabecera = el('div', 'd-flex justify-content-between align-items-start gap-2 mb-1');
            const nombre = el('div');
            nombre.append(el('div', 'fw-bold', `${medicamento.medicamento} · ${medicamento.dosis}`),
                el('div', 'small text-secondary', `${describirFrecuencia(Number(medicamento.frecuencia_horas))} · ${medicamento.duracion_dias} día(s) · desde ${fechaCorta(inicio)} ${horaDe(inicio)}`));
            cabecera.appendChild(nombre);
            if (medicamento.apego !== null) {
                cabecera.appendChild(el('span', `ma-chip ${medicamento.apego >= 80 ? 'exito' : 'aviso'} flex-none`, `${medicamento.apego} % de apego`));
            }
            fila.appendChild(cabecera);

            // La barra mide exactamente los días del tratamiento; el relleno, las tomas registradas.
            const momento = ['manana', 'tarde', 'noche'][indice % 3];
            const barra = el('div', `tratamiento-barra ${momento}`);
            barra.style.width = `${(Number(medicamento.duracion_dias) / maxDias) * 100}%`;
            barra.setAttribute('role', 'img');
            barra.setAttribute('aria-label', `${medicamento.tomadas} de ${medicamento.total_tomas} tomas registradas como tomadas`);
            const relleno = el('i');
            relleno.style.width = `${medicamento.total_tomas ? (medicamento.tomadas / medicamento.total_tomas) * 100 : 0}%`;
            barra.appendChild(relleno);
            fila.appendChild(barra);
            fila.appendChild(el('div', 'small text-secondary mt-1 ma-cifras', `${medicamento.tomadas} de ${medicamento.total_tomas} tomas tomadas${medicamento.omitidas ? ` · ${medicamento.omitidas} omitida(s)` : ''}`));
            if (medicamento.notas) fila.appendChild(el('div', 'small fst-italic text-secondary', medicamento.notas));
            fragmento.appendChild(fila);
        });

        if (receta.indicaciones) {
            const indicaciones = el('div', 'mt-4');
            indicaciones.append(el('div', 'small fw-bold text-secondary mb-1', 'Indicaciones'));
            const texto = el('p', 'mb-0', receta.indicaciones);
            texto.style.whiteSpace = 'pre-line';
            indicaciones.appendChild(texto);
            fragmento.appendChild(indicaciones);
        }

        cuerpo.replaceChildren(fragmento);
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalDetalle);
    document.addEventListener('click', async (evento) => {
        const boton = evento.target.closest('[data-ver-receta]');
        if (!boton) return;

        subtitulo.textContent = '';
        enlacePdf.href = '#';
        mostrarEsqueleto();
        modal.show();

        const respuesta = await apiClient.get(`get_receta.php?id_receta=${encodeURIComponent(boton.dataset.verReceta)}`);
        if (respuesta?.status === 'success') {
            pintarReceta(respuesta.receta);
        } else {
            cuerpo.replaceChildren();
            mostrarAlerta(cuerpo, 'danger', mensajeDeError(respuesta, 'No se pudo cargar la receta'));
        }
    });
})();
