/**
 * Service Worker — CONTROLE DE CONTRATOS.
 * Recebe os eventos push e exibe a notificação nativa do navegador.
 *
 * @author João Pedro Castor Quirino
 */

self.addEventListener('push', function (event) {
    var data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: 'CONTROLE DE CONTRATOS', body: event.data ? event.data.text() : '' };
    }

    var title = data.title || 'CONTROLE DE CONTRATOS';
    var options = {
        body: data.body || '',
        icon: data.icon || undefined,
        badge: data.icon || undefined,
        data: { url: data.url || '/' },
        requireInteraction: false
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

// Ao clicar na notificação, foca/abre a aba do contrato.
self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var targetUrl = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if (client.url === targetUrl && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});
