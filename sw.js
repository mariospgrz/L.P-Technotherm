/* sw.js */
self.addEventListener('push', function(event) {
    if (event.data) {
        try {
            const data = event.data.json();
            const title = data.title || 'Ειδοποίηση LP Technotherm';
            const options = {
                body: data.body,
                icon: data.icon || '/frontend/images/images.jpg',
                badge: '/frontend/images/images.jpg',
                data: {
                    url: data.url || '/'
                }
            };
            event.waitUntil(self.registration.showNotification(title, options));
        } catch (e) {
            console.error('Error parsing push data', e);
        }
    }
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    if (event.notification.data && event.notification.data.url) {
        event.waitUntil(
            clients.openWindow(event.notification.data.url)
        );
    }
});
