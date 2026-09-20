// Globale App-Logik – JuMa

// Service Worker registrieren
if ('serviceWorker' in navigator) {
    // Merken, ob beim Laden bereits ein SW aktiv war – nur dann ist ein späterer
    // controllerchange ein echtes Update (nicht die Erstübernahme nach der Installation).
    const hadControllerAtLoad = !!navigator.serviceWorker.controller;

    navigator.serviceWorker.register('/sw.js').then((registration) => {
        // Safari prüft von selbst eher selten/inkonsistent auf ein neues sw.js –
        // deshalb aktiv nachfragen, sobald der Tab sichtbar wird bzw. periodisch.
        const checkForUpdate = () => registration.update().catch(() => {});
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') checkForUpdate();
        });
        setInterval(checkForUpdate, 5 * 60 * 1000);
    }).catch((err) => {
        console.error('Service Worker Registrierung fehlgeschlagen:', err);
    });

    // Nachrichten vom SW empfangen (z.B. Sync-Trigger)
    navigator.serviceWorker.addEventListener('message', (event) => {
        if (event.data?.type === 'SW_SYNC') {
            window.dispatchEvent(new CustomEvent('wt:sync-trigger'));
        }
    });

    // Neue Version aktiv geworden → Hinweis zum Neuladen anzeigen (nicht erzwingen,
    // damit z.B. nicht gespeicherte Offline-Bewertungen nicht verloren gehen)
    navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (hadControllerAtLoad) {
            showUpdateBanner();
        }
    });
}

/** Banner "Neue Version verfügbar" mit manuellem Neuladen-Button anzeigen */
function showUpdateBanner() {
    if (document.querySelector('.wt_update-banner')) return;

    const el = document.createElement('div');
    el.className = 'wt_update-banner';
    el.innerHTML = '<span>Neue Version verfügbar.</span>' +
        '<button type="button" class="wt_btn wt_btn--primary wt_btn--sm">Jetzt aktualisieren</button>';
    el.querySelector('button').addEventListener('click', () => window.location.reload());
    document.body.appendChild(el);
}

// Einfacher API-Client
export async function apiFetch(url, options = {}) {
    const defaults = {
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
    };
    const response = await fetch(url, { ...defaults, ...options });
    const contentType = response.headers.get('Content-Type') || '';

    const rawText = await response.text();
    let data;

    if (contentType.includes('application/json')) {
        try {
            data = JSON.parse(rawText);
        } catch (jsonError) {
            throw new Error(`Ungültige JSON-Antwort vom Server (Status ${response.status}, Länge ${rawText.length}): ${rawText}`);
        }
    } else {
        throw new Error(`Server-Antwort ist kein JSON (Status ${response.status}, Typ ${contentType}, Länge ${rawText.length}): ${rawText}`);
    }

    if (!data.success) {
        throw new Error(data.error || `Server antwortete mit Status ${response.status}`);
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

export function base64UrlToBuffer(string) {
    const padding = '='.repeat((4 - (string.length % 4)) % 4);
    const base64 = (string + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(base64);
    const buffer = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i++) {
        buffer[i] = raw.charCodeAt(i);
    }
    return buffer.buffer;
}

export function bufferToBase64Url(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.byteLength; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

// Zusätzlich global bereitstellen: app.js wird bereits über <script type="module">
// in jedem Layout eingebunden. Andere Module greifen deshalb direkt auf diese
// globalen Funktionen zu, statt app.js per import erneut zu laden – ein relativer
// import würde wegen der Cache-Busting-Query (?v=...) eine zweite, unversionierte
// Kopie von app.js nachladen und doppelt ausführen.
window.apiFetch = apiFetch;
window.showMessage = showMessage;
window.base64UrlToBuffer = base64UrlToBuffer;
window.bufferToBase64Url = bufferToBase64Url;
