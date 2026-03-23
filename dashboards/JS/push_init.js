// dashboards/JS/push_init.js
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
        .replace(/\-/g, '+')
        .replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

if ('serviceWorker' in navigator && 'PushManager' in window) {
    window.addEventListener('load', async function() {
        try {
            const registration = await navigator.serviceWorker.register('/sw.js');
            
            // Prompt for notification permission
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                console.warn('Push notification permission denied.');
                return;
            }
            
            // Get public VAPID key
            const vapidResponse = await fetch('/Backend/Notifications/vapid_public.php');
            if (!vapidResponse.ok) throw new Error('Failed to fetch VAPID key');
            const vapidData = await vapidResponse.json();
            
            const convertedVapidKey = urlBase64ToUint8Array(vapidData.publicKey);
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: convertedVapidKey
            });
            
            // Send subscription to backend
            await fetch('/Backend/Notifications/save_subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(subscription)
            });
            
        } catch (error) {
            console.error('Service Worker or Push Error', error);
        }
    });
}
