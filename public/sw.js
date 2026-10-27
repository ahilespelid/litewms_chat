// Service worker чата: показывает push-уведомления. Работает только на полной странице /app.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    if (!event.data) return;

    let data = {};
    try {
        data = event.data.json();
    } catch (e) {
        data = { title: 'Новое сообщение', body: event.data.text() };
    }

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            // Если чат у человека открыт и на виду — уведомление только раздражает.
            if (windows.some((w) => w.visibilityState === 'visible' && w.url.includes('/app'))) return;

            return self.registration.showNotification(data.title || 'Новое сообщение', {
                body: data.body || '',
                data: { url: data.url || '/app' },
                tag: data.url || 'chat',
                renotify: true,
            });
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL(event.notification.data?.url || '/app', self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            for (const w of windows) {
                if (w.url.includes('/app') && 'focus' in w) {
                    w.navigate?.(url);
                    return w.focus();
                }
            }
            return self.clients.openWindow(url);
        })
    );
});
