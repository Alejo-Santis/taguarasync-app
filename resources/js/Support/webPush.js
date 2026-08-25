const getCsrfToken = () => {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
};

const urlBase64ToUint8Array = (base64String) => {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);

    return Uint8Array.from([...rawData].map((char) => char.charCodeAt(0)));
};

export const isPushSupported = () =>
    typeof window !== 'undefined' && typeof navigator !== 'undefined' && 'serviceWorker' in navigator && 'PushManager' in window;

export const getCurrentPushSubscription = async () => {
    if (!isPushSupported()) {
        return null;
    }

    const registration = await navigator.serviceWorker.getRegistration('/sw.js');

    return registration ? registration.pushManager.getSubscription() : null;
};

export const subscribeToPush = async (vapidPublicKey) => {
    if (!isPushSupported() || !vapidPublicKey) {
        throw new Error('Este navegador no soporta notificaciones push.');
    }

    const permission = await Notification.requestPermission();

    if (permission !== 'granted') {
        throw new Error('Permiso de notificaciones denegado.');
    }

    const registration = await navigator.serviceWorker.register('/sw.js');
    const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
    });

    const payload = subscription.toJSON();

    await fetch('/push-subscriptions', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify(payload),
    });

    return subscription;
};

export const unsubscribeFromPush = async () => {
    const subscription = await getCurrentPushSubscription();

    if (!subscription) {
        return;
    }

    const endpoint = subscription.endpoint;
    await subscription.unsubscribe();

    await fetch('/push-subscriptions', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({ endpoint }),
    });
};
