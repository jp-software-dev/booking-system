// Previene el autocompletado del navegador eliminando valores cacheados al cargar vistas de autenticación
document.querySelectorAll('form[autocomplete="off"] input').forEach(input => input.setAttribute('autocomplete', 'new-password'));
