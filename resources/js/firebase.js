const firebaseConfig = () => {
    const config = {
        apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
        projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
        messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
        appId: import.meta.env.VITE_FIREBASE_APP_ID,
    };

    return config.apiKey && config.projectId && config.messagingSenderId && config.appId ? config : null;
};

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const registerDeviceToken = async (messaging) => {
    try {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            return;
        }

        const token = await getToken(messaging, {
            vapidKey: import.meta.env.VITE_FIREBASE_VAPID_KEY,
        });

        if (!token) {
            return;
        }

        localStorage.setItem('fcm_token', token);

        await fetch('/push-subscriptions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ fcm_token: token, platform: 'android' }),
        });
    } catch (error) {
        console.error('FCM registration failed:', error);
    }
};

const cleanupTokenOnLogout = () => {
    document.addEventListener('submit', async (event) => {
        if (!(event.target instanceof HTMLFormElement)) {
            return;
        }

        if (!event.target.classList.contains('js-logout-form')) {
            return;
        }

        event.preventDefault();

        const token = localStorage.getItem('fcm_token');
        try {
            if (token) {
                await fetch('/push-subscriptions', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ fcm_token: token }),
                });
            }
        } catch (error) {
            console.error('FCM token cleanup failed:', error);
        } finally {
            localStorage.removeItem('fcm_token');
            event.target.submit();
        }
    });
};

const initFirebaseMessaging = async () => {
    const config = firebaseConfig();
    if (!config || !('serviceWorker' in navigator) || !('PushManager' in window)) {
        return;
    }

    try {
        const { initializeApp } = await import('firebase/app');
        const { getMessaging, getToken, onMessage, isSupported } = await import('firebase/messaging');

        if (!(await isSupported())) {
            return;
        }

        const app = initializeApp(config);
        const messaging = getMessaging(app);

        onMessage(messaging, (payload) => {
            const { title, body } = payload.notification ?? {};
            if (title) {
                new Notification(title, {
                    body: body ?? '',
                    icon: '/icons/icon-192x192.png',
                });
            }
        });

        await registerDeviceToken(messaging);
    } catch (error) {
        console.error('Firebase init failed:', error);
    }
};

window.addEventListener('DOMContentLoaded', () => {
    initFirebaseMessaging();
    cleanupTokenOnLogout();
});
