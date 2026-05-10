// Globale App-Logik – JuMa Tirol

// Service Worker registrieren
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch((err) => {
        console.error('Service Worker Registrierung fehlgeschlagen:', err);
    });

    // Nachrichten vom SW empfangen (z.B. Sync-Trigger)
    navigator.serviceWorker.addEventListener('message', (event) => {
        if (event.data?.type === 'SW_SYNC') {
            window.dispatchEvent(new CustomEvent('wt:sync-trigger'));
        }
    });
}

// Einfacher API-Client
export async function apiFetch(url, options = {}) {
    const defaults = {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
    };
    const response = await fetch(url, { ...defaults, ...options });
    const data = await response.json();

    if (!data.success) {
        throw new Error(data.error || 'Unbekannter Fehler');
    }
    return data.data;
}

// Flash-Nachricht anzeigen
export function showMessage(text, type = 'info', durationMs = 3000) {
    const existing = document.querySelector('.wt_flash');
    if (existing) existing.remove();

    const el = document.createElement('div');
    el.className = `wt_alert wt_alert--${type} wt_flash`;
    el.textContent = text;
    el.style.cssText = 'position:fixed;bottom:1rem;left:50%;transform:translateX(-50%);z-index:9999;min-width:260px;text-align:center;';
    document.body.appendChild(el);

    setTimeout(() => el.remove(), durationMs);
}
