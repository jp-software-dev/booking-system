/**
 * MOSTRAR / OCULTAR CONTRASEÑA
 *
 * Cualquier botón con [data-ver-password] alterna entre ocultar y mostrar el
 * campo de contraseña que tiene justo antes dentro de su .input-group.
 * Mantiene el icono, el texto accesible y aria-pressed al día.
 */
document.querySelectorAll('[data-ver-password]').forEach((boton) => {
    const campo = boton.parentElement.querySelector('input');
    const icono = boton.querySelector('i');
    if (!campo) {
        return;
    }

    boton.addEventListener('click', () => {
        const visible = campo.type === 'password';
        campo.type = visible ? 'text' : 'password';
        icono.classList.toggle('bi-eye', !visible);
        icono.classList.toggle('bi-eye-slash', visible);
        boton.setAttribute('aria-pressed', visible ? 'true' : 'false');
        boton.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
    });
});
