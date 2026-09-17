self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
  const payload = event.data ? event.data.json() : {};
  const data = payload.data || {};

  event.waitUntil((async () => {
    const clientsList = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    clientsList.forEach((client) => {
      client.postMessage({ type: 'checkin-push', payload });
    });

    await self.registration.showNotification(payload.title || 'Welcome', {
      body: payload.body || 'Checked in successfully',
      icon: payload.icon || undefined,
      badge: payload.badge || payload.icon || undefined,
      image: payload.image || undefined,
      tag: payload.tag || 'fitgen-checkin',
      renotify: payload.renotify !== false,
      requireInteraction: payload.requireInteraction !== false,
      vibrate: payload.vibrate || [120, 60, 120],
      data: data,
    });
  })());
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = (event.notification.data && event.notification.data.url) || './';

  event.waitUntil((async () => {
    const clientsList = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (let i = 0; i < clientsList.length; i++) {
      const client = clientsList[i];
      if ('focus' in client) {
        await client.focus();
        if (url && 'navigate' in client) {
          try { await client.navigate(url); } catch (_) {}
        }
        return;
      }
    }
    await self.clients.openWindow(url);
  })());
});
