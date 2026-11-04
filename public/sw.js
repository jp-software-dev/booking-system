/**
 * SERVICE WORKER DE MEDIAGENDA
 *
 * Recibe los avisos push del servidor (App\Services\NotificadorPush) y los
 * muestra como notificación aunque MediAgenda esté cerrada. Al tocar la
 * notificación abre (o enfoca) "Mi semana".
 *
 * Vive en /public/sw.js para que su alcance cubra todas las páginas de /public/.
 * No guarda nada en caché: la app siempre se carga desde el servidor.
 */

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (evento) => evento.waitUntil(self.clients.claim()));

self.addEventListener('push', (evento) => {
    let aviso = { titulo: 'MediAgenda', cuerpo: 'Tienes un recordatorio pendiente.', url: 'tratamientos.php', etiqueta: 'mediagenda' };
    try {
        aviso = { ...aviso, ...evento.data.json() };
    } catch (error) {
        // Sin contenido o con un formato inesperado: se usa el aviso genérico.
    }

    evento.waitUntil(self.registration.showNotification(aviso.titulo, {
        body: aviso.cuerpo,
        icon: 'assets/images/icono-192.png',
        badge: 'assets/images/favicon-32.png',
        tag: aviso.etiqueta,
        renotify: true,
        data: { url: aviso.url },
    }));
});

self.addEventListener('notificationclick', (evento) => {
    evento.notification.close();
    const destino = new URL(evento.notification.data?.url || 'tratamientos.php', self.registration.scope).href;

    evento.waitUntil((async () => {
        const ventanas = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const ventana of ventanas) {
            if (ventana.url.startsWith(self.registration.scope)) {
                await ventana.focus();
                return ventana.navigate(destino);
            }
        }
        return self.clients.openWindow(destino);
    })());
});
