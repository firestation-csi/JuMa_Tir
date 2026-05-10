// QR-Code-Scan und -Anzeige – nutzt html5-qrcode CDN
// Wird dynamisch über CDN geladen um Bundle-Größe zu sparen

import { apiFetch, showMessage } from './app.js';

const QR_LIB_URL = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';

let scanner = null;

/** html5-qrcode Bibliothek lazy laden */
async function loadQrLib() {
    if (window.Html5Qrcode) return;
    await new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = QR_LIB_URL;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

/** QR-Scanner starten */
export async function startScanner(containerId, onSuccess) {
    await loadQrLib();

    if (scanner) {
        await scanner.stop().catch(() => {});
    }

    scanner = new window.Html5Qrcode(containerId);

    await scanner.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 250, height: 250 } },
        (decodedText) => {
            onSuccess(decodedText);
        },
        () => {} // Fehler beim Lesen ignorieren (normaler Vorgang)
    );
}

/** Scanner stoppen */
export async function stopScanner() {
    if (scanner) {
        await scanner.stop().catch(() => {});
        scanner = null;
    }
}

// ============================================================
// Schiedsrichter-Login-Seite
// ============================================================
const startScanBtn = document.getElementById('startScanBtn');
if (startScanBtn) {
    const container = document.getElementById('qrReaderContainer');

    startScanBtn.addEventListener('click', async () => {
        startScanBtn.disabled = true;
        startScanBtn.textContent = 'Scanner läuft…';

        try {
            await startScanner('qrReaderContainer', async (token) => {
                await stopScanner();
                await loginWithToken(token);
            });
        } catch (err) {
            showMessage('Kamera konnte nicht geöffnet werden.', 'error');
            startScanBtn.disabled = false;
            startScanBtn.textContent = 'QR-Code scannen';
        }
    });
}

// Token-Formular (manuelle Eingabe)
const tokenForm = document.getElementById('tokenForm');
if (tokenForm) {
    tokenForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const token = document.getElementById('tokenInput').value.trim();
        if (token) await loginWithToken(token);
    });
}

async function loginWithToken(token) {
    try {
        const data = await apiFetch('/api/judge/login', {
            method: 'POST',
            body: JSON.stringify({ token }),
        });
        showMessage(`Willkommen, ${data.judge_name}!`, 'success');
        setTimeout(() => (location.href = '/judge/station'), 800);
    } catch (err) {
        showMessage(err.message, 'error');
    }
}

// ============================================================
// Station-Seite: Gruppe scannen
// ============================================================
const scanGroupBtn = document.getElementById('scanGroupBtn');
if (scanGroupBtn) {
    scanGroupBtn.addEventListener('click', async () => {
        scanGroupBtn.disabled = true;
        scanGroupBtn.textContent = 'Scanner läuft…';

        try {
            await startScanner('groupQrContainer', async (token) => {
                await stopScanner();
                await verifyGroup(token);
                scanGroupBtn.disabled = false;
                scanGroupBtn.textContent = 'Gruppen-QR scannen';
            });
        } catch {
            showMessage('Kamera konnte nicht geöffnet werden.', 'error');
            scanGroupBtn.disabled = false;
            scanGroupBtn.textContent = 'Gruppen-QR scannen';
        }
    });
}

async function verifyGroup(token) {
    try {
        const data = await apiFetch('/api/group/verify', {
            method: 'POST',
            body: JSON.stringify({ token }),
        });

        document.getElementById('currentGroupId').value   = data.group_id;
        document.getElementById('currentGroupName').textContent = data.group_name;
        document.getElementById('scoreCard').classList.remove('wt_card--hidden');
        document.getElementById('groupScanCard').classList.add('wt_card--hidden');

        showMessage(`Gruppe „${data.group_name}" verifiziert.`, 'success');
    } catch (err) {
        showMessage(err.message, 'error');
    }
}

// ============================================================
// Bewertungsformular
// ============================================================
const scoreGrid = document.getElementById('scoreGrid');
if (scoreGrid) {
    let selectedValue = null;

    scoreGrid.addEventListener('click', (e) => {
        const btn = e.target.closest('.wt_score-btn');
        if (!btn) return;

        scoreGrid.querySelectorAll('.wt_score-btn').forEach((b) => b.classList.remove('wt_score-btn--selected'));
        btn.classList.add('wt_score-btn--selected');

        selectedValue = parseFloat(btn.dataset.value);
        document.getElementById('selectedScoreDisplay').textContent = selectedValue;
        document.getElementById('saveScoreBtn').disabled = false;
    });

    const scoreForm = document.getElementById('scoreForm');
    scoreForm?.addEventListener('submit', async (e) => {
        e.preventDefault();

        const stationEl = document.querySelector('[data-station-id]');
        const stationId = parseInt(stationEl?.dataset.stationId ?? '0');
        const groupId   = parseInt(document.getElementById('currentGroupId').value);
        const notes     = document.getElementById('scoreNotes').value;

        if (!groupId || selectedValue === null) {
            showMessage('Bitte Gruppe scannen und Wert auswählen.', 'error');
            return;
        }

        const payload = { group_id: groupId, station_id: stationId, value: selectedValue, notes };

        try {
            await apiFetch('/api/score', { method: 'POST', body: JSON.stringify(payload) });
            addToHistory(document.getElementById('currentGroupName').textContent, selectedValue);
            showMessage('Bewertung gespeichert!', 'success');
            resetScoreForm();
        } catch {
            // Offline: in IndexedDB speichern
            window.dispatchEvent(new CustomEvent('wt:save-offline', { detail: payload }));
            addToHistory(document.getElementById('currentGroupName').textContent, selectedValue, true);
            showMessage('Offline gespeichert – wird synchronisiert.', 'info');
            resetScoreForm();
        }
    });
}

function resetScoreForm() {
    document.getElementById('currentGroupId').value = '';
    document.getElementById('currentGroupName').textContent = '';
    document.getElementById('selectedScoreDisplay').textContent = '–';
    document.getElementById('scoreNotes').value = '';
    document.getElementById('saveScoreBtn').disabled = true;
    document.querySelectorAll('.wt_score-btn--selected').forEach((b) => b.classList.remove('wt_score-btn--selected'));
    document.getElementById('scoreCard').classList.add('wt_card--hidden');
    document.getElementById('groupScanCard').classList.remove('wt_card--hidden');
}

function addToHistory(groupName, value, offline = false) {
    const list = document.getElementById('scoreHistory');
    if (!list) return;

    const empty = list.querySelector('.wt_score-history__empty');
    if (empty) empty.remove();

    const li = document.createElement('li');
    li.innerHTML = `
        <span>${groupName}</span>
        <span class="wt_score-history__value">${value} Pkt.</span>
        <span class="wt_score-history__done">${offline ? '⏳ Offline' : '✓'}</span>
    `;
    list.prepend(li);
}
