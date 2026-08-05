const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const pushEndpoint = () => {
    if (window.location.pathname.startsWith('/staff')) {
        return '/staff/push-subscriptions';
    }
    return '/push-subscriptions';
};

const registerNativeToken = async (PushNotifications) => {
    try {
        const permission = await PushNotifications.requestPermissions();

        if (permission.receive !== 'granted') {
            return;
        }

        await PushNotifications.register();
    } catch (error) {
        console.error('FCM native registration failed:', error);
    }
};

const setupNativePush = async () => {
    try {
        const { PushNotifications } = await import('@capacitor/push-notifications');

        PushNotifications.addListener('registration', async ({ value }) => {
            const endpoint = pushEndpoint();
            const token = value;
            localStorage.setItem('fcm_token', token);

            try {
                await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ fcm_token: token, platform: 'android' }),
                });
            } catch (error) {
                console.error('FCM token registration failed:', error);
            }
        });

        PushNotifications.addListener('registrationError', ({ error }) => {
            console.error('FCM registration error:', error);
        });

        PushNotifications.addListener('pushNotificationReceived', ({ notification }) => {
            if (notification.data?.url) {
                console.log('Push received:', notification.title, notification.data.url);
            }
        });

        await registerNativeToken(PushNotifications);
    } catch (error) {
        console.error('Capacitor push notifications init failed:', error);
    }
};

const cleanupNativeTokenOnLogout = (PushNotifications) => {
    document.addEventListener('submit', async (event) => {
        if (!(event.target instanceof HTMLFormElement)) {
            return;
        }

        if (!event.target.classList.contains('js-logout-form')) {
            return;
        }

        event.preventDefault();

        const token = localStorage.getItem('fcm_token');
        const endpoint = pushEndpoint();

        try {
            if (token) {
                await fetch(endpoint, {
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
            try {
                await PushNotifications.unregister();
            } catch {
                // ignore
            }
            event.target.submit();
        }
    });
};

window.addEventListener('DOMContentLoaded', () => {
    if (window.Capacitor?.isNative) {
        setupNativePush();
        import('@capacitor/push-notifications').then(({ PushNotifications }) => {
            cleanupNativeTokenOnLogout(PushNotifications);
        });
    }
});