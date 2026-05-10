// Offline-Sync-Logik – IndexedDB + Verbindungsüberwachung

import { apiFetch, showMessage } from './app.js';

const DB_NAME    = 'juma-tir-offline';
const DB_VERSION = 1;
const STORE_NAME = 'score_queue';

// ---- IndexedDB öffnen ----
function openDb() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);

        req.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains(STORE_NAME)) {
                db.createObjectStore(STORE_NAME, { keyPath: 'local_id', autoIncrement: true });
            }
        };

        req.onsuccess  = (e) => resolve(e.target.result);
        req.onerror    = (e) => reject(e.target.error);
    });
}

async function saveToQueue(entry) {
    const db    = await openDb();
    const tx    = db.transaction(STORE_NAME, 'readwrite');
    const store = tx.objectStore(STORE_NAME);
    store.add({ ...entry, queued_at: new Date().toISOString() });
    return new Promise((res, rej) => { tx.oncomplete = res; tx.onerror = rej; });
}

async function getAllQueued() {
    const db    = await openDb();
    const tx    = db.transaction(STORE_NAME, 'readonly');
    const store = tx.objectStore(STORE_NAME);
    return new Promise((res, rej) => {
        const req = store.getAll();
        req.onsuccess = () => res(req.result);
        req.onerror   = () => rej(req.error);
    });
}

async function clearQueue() {
    const db    = await openDb();
    const tx    = db.transaction(STORE_NAME, 'readwrite');
    tx.objectStore(STORE_NAME).clear();
    return new Promise((res, rej) => { tx.oncomplete = res; tx.onerror = rej; });
}

// ---- Offline-Bewertungen speichern ----
window.addEventListener('wt:save-offline', async (e) => {
    await saveToQueue(e.detail);
    updateQueueCount();
});

// ---- Sync-Indikator aktualisieren ----
async function updateQueueCount() {
    const items = await getAllQueued();
    const indicator = document.getElementById('syncIndicator');
    if (!indicator) return;

    if (items.length === 0) {
        indicator.textContent = '';
        indicator.className   = 'wt_sync-indicator';
    } else {
        indicator.textContent = `${items.length} offline`;
        indicator.className   = 'wt_sync-indicator wt_sync-indicator--offline';
    }
}

// ---- Synchronisation ausführen ----
async function syncQueue() {
    const items = await getAllQueued();
    if (items.length === 0) return;

    const indicator = document.getElementById('syncIndicator');
    if (indicator) {
        indicator.textContent = 'Synchronisiere…';
        indicator.className   = 'wt_sync-indicator wt_sync-indicator--syncing';
    }

    try {
        await apiFetch('/api/sync', {
            method: 'POST',
            body: JSON.stringify({ scores: items }),
        });
        await clearQueue();
        showMessage(`${items.length} Bewertungen synchronisiert!`, 'success');
    } catch {
        showMessage('Sync fehlgeschlagen – wird beim nächsten Verbindungsaufbau wiederholt.', 'error');
    } finally {
        await updateQueueCount();
    }
}

// ---- Online-/Offline-Erkennung ----
function handleOnline() {
    syncQueue();
}

window.addEventListener('online',  handleOnline);
window.addEventListener('wt:sync-trigger', syncQueue);

// Verbindungsstatus beim Start anzeigen
const indicator = document.getElementById('syncIndicator');
if (indicator && !navigator.onLine) {
    indicator.textContent = 'Offline';
    indicator.className   = 'wt_sync-indicator wt_sync-indicator--offline';
}

// Warteschlange beim Laden anzeigen
updateQueueCount();
