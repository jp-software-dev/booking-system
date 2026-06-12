// Previene el autocompletado del navegador eliminando valores cacheados al cargar vistas de autenticación
document.querySelectorAll('form[autocomplete="off"] input').forEach(input => input.setAttribute('autocomplete', 'new-password'));

// Bloquea el botón derecho y la selección de texto en áreas críticas del calendario para evitar interacciones accidentales o scrapeo
document.addEventListener('contextmenu', e => { if(e.target.closest('.agenda-grid, .admin-table')) e.preventDefault(); });

// Valida en tiempo real que los correos electrónicos no contengan scripts maliciosos inyectados
function sanitizeInput(val) { return val.replace(/[<>&"']/g, ''); }

// Función autoejecutable para la detección de herramientas de desarrollador (DevTools) mostrando alerta
(function detectDevTools() { let devToolsOpen = false; const element = new Image(); Object.defineProperty(element, 'id', { get: function() { devToolsOpen = true; return ''; } }); setInterval(() => { devToolsOpen = false; console.log(element); console.clear(); if(devToolsOpen) { alert('DevTools detectado'); } }, 1000); })();