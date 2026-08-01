import { precacheAndRoute } from 'workbox-precaching';
import { initializeApp } from 'firebase/app';
import { getMessaging, onBackgroundMessage } from 'firebase/messaging/sw';

precacheAndRoute(self.__WB_MANIFEST);

initializeApp({
    apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
    projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
    messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
    appId: import.meta.env.VITE_FIREBASE_APP_ID,
});

const messaging = getMessaging();

onBackgroundMessage(messaging, (payload) => {
    const { title, body } = payload.notification ?? {};

    self.registration.showNotification(title ?? 'Hydro Farm', {
        body: body ?? '',
        icon: '/icons/icon-192x192.png',
        badge: '/icons/icon-192x192.png',
        data: { url: payload.data?.url },
    });
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url ?? '/';
    event.waitUntil(clients.openWindow(url));
});
